<?php

declare(strict_types=1);

namespace Drupal\tre_ptv_import\Service\Api;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use OpenAPI\Client\Api\ConnectionApi;
use OpenAPI\Client\Api\OrganizationApi;
use OpenAPI\Client\Api\ServiceApi;
use OpenAPI\Client\Api\ServiceChannelApi;
use OpenAPI\Client\Model\ConnectionSearchResponse;
use OpenAPI\Client\Model\ModelInterface;
use OpenAPI\Client\Model\OrganizationResponse;
use OpenAPI\Client\Model\ServiceLocationChannelResponse;
use OpenAPI\Client\Model\ServiceSearchResponse;
use OpenAPI\Client\Api\MunicipalitiesApi;

/**
 * Fetches PTV API data through the generated OpenAPI client.
 */
final class PtvApiFetcher {

  /**
   * The maximum API page size.
   */
  private const MAX_PAGE_SIZE = 100;

  /**
   * The maximum service IDs per connection request.
   */
  private const CONNECTION_SERVICE_ID_BATCH_SIZE = 20;
  /**
   * The MUNICIPALITY IDs per request.
   */
  private const MUNICIPALITY_BATCH_SIZE = 20;

  /**
   * The module logger.
   */
  private LoggerChannelInterface $logger;

  /**
   * Constructs the fetcher.
   */
  public function __construct(
    private readonly ServiceApi $serviceApi,
    private readonly ConnectionApi $connectionApi,
    private readonly ServiceChannelApi $serviceChannelApi,
    private readonly OrganizationApi $organizationApi,
    private readonly MunicipalitiesApi $municipalitiesApi,
    private readonly ClientInterface $httpClient,
    private readonly PtvApiSettings $settings,
    private readonly TprAccessibilityFetcher $tprAccessibilityFetcher,
    LoggerChannelFactoryInterface $loggerFactory,
  ) {
    $this->logger = $loggerFactory->get('tre_ptv_import');
  }

  /**
   * Fetches one service search page.
   */
  public function fetchServicePage(
    int $page,
    int $pageSize = self::MAX_PAGE_SIZE,
    ?string $organizationContentId = NULL,
  ): ServiceSearchResponse {
    $this->validatePagination($page, $pageSize);

    $organizationContentId = $organizationContentId !== NULL
      ? trim($organizationContentId)
      : $this->settings->getOrganizationContentId();

    if ($organizationContentId === '') {
      throw new \InvalidArgumentException(
        'The organization content ID cannot be empty.'
      );
    }

    return $this->serviceApi->searchServices(
      page: $page,
      page_size: $pageSize,
      organization_content_ids: [$organizationContentId],
    );
  }

  /**
   * Fetches one service by content ID.
   */
  public function fetchService(string $serviceContentId): ModelInterface {
    $serviceContentId = trim($serviceContentId);

    if ($serviceContentId === '') {
      throw new \InvalidArgumentException(
        'The service content ID cannot be empty.'
      );
    }

    $service = $this->serviceApi->getService(
      content_id: $serviceContentId
    );

    if (!$service instanceof ModelInterface) {
      throw new \RuntimeException(
        'PTV API did not return a valid service model for "'
        . $serviceContentId
        . '".'
      );
    }

    if (
      method_exists($service, 'getContentId')
      && $service->getContentId() !== $serviceContentId
    ) {
      throw new \RuntimeException(
        'PTV API returned service "'
        . $service->getContentId()
        . '" when service "'
        . $serviceContentId
        . '" was requested.'
      );
    }

    return $service;
  }

  /**
   * Fetches services by content ID.
   *
   * @param string[] $serviceContentIds
   *   Service content IDs.
   *
   * @return array<string, \OpenAPI\Client\Model\ModelInterface>
   *   Generated service DTOs keyed by content ID.
   */
  public function fetchServices(array $serviceContentIds): array {
    $serviceContentIds = $this->normalizeContentIds($serviceContentIds);

    if ($serviceContentIds === []) {
      return [];
    }

    $services = [];

    foreach ($serviceContentIds as $serviceContentId) {
      $services[$serviceContentId] = $this->fetchService($serviceContentId);
    }

    return $services;
  }

