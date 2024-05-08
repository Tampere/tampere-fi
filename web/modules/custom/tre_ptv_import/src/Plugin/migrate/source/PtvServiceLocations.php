<?php

namespace Drupal\tre_ptv_import\Plugin\migrate\source;

use Drupal\Component\Serialization\SerializationInterface;
use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Drupal\tre_node_location_coordinate_conversion\Service\PointToRegionName;
use Drupal\tre_ptv_import\PtvDataHelpersInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Tampere\PtvV11\PtvModel\V11VmOpenApiServiceHour;
use Tampere\PtvV11\PtvModel\V11VmOpenApiServiceLocationChannel;

/**
 * Plugin class for source plugin for PTV Service locations.
 *
 * @MigrateSource(
 *   id = "ptv_service_locations"
 * )
 */
class PtvServiceLocations extends SqlBase {

  /**
   * The database table to use in the source data query.
   */
  const TABLE = 'tre_ptv_import_channel';

  /**
   * The class name that represents the service locations amongst all channels.
   */
  const SERVICE_LOCATION_TYPE = 'V11VmOpenApiServiceLocationChannel';

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
   * The tre_node_location_coordinate_conversion.point_to_region_name service.
   *
   * @var \Drupal\tre_node_location_coordinate_conversion\Service\PointToRegionName
   */
  public PointToRegionName $pointToRegionNameConverter;

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select(static::TABLE, 'channel')
      ->fields('channel');

