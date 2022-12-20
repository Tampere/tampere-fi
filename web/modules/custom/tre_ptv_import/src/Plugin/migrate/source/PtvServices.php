<?php

namespace Drupal\tre_ptv_import\Plugin\migrate\source;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\SerializationInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Drupal\tre_ptv_import\PtvDataHelpersInterface;
use Drupal\tre_ptv_import\PtvServiceIntermediateStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Tampere\PtvV11\PtvApi\OrganizationApi;
use Tampere\PtvV11\PtvModel\V11VmOpenApiService;
use Tampere\PtvV11\PtvModel\VmOpenApiItem;

/**
 * Plugin class for source plugin for PTV Services.
 *
 * @MigrateSource(
 *   id = "ptv_services"
 * )
 */
class PtvServices extends SqlBase {

  /**
   * The database table to use in the source data query.
   */
  const TABLE = 'tre_ptv_import_service';

  /**
   * The serialization service.
   *
   * @var \Drupal\Component\Serialization\SerializationInterface
   */
  public SerializationInterface $serialization;

  /**
   * The language to get the information in.
   *
   * @var string
   */
  public string $contentLanguage;

  /**
   * The PTV data helpers service.
   *
   * @var \Drupal\tre_ptv_import\PtvDataHelpersInterface
   */
  public PtvDataHelpersInterface $dataHelpers;

  /**
   * The PTV data intermediate storage service.
   *
   * @var \Drupal\tre_ptv_import\PtvServiceIntermediateStorageInterface
   */
  public PtvServiceIntermediateStorageInterface $ptvStorage;

  /**
   * A Swagger API instance capable of making requests to the organization API.
   *
   * @var \Tampere\PtvV11\PtvApi\OrganizationApi
   */
  public OrganizationApi $organizationApiConnection;

  /**
   * Cache bin for organization names.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  public CacheBackendInterface $organizationNameCache;

  /**
   * The Datetime Time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  public TimeInterface $timeService;

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select(static::TABLE, 'service')
      ->fields('service');

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = [
      'uuid' => '(string) The UUID.',
      'name' => '(string) The name of the service.',
      'alternative_name' => '(string, optional) The alternative name of the service.',
      'description' => '(string, optional) The main description.',
      'summary' => '(string, optional) The summary.',
      'user_instruction' => '(string, optional) The user instruction text.',
      'requirements' => '(string, optional) The requirements for the service.',
      'chargeability' => '(string, optional) Whether the service is free of charge (FreeOfCharge) or not (Chargeable)',
      'chargeability_info' => '(string, optional) Textual additional information pertaining to chargeability.',
      'service_vouchers_in_use' => '(bool) Whether the service has service voucher in use.',
      'service_voucher_links' => '(array, optional) Structured arrays of service voucher links.',
      'languages' => '(array, optional) Array of strings (language codes) for the languages the service is available in.',
      'service_producer' => '(string, optional) The name(s) of the producer(s) for the service.',
      'service_responsible' => '(string, optional) The name(s) of the organization(s) responsible for the service.',
      'service_other_responsible' => '(string, optional) The name(s) of the other responsible organization(s) for the service.',
      'areas_text' => '(string, formatted text) Formatted text requiring <ul> and <h3> element support from the text format, listing areas for the service.',
      'life_situations' => '(referenceArray, optional) The target groups and life events of the service as IDs',
      'keywords' => '(referenceArray, optional) The ontology terms of the service as IDs',
      'topics' => '(referenceArray, optional) The service classes and life events of the service as IDs',
      'service_locations' => '(referenceArray, optional) The location service channels of the service as IDs',
      'eservice_channels' => '(referenceArray, optional) The e-service channels of the service as IDs.',
      'phone_service_channels' => '(referenceArray, optional) The phone service channels of the service as IDs.',
      'web_page_service_channels' => '(referenceArray, optional) The web page service channels of the service as IDs.',
    ];

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'uuid' => [
        'type' => 'string',
        'max_length' => 50,
        'is_ascii' => TRUE,
        'alias' => 'service',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    /** @var \Tampere\PtvV11\PtvModel\V11VmOpenApiService $service */
    $service = $this->serialization::decode($row->getSourceProperty('data'));

    if (!($service instanceof V11VmOpenApiService)) {
      return FALSE;
    }

    $values = self::mangleServiceIntoSourceValues(
      $service,
      $this->contentLanguage,
      $this->dataHelpers,
      $this->ptvStorage,
      $this->organizationApiConnection,
      $this->organizationNameCache,
      $this->timeService
    );