  /**
   * Fetches all services.
   * 
   * PTV API has pagination for Services
   *
   * @return array<int, object>
   *   Generated service DTOs.
   */
  public function fetchAllServices(
    ?string $organizationContentId = NULL,
  ): array {
    $page = 1;
    $services = [];

    do {
      $response = $this->fetchServicePage(
        $page,
        self::MAX_PAGE_SIZE,
        $organizationContentId
      );

      foreach ($response->getItems() ?? [] as $service) {
        $services[] = $service;
      }

      $totalPages = max(1, (int) $response->getTotalPages());
      $page++;
    } while ($page <= $totalPages);

    $this->logger->notice(
      'PTV API: fetched @count services from @pages page(s).',
      [
        '@count' => count($services),
        '@pages' => $totalPages,
      ]
    );

    return $services;
  }

  /**
   * Fetches connections for one service ID batch.
   *
   * @param string[] $serviceContentIds
   *   Service content IDs.
   *
   * @return array<int, object>
   *   Generated connection DTOs.
   */
  public function fetchConnectionsForServiceIds(
    array $serviceContentIds,
  ): array {
    $serviceContentIds = $this->normalizeContentIds($serviceContentIds);

    if ($serviceContentIds === []) {
      return [];
    }

    if (count($serviceContentIds) > self::CONNECTION_SERVICE_ID_BATCH_SIZE) {
      throw new \InvalidArgumentException(
        'A connection search can contain at most '
        . self::CONNECTION_SERVICE_ID_BATCH_SIZE
        . ' service IDs.'
      );
    }

    $page = 1;
    $connections = [];

    do {
      $response = $this->fetchConnectionPageForServices(
        $page,
        self::MAX_PAGE_SIZE,
        $serviceContentIds
      );

      foreach ($response->getItems() ?? [] as $connection) {
        $connections[] = $connection;
      }

      $totalPages = max(1, (int) $response->getTotalPages());
      $page++;
    } while ($page <= $totalPages);

    return $connections;
  }

  /**
   * Fetches all connections for service IDs.
   *
   * @param string[] $serviceContentIds
   *   Service content IDs.
   *
   * @return array<int, object>
   *   Generated connection DTOs.
   */
  public function fetchConnectionsForServices(
    array $serviceContentIds,
  ): array {
    $serviceContentIds = $this->normalizeContentIds($serviceContentIds);

    if ($serviceContentIds === []) {
      return [];
    }
    // Connection request allows maximum of 20 service_content_ids values per request -> CONNECTION_SERVICE_ID_BATCH_SIZE is used
    $connections = [];
    $batches = array_chunk(
      $serviceContentIds,
      self::CONNECTION_SERVICE_ID_BATCH_SIZE
    );

    foreach ($batches as $batchIndex => $serviceIdBatch) {
      $this->logger->notice(
        'PTV API: fetching connection batch @current/@total for @count service(s).',
        [
          '@current' => $batchIndex + 1,
          '@total' => count($batches),
          '@count' => count($serviceIdBatch),
        ]
      );

      array_push(
        $connections,
        ...$this->fetchConnectionsForServiceIds($serviceIdBatch)
      );
    }

    $this->logger->notice(
      'PTV API: fetched @count connection(s) for @service_count service(s).',
      [
        '@count' => count($connections),
        '@service_count' => count($serviceContentIds),
      ]
    );

    return $connections;
  }

  /**
   * Fetches one service channel as a concrete generated DTO.
   */
  public function fetchChannel(string $channelContentId): ModelInterface {
    $channelContentId = trim($channelContentId);

    if ($channelContentId === '') {
      throw new \InvalidArgumentException(
        'The channel content ID cannot be empty.'
      );
    }

    $request = $this->serviceChannelApi->getChannelRequest(
      $channelContentId
    );

    $data = $this->sendJsonRequest($request);
    $channel = PtvChannelResponseDeserializer::deserialize($data);

    if ($channel->getContentId() !== $channelContentId) {
      throw new \RuntimeException(
        'PTV API returned channel "'
        . $channel->getContentId()
        . '" when channel "'
        . $channelContentId
        . '" was requested.'
      );
    }

    return $channel;
  }