    $query->where('channel.type = :type', [':type' => static::SERVICE_LOCATION_TYPE]);

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = [
      'uuid' => '(string) The UUID.',
      'name' => '(string) The name of the location.',
      'additional_name' => '(string, optional) The additional name.',
      'street_addresses' => '(array, optional) Street addresses.',
      'postal_address' => '(array, optional) Postal addresses. For now only 1.',
      'description' => '(string, optional) The location description.',
      'summary' => '(string, optional) The location summary.',
      'regular_hours_hours' => '(array, optional) The regular opening hours.',
      'regular_hours_info' => '(array, optional) The regular opening hours notes.',
      'exception_hours_info' => '(string, optional) The exception hours.',
      'phones' => '(array, optional) Structured arrays of phone number data.',
      'web_pages' => '(array, optional) Structured arrays of web site data.',
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
        'alias' => 'channel',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    /** @var \Tampere\PtvV11\PtvModel\V11VmOpenApiServiceLocationChannel $service_location */
    $service_location = $this->serialization::decode($row->getSourceProperty('data'));

    if (!($service_location instanceof V11VmOpenApiServiceLocationChannel)) {
      return FALSE;
    }

    $values = self::mangleServiceLocationIntoSourceValues(
      $service_location,
      $this->contentLanguage,
      $this->dataHelpers,
      $this->pointToRegionNameConverter
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
    $instance->pointToRegionNameConverter = $container->get('tre_node_location_coordinate_conversion.point_to_region_name');

    return $instance;
  }

  /**
   * Transforms V11VmOpenApiServiceLocationChannel objects into source row data.
   *
   * @param \Tampere\PtvV11\PtvModel\V11VmOpenApiServiceLocationChannel $service_location
   *   The V11VmOpenApiServiceLocationChannel object to transform.
   * @param string $language
   *   The language to use in requesting the data.
   * @param \Drupal\tre_ptv_import\PtvDataHelpersInterface $data_helpers
   *   The PtvDataHelpers service to use.
   * @param \Drupal\tre_node_location_coordinate_conversion\Service\PointToRegionName $point_to_region_converter
   *   The PointToRegionName service to use.
   *
   * @return array
   *   Associative array keyed by source field names, or an empty array to
   *   enable skipping the import for this particular row.
   */
  public static function mangleServiceLocationIntoSourceValues(V11VmOpenApiServiceLocationChannel $service_location, string $language, PtvDataHelpersInterface $data_helpers, PointToRegionName $point_to_region_converter): array {
    $values = [
      'uuid' => $service_location->getId(),
    ];

    $values['name'] = $data_helpers::getDescriptionStringByLanguageAndType($service_location->getServiceChannelNames(), $language, 'Name');
    $values['additional_name'] = $data_helpers::getDescriptionStringByLanguageAndType($service_location->getServiceChannelNames(), $language, 'AdditionalName');

    if (empty($values['name'])) {
      return [];
    }

    $address_types_with_processors = PtvDataHelpersInterface::ADDRESS_TYPE_PROCESSORS;

    $addresses_by_type = [];

    foreach (array_keys($address_types_with_processors) as $address_type) {
      $addresses_by_type[$address_type] = array_filter($service_location->getAddresses(), function ($addr) use ($address_type) {
        return $addr->getType() . '.' . $addr->getSubType() == $address_type;
      });
    }

    $geographical_areas = [];
    foreach ($addresses_by_type as $address_type => $addresses) {
      $values_key = $address_types_with_processors[$address_type]['value type'];
      if (!isset($values[$values_key])) {
        $values[$values_key] = [];
      }
      $processor_function = [
        $data_helpers,
        $address_types_with_processors[$address_type]['processor'],
      ];
      $processor_additional_data = [
        'location_name' => $values['name'],
        'language' => $language,
        'data_helpers' => $data_helpers,
        'point_to_address_converter' => $point_to_region_converter,
      ];
      array_walk($addresses, $processor_function, $processor_additional_data);
      foreach ($addresses as $key => $address) {
        if (!isset($address['region'])) {
          continue;
        }
        if (!empty($address['region'])) {
          $geographical_areas[$address['region']] = ['name' => $address['region']];
        }
        unset($addresses[$key]['region']);
      }
      $values[$values_key] = array_merge($values[$values_key], $addresses);
    }
    $values['geographical_areas'] = array_values($geographical_areas);

    $type = 'Description';
    $values['description'] = $data_helpers::getDescriptionStringByLanguageAndType($service_location->getServiceChannelDescriptions(), $language, $type);
    $type = 'Summary';
    $values['summary'] = $data_helpers::getDescriptionStringByLanguageAndType($service_location->getServiceChannelDescriptions(), $language, $type);

    $opening_hours_types_with_processors = [
      'DaysOfTheWeek' => [
        'processor' => 'processRegularOpeningHours',
        'value type' => 'regular_hours',
      ],
      'Exceptional' => [
        'processor' => 'processExceptionalOpeningHours',
        'value type' => 'exception_hours',
      ],
    ];

    $service_hours = $service_location->getServiceHours();
    $data_helpers->processServiceHours($values, $opening_hours_types_with_processors, $service_hours, $language);

    $phone_numbers = $service_location->getPhoneNumbers();
    $values['phones'] = $data_helpers::processPhoneNumbers($phone_numbers, $language);

    $values['web_pages'] = [];
    $web_page_data_in_language = array_filter($service_location->getWebPages(), function ($web_page) use ($language) {
      return $web_page->getLanguage() == $language;
    });
    foreach ($web_page_data_in_language as $web_page) {
      $values['web_pages'][] = [
        'title' => $web_page->getValue(),
        'uri' => $web_page->getUrl(),
      ];
    }

    $values['emails'] = [];
    $email_items = $service_location->getEmails();
    if (!empty($email_items)) {
      $email_data_in_language = array_filter($email_items, function ($email) use ($language) {
        return $email->getLanguage() == $language;
      });
    }
    else {
      $email_data_in_language = [];
    }
    foreach ($email_data_in_language as $email) {
      $values['emails'][] = $email->getValue();
    }

    return $values;
  }

  /**
   * Checks if an opening hour entry is valid at the given comparison moment.
   *
   * @param \Tampere\PtvV11\PtvModel\V11VmOpenApiServiceHour $hours_entry
   *   The opening hour entry to check.
   * @param \DateTimeInterface $comparison
   *   The moment to check against.
   *
   * @return bool
   *   True if the entry is valid at the given moment, false otherwise.
   */
  private static function isServiceHourEntryValidAtTime(V11VmOpenApiServiceHour $hours_entry, \DateTimeInterface $comparison) {
    if ($hours_entry->getValidFrom() instanceof \DateTimeInterface && empty($hours_entry->getValidTo())) {
      $start_interval = $hours_entry->getValidFrom()->diff($comparison);
      // DateInterval formats (%R) to '+' when the validFrom date is before the
      // comparison date.
      return $start_interval->format('%R') == '+';
    }

    if (empty($hours_entry->getValidFrom()) && $hours_entry->getValidTo() instanceof \DateTimeInterface) {
      $end_interval = $hours_entry->getValidTo()->diff($comparison);
      // DateInterval formats (%R) to '-' when the validTo date is after the
      // comparison date.
      return $end_interval->format('%R') == '-';
    }

    if ($hours_entry->getValidFrom() instanceof \DateTimeInterface && $hours_entry->getValidTo() instanceof \DateTimeInterface) {
      $start_interval = $hours_entry->getValidFrom()->diff($comparison);
      $end_interval = $hours_entry->getValidTo()->diff($comparison);
      return $start_interval->format('%R') == '+' && $end_interval->format('%R') == '-';
    }

    // Reaching this point means that both validFrom and validTo are empty.
    // @todo Decide whether the entry is valid when validFrom and validTo are
    // empty.
    return TRUE;
  }

}
