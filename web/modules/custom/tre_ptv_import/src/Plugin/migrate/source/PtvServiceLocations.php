<?php

declare(strict_types=1);

namespace Drupal\tre_ptv_import\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Drupal\tre_ptv_import\Service\Api\PtvChannelResponseDeserializer;
use Drupal\tre_ptv_import\Service\Migration\PtvServiceLocationSourceMapper;
use Drupal\tre_ptv_import\Service\Storage\PtvStorageRecordDecoder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Source plugin for PTV service locations.
 *
 * @MigrateSource(
 *   id = "ptv_service_locations"
 * )
 */
final class PtvServiceLocations extends SqlBase {

  /**
   * The intermediate storage table.
   */
  private const TABLE = 'tre_ptv_import_channel';

  /**
   * The channel type handled by this source plugin.
   */
  private const SERVICE_LOCATION_TYPE = 'ServiceLocation';

  /**
   * The requested content language.
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
   * The service location source mapper.
   */
  private PtvServiceLocationSourceMapper $serviceLocationSourceMapper;

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select(self::TABLE, 'channel')
      ->fields('channel');

    $query->condition(
      'channel.type',
      self::SERVICE_LOCATION_TYPE
    );

    if ($this->ptvContentIds !== []) {
      $query->condition('channel.uuid', $this->ptvContentIds, 'IN');
    }

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'uuid' => '(string) The UUID.',
      'name' => '(string) The name of the location.',
      'additional_name' => '(string, optional) The additional name.',
      'street_addresses' => '(array, optional) Street addresses.',
      'postal_address' => '(array, optional) Postal addresses.',
      'description' => '(string, optional) The location description.',
      'summary' => '(string, optional) The location summary.',
      'regular_hours_hours' => '(array, optional) The regular opening hours.',
      'regular_hours_info' => '(array, optional) The regular opening hours notes.',
      'exception_hours_info' => '(string, optional) The exception hours.',
      'phones' => '(array, optional) Structured arrays of phone number data.',
      'web_pages' => '(array, optional) Structured arrays of web site data.',
      'emails' => '(array, optional) Email address values.',
      'geographical_areas' => '(array, optional) Geographical area term names.',
      'epsg_points' => '(array, optional) EPSG:3067 easting and northing strings.',
      'place_area_text' => '(string, optional) Municipality area names.',
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
        'alias' => 'channel',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $record = PtvStorageRecordDecoder::decode(
      (string) $row->getSourceProperty('data')
    );
    // Resolve the polymorphic channel payload into the correct generated DTO.
    $location = PtvChannelResponseDeserializer::deserialize(
      $record['payload']
    );

    $values = $this->serviceLocationSourceMapper->map(
      $location,
      $this->contentLanguage,
      $record['accessibility'],
      $record['areas'] ?? [],
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
    MigrationInterface $migration = NULL,
  ) {
    $instance = parent::create(
      $container,
      $configuration,
      $pluginId,
      $pluginDefinition,
      $migration
    );

    $instance->contentLanguage = $configuration['language'];
    $instance->ptvContentIds = $configuration['ptv_content_ids'] ?? [];
    $instance->serviceLocationSourceMapper = $container->get(
      'tre_ptv_import.service_location_source_mapper'
    );

    return $instance;
  }

}