  /**
   * Fetches service channels by content ID.
   *
   * @param string[] $channelContentIds
   *   Channel content IDs.
   *
   * @return array<string, \OpenAPI\Client\Model\ModelInterface>
   *   Generated channel DTOs keyed by content ID.
   */
  public function fetchChannels(array $channelContentIds): array {
    $channelContentIds = $this->normalizeContentIds($channelContentIds);

    if ($channelContentIds === []) {
      return [];
    }

    $channels = [];

    foreach ($channelContentIds as $channelContentId) {
      $channels[$channelContentId] = $this->fetchChannel($channelContentId);
    }

    return $channels;
  }

  /**
   * Fetches one organization.
   */
  public function fetchOrganization(
    string $organizationContentId,
  ): OrganizationResponse {
    $organizationContentId = trim($organizationContentId);

    if ($organizationContentId === '') {
      throw new \InvalidArgumentException(
        'The organization content ID cannot be empty.'
      );
    }

    return $this->organizationApi->getOrganization($organizationContentId);
  }

  /**
   * Fetches a complete import package.
   *
   * @return array{
   *   services: array<string, array{
   *     payload: object,
   *     connections: array<int, object>
   *   }>,
   *   channels: array<string, array{
   *     payload: \OpenAPI\Client\Model\ModelInterface,
   *     accessibility?: array<string, array<string, array<int, array{
   *       title: string,
   *       sentences: string[]
   *     }>>>
   *   }>,
   *   statistics: array<string, int>
   * }
   *   Import data keyed by service and channel IDs.
   */
  public function fetchImportData(
    ?string $organizationContentId = NULL,
  ): array {
    $servicesById = [];
    // Fetch services and related connection for those services, connections are used to link service channels to services
    foreach ($this->fetchAllServices($organizationContentId) as $service) {
      if (!$service instanceof ModelInterface) {
        continue;
      }

      $serviceId = $service->getContentId();
      $servicesById[$serviceId] = $service;
    }
    // Fetch all connections based on service IDs - note that connection are linked to services in the buildImportData()
    $connections = $this->fetchConnectionsForServices(
      array_keys($servicesById)
    );

    $connectionsByChannelId = $this->groupConnectionsByChannelId(
      $connections
    );
    // Fetch all the service channels using connection IDs - meaning we only fetch the channels that are referenced by services via connections
    $channelsById = $this->fetchChannels(
      array_keys($connectionsByChannelId)
    );

    return $this->buildImportData(
      $servicesById,
      $channelsById,
      $connections
    );
  }

  /**
   * Fetches an import package for selected service IDs.
   *
   * @param string[] $serviceContentIds
   *   Service content IDs.
   *
   * @return array{
   *   services: array<string, array{
   *     payload: \OpenAPI\Client\Model\ModelInterface,
   *     connections: array<int, object>
   *   }>,
   *   channels: array<string, array{
   *     payload: \OpenAPI\Client\Model\ModelInterface,
   *     accessibility?: array<string, array<string, array<int, array{
   *       title: string,
   *       sentences: string[]
   *     }>>>
   *   }>,
   *   statistics: array<string, int>
   * }
   *   Import data keyed by service and channel IDs.
   */
  public function fetchImportDataForServices(array $serviceContentIds): array {
    $servicesById = $this->fetchServices($serviceContentIds);

    $connections = $this->fetchConnectionsForServices(
      array_keys($servicesById)
    );

    $connectionsByChannelId = $this->groupConnectionsByChannelId(
      $connections
    );

    $channelsById = $this->fetchChannels(
      array_keys($connectionsByChannelId)
    );

    return $this->buildImportData(
      $servicesById,
      $channelsById,
      $connections
    );
  }

  /**
   * Fetches an import package for selected channel IDs.
   *
   * @param string[] $channelContentIds
   *   Channel content IDs.
   *
   * @return array{
   *   services: array<string, array{
   *     payload: \OpenAPI\Client\Model\ModelInterface,
   *     connections: array<int, object>
   *   }>,
   *   channels: array<string, array{
   *     payload: \OpenAPI\Client\Model\ModelInterface,
   *     accessibility?: array<string, array<string, array<int, array{
   *       title: string,
   *       sentences: string[]
   *     }>>>
   *   }>,
   *   statistics: array<string, int>
   * }
   *   Import data keyed by service and channel IDs.
   */
  public function fetchImportDataForChannels(array $channelContentIds): array {
    $channelsById = $this->fetchChannels($channelContentIds);

    return $this->buildImportData(
      [],
      $channelsById,
      []
    );
  }

