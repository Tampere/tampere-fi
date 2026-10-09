<?php

declare(strict_types=1);

namespace Drupal\tre_ptv_import\Plugin\migrate\source;

use Drupal\Core\Database\Connection;
use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Drupal\tre_ptv_import\Service\Migration\PtvServiceSourceMapper;
use Drupal\tre_ptv_import\Service\Storage\PtvStorageRecordDecoder;
use OpenAPI\Client\Model\ServiceResponse;
use OpenAPI\Client\ObjectSerializer;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Source plugin for PTV services.
 *
 * @MigrateSource(
 *   id = "ptv_services"
 * )
 */
class PtvServices extends SqlBase {

  /**
   * The database table to use in the source data query.
   */
  private const TABLE = 'tre_ptv_import_service';

  /**
   * The language to get the information in.
   */
  private string $contentLanguage;

  /**
   * The requested PTV content IDs.
   *
   * An empty array means that the migration imports all matching rows.
   *
   * @var string[]
   */
  private array $ptvContentIds = [];

  /**
   * The service source mapper.
   */
  private PtvServiceSourceMapper $serviceSourceMapper;

  /**
   * The database connection for intermediate storage lookups.
   */
  private Connection $storageDatabase;

  /**
   * Cached channel types keyed by channel content ID.
   *
   * @var array<string, string>
   */
  private array $channelTypesById = [];

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select(self::TABLE, 'service')
      ->fields('service');

    if ($this->ptvContentIds !== []) {
      $query->condition('service.uuid', $this->ptvContentIds, 'IN');
    }

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'uuid' => '(string) The UUID.',
      'name' => '(string) The name of the service.',
      'alternative_name' => '(string, optional) The alternative name of the service.',
      'description' => '(string, optional) The main description.',
      'summary' => '(string, optional) The summary.',
      'user_instruction' => '(string, optional) The user instruction text.',
      'requirements' => '(string, optional) The requirements for the service.',
      'chargeability' => '(string, optional) Whether the service is free of charge (FreeOfCharge) or not (Chargeable).',
      'chargeability_info' => '(string, optional) Textual additional information pertaining to chargeability.',
      'service_vouchers_in_use' => '(bool) Whether the service has service voucher in use.',
      'service_voucher_links' => '(array, optional) Structured arrays of service voucher links.',
      'languages' => '(array, optional) Array of strings (language codes) for the languages the service is available in.',
      'service_producer' => '(string, optional) The name(s) of the producer(s) for the service.',
      'service_responsible' => '(string, optional) The name(s) of the organization(s) responsible for the service.',
      'service_other_responsible' => '(string, optional) The name(s) of the other responsible organization(s) for the service.',
      'areas_text' => '(string, formatted text) Formatted text requiring <ul> and <h3> element support from the text format, listing areas for the service.',
      'life_situations' => '(referenceArray, optional) The target groups and life events of the service as IDs.',
      'keywords' => '(referenceArray, optional) The ontology terms of the service as IDs.',
      'topics' => '(referenceArray, optional) The service classes and life events of the service as IDs.',
      'service_locations' => '(referenceArray, optional) The location service channels of the service as IDs.',
      'eservice_channels' => '(referenceArray, optional) The e-service channels of the service as IDs.',
      'phone_service_channels' => '(referenceArray, optional) The phone service channels of the service as IDs.',
      'web_page_service_channels' => '(referenceArray, optional) The web page service channels of the service as IDs.',
      'form_service_channels' => '(referenceArray, optional) The printable form service channels of the service as IDs.',
    ];
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
    $storedData = $row->getSourceProperty('data');

    if (!is_string($storedData)) {
      return FALSE;
    }

    $record = PtvStorageRecordDecoder::decode($storedData);

    $service = ObjectSerializer::deserialize(
      $record['payload'],
      ServiceResponse::class,
      []
    );

    if (!$service instanceof ServiceResponse) {
      throw new \RuntimeException(
        'PTV service payload did not deserialize into a ServiceResponse DTO.'
      );
    }

    $rawAreas = $record['areas'] ?? [];
    $areasText = $this->formatAreasText(
      $rawAreas,
      $this->contentLanguage
    );

    $values = $this->serviceSourceMapper->map(
      $service,
      $record['connections'],
      $this->contentLanguage,
      $this->getChannelTypesById($record['connections']),
      $areasText
    );

    if ($values === []) {
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
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $pluginId,
    $pluginDefinition,
    ?MigrationInterface $migration = NULL,
  ) {
    $instance = parent::create(
      $container,
      $configuration,
      $pluginId,
      $pluginDefinition,
      $migration
    );

    $language = $configuration['language'] ?? NULL;

    if (!is_string($language) || trim($language) === '') {
      throw new \InvalidArgumentException(
        'The PTV services source plugin requires a non-empty language configuration.'
      );
    }

    $instance->contentLanguage = trim($language);
    $instance->ptvContentIds = $configuration['ptv_content_ids'] ?? [];
    $instance->serviceSourceMapper = $container->get(
      'tre_ptv_import.service_source_mapper'
    );
    $instance->storageDatabase = $container->get('database');

    return $instance;
  }

  /**
   * Gets channel types required by one service's connection records.
   *
   * @param array<int, array<string, mixed>> $connections
   *   The decoded connection records.
   *
   * @return array<string, string>
   *   Channel types keyed by content ID.
   */
  private function getChannelTypesById(array $connections): array {
    $channelIds = PtvStorageRecordDecoder::getChannelIdsFromConnections(
      $connections
    );

    $missingChannelIds = array_values(
      array_diff($channelIds, array_keys($this->channelTypesById))
    );

    if ($missingChannelIds !== []) {
      $query = $this->storageDatabase
        ->select('tre_ptv_import_channel', 'channel')
        ->fields('channel', ['uuid', 'type'])
        ->condition('channel.uuid', $missingChannelIds, 'IN');

      $result = $query->execute()->fetchAll();

      foreach ($result as $channel) {
        if (
          !is_string($channel->uuid)
          || trim($channel->uuid) === ''
          || !is_string($channel->type)
          || trim($channel->type) === ''
        ) {
          continue;
        }

        $this->channelTypesById[trim($channel->uuid)] = trim(
          $channel->type
        );
      }
    }

    $channelTypes = [];

    foreach ($channelIds as $channelId) {
      if (isset($this->channelTypesById[$channelId])) {
        $channelTypes[$channelId] = $this->channelTypesById[$channelId];
      }
    }

    return $channelTypes;
  }

  /**
   * Formats localized area names into a comma-separated string.
   *
   * @param array<int, array{code: string, name: array<string, string|null>}> $areas
   *   The area records from intermediate storage.
   * @param string $language
   *   The target content language.
   *
   * @return string|null
   *   Comma-separated area names, or NULL when no areas exist.
   */
  private function formatAreasText(array $areas, string $language): ?string {
    if ($areas === []) {
      return NULL;
    }

    $names = [];

    foreach ($areas as $area) {
      $nameMap = $area['name'] ?? [];
      $name = $nameMap[$language] ?? $nameMap['fi'] ?? NULL;

      if (is_string($name) && trim($name) !== '') {
        $trimmedName = trim($name);
        $names[$trimmedName] = $trimmedName;
      }
    }

    if ($names === []) {
      return NULL;
    }

    sort($names, SORT_LOCALE_STRING);

    return implode(', ', $names);
  }
}