    if (empty($values)) {
      return FALSE;
    }

    foreach ($values as $property => $value) {
      $row->setSourceProperty($property, $value);
    }

    return parent::prepareRow($row);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration = NULL) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition, $migration);
    $instance->serialization = $container->get('serialization.phpserialize');
    $instance->contentLanguage = $configuration['language'];
    $instance->dataHelpers = $container->get('tre_ptv_import.ptv_data_helpers');
    $instance->ptvStorage = $container->get('tre_ptv_import.ptv_intermediate_storage');
    $instance->organizationApiConnection = new OrganizationApi(\Drupal::service('http_client'), \Drupal::service('tre_ptv_import.import_config'));
    $instance->organizationNameCache = $container->get('cache.tre_ptv_import_organization_names');
    $instance->timeService = $container->get('datetime.time');

    return $instance;
  }

  /**
   * Transforms V11VmOpenApiService objects into source row data.
   *
   * @param \Tampere\PtvV11\PtvModel\V11VmOpenApiService $service
   *   The V11VmOpenApiServiceLocationChannel object to transform.
   * @param string $language
   *   The language to use in requesting the data.
   * @param \Drupal\tre_ptv_import\PtvDataHelpersInterface $data_helpers
   *   The PtvDataHelpers service to use.
   * @param \Drupal\tre_ptv_import\PtvServiceIntermediateStorageInterface $ptv_storage
   *   The PtvServiceIntermediateStorage service to use.
   * @param \Tampere\PtvV11\PtvApi\OrganizationApi $organization_api_connection
   *   The API to use for requesting organization data.
   * @param \Drupal\Core\Cache\CacheBackendInterface $organizationNameCache
   *   The cache bin to store organization names in.
   * @param \Drupal\Component\Datetime\TimeInterface $timeService
   *   The time service.
   *
   * @return array
   *   Associative array keyed by source field names, or an empty array to
   *   enable skipping the import for this particular row.
   *
   * @throws \Tampere\PtvV11\ApiException
   */
  public static function mangleServiceIntoSourceValues(
    V11VmOpenApiService $service,
    string $language,
    PtvDataHelpersInterface $data_helpers,
    PtvServiceIntermediateStorageInterface $ptv_storage,
    OrganizationApi $organization_api_connection,
    CacheBackendInterface $organizationNameCache,
    TimeInterface $timeService
  ): array {
    $values = [
      'uuid' => $service->getId(),
    ];

    $values['name'] = $data_helpers::getDescriptionStringByLanguageAndType($service->getServiceNames(), $language, 'Name');

    if (empty($values['name'])) {
      return [];
    }

    $values['alternative_name'] = $data_helpers::getDescriptionStringByLanguageAndType($service->getServiceNames(), $language, 'AlternativeName');

    $type = 'Description';
    $values['description'] = $data_helpers::getDescriptionStringByLanguageAndType($service->getServiceDescriptions(), $language, $type);
    $type = 'Summary';
    $values['summary'] = $data_helpers::getDescriptionStringByLanguageAndType($service->getServiceDescriptions(), $language, $type);
    $type = 'UserInstruction';
    $values['user_instruction'] = $data_helpers::getDescriptionStringByLanguageAndType($service->getServiceDescriptions(), $language, $type);

    $values['requirements'] = implode("\n", array_map(function ($requirement) {
      return $requirement->getValue();
    }, $data_helpers::getOpenApiLanguageItemStringsByLanguage($service->getRequirements(), $language)));

    $values['chargeability'] = $service->getServiceChargeType();
    $type = 'ChargeTypeAdditionalInfo';
    $values['chargeability_info'] = $data_helpers::getDescriptionStringByLanguageAndType($service->getServiceDescriptions(), $language, $type);

    $values['service_vouchers_in_use'] = $service->getServiceVouchersInUse();

    $vouchers_in_language = array_filter($service->getServiceVouchers(), function ($voucher) use ($language) {
      return $voucher->getLanguage() == $language;
    });
    $values['service_voucher_links'] = array_map(function ($voucher) {
      return [
        'uri' => $voucher->getUrl(),
        'title' => $voucher->getValue(),
      ];
    }, $vouchers_in_language);

    $values['languages'] = $service->getLanguages();

    $organizations = $service->getOrganizations();

    $values['service_producer'] = static::processOrganizationsByType(
      $organizations,
      $language,
      'Producer',
      $data_helpers,
      $organization_api_connection,
      $organizationNameCache,
      $timeService
    );

    $values['service_responsible'] = static::processOrganizationsByType(
      $organizations,
      $language,
      'Responsible',
      $data_helpers,
      $organization_api_connection,
      $organizationNameCache,
      $timeService
    );

    $values['service_other_responsible'] = static::processOrganizationsByType(
      $organizations,
      $language,
      'OtherResponsible',
      $data_helpers,
      $organization_api_connection,
      $organizationNameCache,
      $timeService
    );

    $values['areas_text'] = $data_helpers::processAreas($service->getAreas(), $language);

    $life_events = array_map(function ($event) {
      return $event->getNewUri();

    }, $service->getLifeEvents());
    $target_groups = array_map(function ($group) {
      return $group->getNewUri();

    }, $service->getTargetGroups());

    $values['life_situations'] = array_merge($life_events, $target_groups);
    $values['topics'] = array_map(function ($class) {
      return $class->getNewUri();

    }, $service->getServiceClasses());
    $values['keywords'] = array_map(function ($term) {
      return $term->getUri();

    }, $service->getOntologyTerms());

    $service_channel_field_names_by_class = [
      'Tampere\PtvV11\PtvModel\V11VmOpenApiServiceLocationChannel' => 'service_locations',
      'Tampere\PtvV11\PtvModel\V11VmOpenApiElectronicChannel' => 'eservice_channels',
      'Tampere\PtvV11\PtvModel\V11VmOpenApiPhoneChannel' => 'phone_service_channels',
      'Tampere\PtvV11\PtvModel\V11VmOpenApiWebPageChannel' => 'web_page_service_channels',
      'Tampere\PtvV11\PtvModel\V11VmOpenApiPrintableFormChannel' => 'form_service_channels',
    ];

    $all_service_channels = $ptv_storage->getServiceChannelsFromStorageByService($service);

    foreach ($service_channel_field_names_by_class as $service_channel_class => $service_channel_field_name) {
      $service_channels_of_type = array_filter($all_service_channels, function ($channel) use ($service_channel_class) {
        return $channel instanceof $service_channel_class;
      });

      $values[$service_channel_field_name] = array_map(function ($channel) {
        return $channel->getId();
      }, $service_channels_of_type);
    }

    return $values;
  }

  /**
   * Creates a string representation of organization(s) behind a service.
   *
   * @param \Tampere\PtvV11\PtvModel\V6VmOpenApiServiceOrganization[] $organizations
   *   The organizations for a service, accessible by the 'getOrganizations()'
   *   method.
   * @param string $language
   *   The language to use for extracting the name.
   * @param string $organization_type
   *   The type of organization(s) to process (e.g. 'Producer', 'Responsible',
   *   'OtherResponsible').
   * @param \Drupal\tre_ptv_import\PtvDataHelpersInterface $data_helpers
   *   The PtvDataHelpers instance to help in digging into the data.
   * @param \Tampere\PtvV11\PtvApi\OrganizationApi $organization_api_connection
   *   The OrganizationApi connection to use for organization data requests.
   * @param \Drupal\Core\Cache\CacheBackendInterface $organizationNameCache
   *   The cache bin to store organization names in.
   * @param \Drupal\Component\Datetime\TimeInterface $timeService
   *   The time service.
   *
   * @return string|null
   *   A string of organization names of given type, or null if none available.
   *
   * @throws \Tampere\PtvV11\ApiException
   */
  protected static function processOrganizationsByType(
    array $organizations,
    string $language,
    string $organization_type,
    PtvDataHelpersInterface $data_helpers,
    OrganizationApi $organization_api_connection,
    CacheBackendInterface $organizationNameCache,
    TimeInterface $timeService
  ): ?string {
    $organizations_of_type = array_filter($organizations, function ($organization) use ($organization_type) {
      return $organization->getRoleType() === $organization_type;
    });

    if (empty($organizations_of_type)) {
      return NULL;
    }

    $all_organization_names_of_type = [];
    foreach ($organizations_of_type as $organization_of_type) {
      $organization_item = $organization_of_type->getOrganization();

      if (!($organization_item instanceof VmOpenApiItem)) {
        continue;
      }

      $organization_id = $organization_item->getId();
      $all_organization_names_of_type[] = $data_helpers::getOrganizationNameByLanguage(
        $organization_id,
        $language,
        $organization_api_connection,
        $organizationNameCache,
        $timeService
      );
    }

    return implode(", ", array_filter($all_organization_names_of_type));
  }

}