  /**
   * Fetches one connection search page by service IDs.
   *
   * @param string[] $serviceContentIds
   *   Service content IDs.
   */
  private function fetchConnectionPageForServices(
    int $page,
    int $pageSize,
    array $serviceContentIds,
  ): ConnectionSearchResponse {
    $this->validatePagination($page, $pageSize);

    return $this->connectionApi->searchConnections(
      page: $page,
      page_size: $pageSize,
      service_content_ids: $serviceContentIds,
    );
  }

  /**
   * Builds the standard import data structure.
   *
   * @param array<string, \OpenAPI\Client\Model\ModelInterface> $servicesById
   *   Service models keyed by content ID.
   * @param array<string, \OpenAPI\Client\Model\ModelInterface> $channelsById
   *   Channel models keyed by content ID.
   * @param array<int, object> $connections
   *   Connection models.
   *
   * @return array<string, mixed>
   *   Import data keyed by service and channel IDs.
   */
  private function buildImportData(
    array $servicesById,
    array $channelsById,
    array $connections,
  ): array {
    $connectionsByServiceId = $this->groupConnectionsByServiceId(
      $connections
    );

    $municipalityCodes = $this->extractMunicipalityCodes(
      array_merge($servicesById, $channelsById)
    );

    $this->logger->notice(
      'PTV API Fetcher: Extracted @code_count unique municipality code(s) from @service_count services and @channel_count channels. Sample codes: @codes',
      [
        '@code_count' => count($municipalityCodes),
        '@service_count' => count($servicesById),
        '@channel_count' => count($channelsById),
        '@codes' => implode(', ', array_slice($municipalityCodes, 0, 10)),
      ]
    );

    $areasByCode = $this->fetchAreasByCodes($municipalityCodes);

    $this->logger->notice(
      'PTV API Fetcher: Successfully fetched @area_count area details from MunicipalitiesApi.',
      ['@area_count' => count($areasByCode)]
    );

    $serviceRecords = [];
    foreach ($servicesById as $serviceId => $service) {
      $serviceCodes = $this->extractMunicipalityCodesFromEntity($service);
      $serviceAreas = array_intersect_key($areasByCode, array_flip($serviceCodes));

      $serviceRecords[$serviceId] = [
        'payload' => $service,
        'connections' => $connectionsByServiceId[$serviceId] ?? [],
        'areas' => array_values($serviceAreas),
      ];
    }

    $channelRecords = [];
    foreach ($channelsById as $channelId => $channel) {
      $channelCodes = $this->extractMunicipalityCodesFromEntity($channel);
      $channelAreas = array_intersect_key($areasByCode, array_flip($channelCodes));

      $channelRecord = [
        'payload' => $channel,
        'areas' => array_values($channelAreas),
      ];

      $accessibility = $this->fetchChannelAccessibility($channel);

      if ($accessibility !== []) {
        $channelRecord['accessibility'] = $accessibility;
      }

      $channelRecords[$channelId] = $channelRecord;
    }

    $statistics = [
      'service_count' => count($serviceRecords),
      'connection_count' => count($connections),
      'channel_count' => count($channelRecords),
      'area_count' => count($areasByCode),
    ];

    $this->logger->notice(
      'PTV API: import package ready. Services: @services, connections: @connections, channels: @channels, unique areas: @areas.',
      [
        '@services' => $statistics['service_count'],
        '@connections' => $statistics['connection_count'],
        '@channels' => $statistics['channel_count'],
        '@areas' => $statistics['area_count'],
      ]
    );

    return [
      'services' => $serviceRecords,
      'channels' => $channelRecords,
      'statistics' => $statistics,
    ];
  }

