<?php

namespace Drupal\tre_ptv_import\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Drupal\tre_ptv_import\Service\Api\PtvChannelResponseDeserializer;
use Drupal\tre_ptv_import\Service\Migration\PtvChannelSourceMapper;
use Drupal\tre_ptv_import\Service\Storage\PtvStorageRecordDecoder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Source plugin for PTV service channels.
 *
 * @MigrateSource(
 *   id = "ptv_service_channels"
 * )
 */
class PtvServiceChannels extends SqlBase {

  /**
   * The intermediate storage table.
   */
  private const TABLE = 'tre_ptv_import_channel';

  /**
   * The channel type handled by the location source plugin.
   */
  private const SERVICE_LOCATION_TYPE = 'ServiceLocation';

  /**
   * The requested content language.
   */
  public string $contentLanguage;

  /**
   * The requested PTV content IDs.
   *
   * An empty array means that the migration imports all matching rows.
   *
   * @var string[]
   */
  private array $ptvContentIds = [];

  /**
   * The service channel source mapper.
   */
  public PtvChannelSourceMapper $channelSourceMapper;

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select(self::TABLE, 'channel')
      ->fields('channel');

    $query->condition(
      'channel.type',
      self::SERVICE_LOCATION_TYPE,
      '<>'
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
      'name' => '(string) The name of the service.',
      'description' => '(string, optional) The main description.',
      'summary' => '(string, optional) The summary.',
      'service_channel_type' => '(string) The type of the service channel.',
      'web_pages' => '(array, optional) Structured arrays of web site data.',
      'support_phones' => '(array, optional) Structured arrays of support phone number data.',
      'support_emails' => '(array, optional) Structured arrays of support email data.',
      'phones' => '(array, optional) Structured arrays of phone number data.',
      'attachments' => '(array, optional) Structured arrays of attachment and additional link data.',
      'accessibility' => '(array, optional) Structured arrays of accessibility information link data.',
      'electronic_signature_required' => '(bool) Whether the service channel requires an electronic signature.',
      'electronic_id_required' => '(bool) Whether the service channel requires electronic identification.',
      'postal_address' => '(array, optional) Postal address for form channels.',
      'delivery_details' => '(string, optional) The free-form delivery details.',
      'form_receiver' => '(string, optional) The name of the receiver for the form channel.',
      'forms' => '(array, optional) Structured arrays of links to forms.',
      'languages' => '(array, optional) Array of language codes.',
      'areas_text' => '(string, formatted text) Area information.',
      'organization' => '(string, optional) The name of the organization.',
      'regular_daily_hours_hours' => '(array, optional) The regular daily opening hours.',
      'regular_overnight_hours_hours' => '(array, optional) The regular overnight opening hours.',
      'exception_hours_info' => '(string, optional) The exception hours.',
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
        'alias' => 'service_channel',
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

    $channel = PtvChannelResponseDeserializer::deserialize(
      $record['payload']
    );

    $areasText = $this->formatAreasText(
      $record['areas'] ?? [],
      $this->contentLanguage
    );

    $values = $this->channelSourceMapper->map(
      $channel,
      $this->contentLanguage,
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
    $instance->channelSourceMapper = $container->get(
      'tre_ptv_import.channel_source_mapper'
    );

    return $instance;
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