  /**
   * Extracts unique municipality codes from entity models.
   *
   * @param array<string, \OpenAPI\Client\Model\ModelInterface> $entitiesById
   *   Entity models keyed by content ID.
   *
   * @return string[]
   *   Unique municipality codes.
   */
  private function extractMunicipalityCodes(array $entitiesById): array {
    $codes = [];

    foreach ($entitiesById as $entity) {
      foreach ($this->extractMunicipalityCodesFromEntity($entity) as $code) {
        $codes[$code] = $code;
      }
    }

    return array_values($codes);
  }

  /**
   * Extracts municipality codes from a single entity model.
   *
   * @param \OpenAPI\Client\Model\ModelInterface $entity
   *   The entity model.
   *
   * @return string[]
   *   Municipality codes found in the entity area data.
   */
  private function extractMunicipalityCodesFromEntity(ModelInterface $entity): array {
    if (!method_exists($entity, 'getArea')) {
      return [];
    }

    $area = $entity->getArea();

    if ($area === NULL || !is_object($area) || !method_exists($area, 'getMunicipalities')) {
      return [];
    }

    $municipalities = $area->getMunicipalities();

    if (!is_array($municipalities)) {
      return [];
    }

    return array_filter($municipalities, 'is_string');
  }

  /**
   * Fetches area details by municipality codes in batches.
   *
   * @param string[] $codes
   *   Unique municipality codes.
   *
   * @return array<string, array{code: string, name: array<string, string|null>}>
   *   Area information keyed by municipality code.
   */
  private function fetchAreasByCodes(array $codes): array {
    if ($codes === []) {
      return [];
    }

    $codes = array_values(array_unique($codes));
    $batches = array_chunk($codes, self::MUNICIPALITY_BATCH_SIZE);
    $areasByCode = [];

    $this->logger->notice(
      'PTV API: Fetching area details for @count unique municipality code(s) in @batches batch(es).',
      [
        '@count' => count($codes),
        '@batches' => count($batches),
      ]
    );

    foreach ($batches as $batchIndex => $batch) {
      try {
        $response = $this->municipalitiesApi->getMunicipalityCodes(
          page: 1,
          page_size: self::MAX_PAGE_SIZE,
          codes: $batch
        );

        $items = method_exists($response, 'getItems') ? ($response->getItems() ?? []) : [];

        foreach ($items as $item) {
          $code = method_exists($item, 'getCode') ? $item->getCode() : NULL;
          if (!$code) {
            continue;
          }

          $nameModel = method_exists($item, 'getName') ? $item->getName() : NULL;

          $areasByCode[$code] = [
            'code' => $code,
            'name' => [
              'fi' => method_exists($nameModel, 'getFi') ? $nameModel->getFi() : NULL,
              'sv' => method_exists($nameModel, 'getSv') ? $nameModel->getSv() : NULL,
              'en' => method_exists($nameModel, 'getEn') ? $nameModel->getEn() : NULL,
            ],
          ];
        }
      }
      catch (\Exception $e) {
        $this->logger->error(
          'PTV API: Failed to fetch municipality codes batch @batch: @message',
          [
            '@batch' => $batchIndex + 1,
            '@message' => $e->getMessage(),
          ]
        );
      }
    }

    return $areasByCode;
  }

  /**
   * Fetches accessibility information for one ServiceLocation channel.
   *
   * Accessibility information is keyed by the accessibility register service
   * point ID found on each physical street address.
   *
   * @return array<string, array{
   *   fi: array<int, array{
   *     title: string,
   *     sentences: string[]
   *   }>,
   *   en: array<int, array{
   *     title: string,
   *     sentences: string[]
   *   }>
   * }>
   *   Accessibility information keyed by service point ID.
   */
  private function fetchChannelAccessibility(
    ModelInterface $channel,
  ): array {
    if (!$channel instanceof ServiceLocationChannelResponse) {
      return [];
    }

    $location = $channel->getLocation();

    if (!is_object($location)) {
      return [];
    }

    $streetAddresses = $location->getStreetAddresses();

    if (!is_array($streetAddresses)) {
      return [];
    }

    $servicePointIds = [];

    foreach ($streetAddresses as $streetAddress) {
      if (
        !is_object($streetAddress)
        || !method_exists(
          $streetAddress,
          'getAccessibilityRegisterServicePointId'
        )
      ) {
        continue;
      }

      $servicePointId = $streetAddress
        ->getAccessibilityRegisterServicePointId();

      if (!is_string($servicePointId)) {
        continue;
      }

      $servicePointId = trim($servicePointId);

      if ($servicePointId === '') {
        continue;
      }

      $servicePointIds[$servicePointId] = $servicePointId;
    }

    if ($servicePointIds === []) {
      return [];
    }

    $accessibility = [];

    foreach ($servicePointIds as $servicePointId) {
      $accessibility[$servicePointId] =
        $this->tprAccessibilityFetcher->fetch($servicePointId);
    }

    $this->logger->notice(
      'TPR accessibility API: fetched accessibility information for @count service point(s) connected to PTV channel @channel_id.',
      [
        '@count' => count($accessibility),
        '@channel_id' => $channel->getContentId(),
      ]
    );

    return $accessibility;
  }

  /**
   * Groups connections by service content ID.
   *
   * @param array<int, object> $connections
   *   Connection models.
   *
   * @return array<string, array<int, object>>
   *   Connections keyed by service content ID.
   */
  private function groupConnectionsByServiceId(array $connections): array {
    $connectionsByServiceId = [];

    foreach ($connections as $connection) {
      if (!method_exists($connection, 'getServiceContentId')) {
        continue;
      }

      $serviceId = $connection->getServiceContentId();

      if (is_string($serviceId) && trim($serviceId) !== '') {
        $connectionsByServiceId[trim($serviceId)][] = $connection;
      }
    }

    return $connectionsByServiceId;
  }

  /**
   * Groups connections by channel content ID.
   *
   * @param array<int, object> $connections
   *   Connection models.
   *
   * @return array<string, array<int, object>>
   *   Connections keyed by channel content ID.
   */
  private function groupConnectionsByChannelId(array $connections): array {
    $connectionsByChannelId = [];

    foreach ($connections as $connection) {
      if (!method_exists($connection, 'getServiceChannelContentId')) {
        continue;
      }

      $channelId = $connection->getServiceChannelContentId();

      if (is_string($channelId) && trim($channelId) !== '') {
        $connectionsByChannelId[trim($channelId)][] = $connection;
      }
    }

    return $connectionsByChannelId;
  }

  /**
   * Sends one generated request and decodes JSON.
   *
   * @return array<string, mixed>
   *   Decoded response data.
   */
  private function sendJsonRequest(
    \Psr\Http\Message\RequestInterface $request,
  ): array {
    try {
      $response = $this->httpClient->send($request);
    }
    catch (GuzzleException $exception) {
      throw new \RuntimeException(
        'PTV API request failed: ' . $exception->getMessage(),
        0,
        $exception
      );
    }

    try {
      $data = json_decode(
        (string) $response->getBody(),
        TRUE,
        512,
        JSON_THROW_ON_ERROR
      );
    }
    catch (\JsonException $exception) {
      throw new \RuntimeException(
        'PTV API returned invalid JSON: ' . $exception->getMessage(),
        0,
        $exception
      );
    }

    if (!is_array($data)) {
      throw new \RuntimeException(
        'PTV API response did not decode into an array.'
      );
    }

    return $data;
  }

  /**
   * Validates pagination values.
   */
  private function validatePagination(int $page, int $pageSize): void {
    if ($page < 1) {
      throw new \InvalidArgumentException(
        'The page number must be at least 1.'
      );
    }

    if ($pageSize < 1 || $pageSize > self::MAX_PAGE_SIZE) {
      throw new \InvalidArgumentException(
        'The page size must be between 1 and '
        . self::MAX_PAGE_SIZE
        . '.'
      );
    }
  }

  /**
   * Normalizes content IDs.
   *
   * @param array<int, mixed> $contentIds
   *   Input values.
   *
   * @return string[]
   *   Unique content IDs.
   */
  private function normalizeContentIds(array $contentIds): array {
    $normalized = [];

    foreach ($contentIds as $contentId) {
      if (!is_string($contentId)) {
        throw new \InvalidArgumentException(
          'All content IDs must be strings.'
        );
      }

      $contentId = trim($contentId);

      if ($contentId === '') {
        throw new \InvalidArgumentException(
          'Content IDs cannot be empty.'
        );
      }

      $normalized[$contentId] = $contentId;
    }

    return array_values($normalized);
  }

}