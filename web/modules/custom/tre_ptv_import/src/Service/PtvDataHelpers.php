<?php

declare(strict_types=1);

namespace Drupal\tre_ptv_import\Service;

use Drupal\Component\Datetime\DateTimePlus;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\node\NodeInterface;
use Drupal\tre_ptv_import\PtvDataHelpersInterface;
use Tampere\PtvV11\PtvApi\OrganizationApi;
use Tampere\PtvV11\PtvModel\V11VmOpenApiServiceChannels;
use Tampere\PtvV11\PtvModel\V11VmOpenApiServiceHour;
use Tampere\PtvV11\PtvModel\V11VmOpenApiServiceLocationChannel;
use Tampere\PtvV11\PtvModel\V4VmOpenApiPhoneWithType;
use Tampere\PtvV11\PtvModel\V9VmOpenApiAddressLocation;
use Tampere\PtvV11\PtvModel\VmOpenApiAttachment;
use Tampere\PtvV11\PtvModel\VmOpenApiAttachmentWithType;

/**
 * Data helper functions for dealing with the PTV API data.
 */
class PtvDataHelpers implements PtvDataHelpersInterface {

  /**
   * {@inheritDoc}
   */
  public static function findOpeningHourEntryByWeekday(string $weekday, array $opening_hours_entries) {
    $entries_starting_on_weekday = array_filter($opening_hours_entries, function ($entry) use ($weekday) {
      return $entry->getDayFrom() == $weekday;
    });

    if (count($entries_starting_on_weekday) > 0) {
      return array_keys($entries_starting_on_weekday);
    }

    return FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public static function formatExceptionHourDatePart(V11VmOpenApiServiceHour $exception_hour) {
    $date_value = '';

    if (!is_null($exception_hour->getValidFrom())) {
      $start_date_value = $exception_hour->getValidFrom()->format('j.n.Y');
      $sortable_date = (int) $exception_hour->getValidFrom()->format('Ymd');
    }
    else {
      $start_date_value = '';
      // Assign a negative value in order to place the exception hour without
      // a starting date at the top of the list.
      $sortable_date = -1;
    }

    if (!is_null($exception_hour->getValidTo())) {
      $end_date_value = $exception_hour->getValidTo()->format('j.n.Y');
    }
    else {
      $end_date_value = '';
    }

    if (!empty($start_date_value) && !empty($end_date_value)) {
      $date_value = sprintf('%s—%s', $start_date_value, $end_date_value);
    }
    elseif (!empty($start_date_value)) {
      $date_value = $start_date_value;
    }

    if (!empty($exception_hour->getOpeningHour())) {
      $date_value = sprintf('%s %s', $date_value, self::formatOpeningHours($exception_hour->getOpeningHour()));
    }

    return [$date_value, $sortable_date];
  }

  /**
   * {@inheritDoc}
   */
  public static function formatOpeningHours(array $opening_hours) {
    $hour_strings = [];
    $print_start_weekday_per_line = count($opening_hours) > 1;
    foreach ($opening_hours as $opening_time) {
      $start = empty($opening_time->getFrom()) ? '' : self::formatOpeningHourTime($opening_time->getFrom());
      $end = empty($opening_time->getTo()) ? '' : self::formatOpeningHourTime($opening_time->getTo());
      $start_day = $opening_time->getDayFrom();
      $end_day = $opening_time->getDayTo();

      if (!empty($end)) {
        $time_interval = sprintf('%s–%s', $start, $end);
      }
      else {
        $time_interval = sprintf('%s', $start);
      }

      if (empty($end_day)) {
        if ($print_start_weekday_per_line) {
          // Note that the $start_day in the API is in English: Monday etc.
          $hour_strings[] = sprintf('%s %s', $start_day, $time_interval);
        }
        else {
          $hour_strings[] = $time_interval;
        }
      }
      else {
        // Note that the $start_day and the $end_day in the API are in English:
        // Monday etc.
        $hour_strings[] = sprintf('%s–%s %s', $start_day, $end_day, $time_interval);
      }
    }

    return implode(', ', $hour_strings);
  }

  /**
   * {@inheritDoc}
   */
  public static function formatOpeningHourTime(string $time_entry) {
    $date = new DateTimePlus('2022-01-01T' . $time_entry, 'Z');
    return $date->format('G.i', ['timezone' => 'Z']);
  }

  /**
   * {@inheritdoc}
   */
  public static function getDescriptionStringByLanguageAndType(array $descriptions_raw, string $language, string $type): string {
    $description_values = array_filter($descriptions_raw, function ($description) use ($language, $type) {
      return $description->getLanguage() == $language && $description->getType() == $type;
    });

    $descriptions = [];
    foreach ($description_values as $description) {
      $descriptions[] = $description->getValue();
    }

    return implode("\n\n", $descriptions);
  }

  /**
   * {@inheritdoc}
   */
  public static function getOpenApiLanguageItemStringsByLanguage(array $localized_items, string $language): array {
    $values_in_language = array_filter($localized_items, function ($item) use ($language) {
      return $item->getLanguage() == $language;
    });

    return $values_in_language;
  }

  /**
   * {@inheritDoc}
   */
  public static function getOrganizationNameByLanguage(
    string $organization_id,
    string $language,
    OrganizationApi $organization_api_connection,
    CacheBackendInterface $cache,
    TimeInterface $timeService,
  ): string {
    $organization_name_cid = 'tre_ptv_import_organization_name__' . $organization_id;
    $cached = $cache->get($organization_name_cid);

    $organization_names = [];
    if (isset($cached->data)) {
      $organization_names = $cached->data;
    }
    else {
      /** @var \Tampere\PtvV11\PtvModel\V10VmOpenApiOrganization $organization */
      $organization = $organization_api_connection->apiV11OrganizationIdGet($organization_id);
      $organization_names = $organization->getOrganizationNames();

      $current_time = $timeService->getCurrentTime();

      // Set to expire after 15 minutes.
      $cache_expiry_time = $current_time + 900;
      $cache->set($organization_name_cid, $organization_names, $cache_expiry_time);
    }

    $organization_names_of_type_name = array_filter($organization_names, function ($item) {
      return $item->getType() === 'Name';
    });

    $organization_names_by_language = array_map(function ($item) {
      return $item->getValue();
    }, self::getOpenApiLanguageItemStringsByLanguage($organization_names_of_type_name, $language));

    // Not all organizations have translations. If there is no translation
    // for the organization name, use whatever name is available.
    $no_translations = empty($organization_names_by_language);
    if ($no_translations) {
      return reset($organization_names)->getValue();
    }
    else {
      return reset($organization_names_by_language);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function separateLocationsFromOtherServiceChannels(array $allChannels): array {
    $location_class = V11VmOpenApiServiceLocationChannel::class;
    $locations = array_filter($allChannels, function ($item) use ($location_class) {
      return $item instanceof $location_class;
    });

    $locations_by_uuid = [];
    /** @var \Tampere\PtvV11\PtvModel\V11VmOpenApiServiceLocationChannel $location */
    foreach ($locations as $location) {
      $locations_by_uuid[$location->getId()] = $location;
    }

    $non_locations = array_filter($allChannels, function ($item) use ($location_class) {
      return !($item instanceof $location_class);
    });

    $non_locations_by_uuid = [];
    /** @var \Tampere\PtvV11\PtvModel\V11VmOpenApiWebPageChannel|\Tampere\PtvV11\PtvModel\V11VmOpenApiElectronicChannel|\Tampere\PtvV11\PtvModel\V11VmOpenApiPrintableFormChannel|\Tampere\PtvV11\PtvModel\V11VmOpenApiPhoneChannel $non_location */
    foreach ($non_locations as $non_location) {
      $non_locations_by_uuid[$non_location->getId()] = $non_location;
    }

    return [$locations_by_uuid, $non_locations_by_uuid];
  }

  /**
   * {@inheritdoc}
   */
  public static function getServiceChannelObjectFromServiceChannelsObject(V11VmOpenApiServiceChannels $apiServiceChannelsCollection) {
    $object_candidates = [
      $apiServiceChannelsCollection->getElectronicChannel(),
      $apiServiceChannelsCollection->getLocationChannel(),
      $apiServiceChannelsCollection->getPhoneChannel(),
      $apiServiceChannelsCollection->getPrintableFormChannel(),
      $apiServiceChannelsCollection->getWebPageChannel(),
    ];

    $actual_objects = array_filter($object_candidates);

    if (count($actual_objects) !== 1) {
      throw new \InvalidArgumentException("Unsupported V11VmOpenApiServiceChannels object with multiple or 0 actual channels.");
    }

    return current($actual_objects);
  }

  /**
   * {@inheritDoc}
   */
  public static function processAreas(array $areas, string $language): ?string {
    $result_strings = [];

    $areas_per_type = [];
    foreach ($areas as $area) {
      $areas_per_type[$area->getType()][] = $area;
    }

    $municipality_name_list = [];
    if (isset($areas_per_type['Municipality'])) {
      /** @var \Tampere\PtvV11\PtvModel\VmOpenApiArea $area */
      foreach ($areas_per_type['Municipality'] as $area) {
        foreach ($area->getMunicipalities() as $municipality) {
          $names = array_map(function ($item) {
            return $item->getValue();
          }, self::getOpenApiLanguageItemStringsByLanguage($municipality->getName(), $language));
          foreach ($names as $name) {
            $municipality_name_list[] = $name;
          }
        }
      }

      if (!empty($municipality_name_list)) {
        $result_strings[] = sprintf('<p>%s</p>', implode(", ", $municipality_name_list));
      }
    }

    return empty($result_strings) ? NULL : implode("\n", $result_strings);
  }

  /**
   * {@inheritDoc}
   */
  public static function processEmails(array $emails, string $language): ?array {
    $email_list = [];

    if (!empty($emails)) {
      $email_data_in_language = array_filter($emails, function ($email) use ($language) {
        return $email->getLanguage() == $language;
      });
    }
    else {
      $email_data_in_language = [];
    }

    foreach ($email_data_in_language as $email) {
      $email_list[] = $email->getValue();
    }

    return empty($email_list) ? NULL : $email_list;
  }

  /**
   * {@inheritDoc}
   */
  public static function processExceptionalOpeningHours(V11VmOpenApiServiceHour &$hours_entry, $key, array $additional_data) {
    $language = $additional_data['language'];

    $closed_labels = [
      'fi' => 'suljettu',
      'en' => 'closed',
    ];

    // Default to Finnish label for unknown languages.
    $closed_label = $closed_labels[$language] ?? 'suljettu';
    $is_closed = $hours_entry->getIsClosed() ? $closed_label : '';

    $is_invalid = empty($hours_entry->getAdditionalInformation()) && empty($hours_entry->getValidFrom()) && empty($hours_entry->getValidForNow()) && empty($hours_entry->getOpeningHour());

    if ($is_invalid) {
      $hours_entry = [];
      return;
    }

    [$date_value, $sortable_date] = self::formatExceptionHourDatePart($hours_entry);
    $texts = self::getOpenApiLanguageItemStringsByLanguage($hours_entry->getAdditionalInformation(), $language);
    $text = implode(', ', array_map(function ($value) {
      return $value->getValue();
    }, $texts));

    $hours_entry = [
      'info' => trim(sprintf('%s %s %s', $text, $date_value, $is_closed)),
      'sortable_date' => $sortable_date,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function processLocationSingle(V9VmOpenApiAddressLocation &$address, $key, array $additional_data) {
    $coordinates = self::getCoordinatesForAddressLocation($address);
    if (is_array($coordinates)) {
      /** @var \Drupal\tre_node_location_coordinate_conversion\Service\PointToRegionName $converter */
      $converter = $additional_data['point_to_address_converter'];
      $region_name = $converter->convertPointToName($coordinates['E'], $coordinates['N']);
      $coordinate_string = self::formatCoordinatesAsString($coordinates);
    }
    else {
      $region_name = '';
      $coordinate_string = '(ei koordinaatteja)';
    }
    $address_item = $address->getStreetAddress();

    $street_name = current(self::getOpenApiLanguageItemStringsByLanguage($address_item->getStreet(),
        $additional_data['language']))->getValue();

    $locality_data = is_null($address_item->getMunicipality()) ? $address_item->getPostOffice() : $address_item->getMunicipality()
      ->getName();
    $locality_name = current(self::getOpenApiLanguageItemStringsByLanguage($locality_data, $additional_data['language']))->getValue();

    $description_data = self::getOpenApiLanguageItemStringsByLanguage($address_item->getAdditionalInformation(), $additional_data['language']);

    $entrances = $address->getEntrances();
    $accessibility_information = [];

    foreach ($entrances as $entrance) {
      $accessibility_sentences = $entrance->getAccessibilitySentences();
      foreach ($accessibility_sentences as $accessibility_sentence) {
        $title = self::getOpenApiLanguageItemStringsByLanguage($accessibility_sentence->getSentenceGroup(), $additional_data['language']);
        $sentences = [];
        foreach ($accessibility_sentence->getSentences() as $sentence_group) {
          $sentence = self::getOpenApiLanguageItemStringsByLanguage($sentence_group->getSentence(), $additional_data['language']);
          if (!empty($sentence)) {
            $sentences[] = current($sentence)->getValue();
          }
        }
        $accessibility_information[] = [
          'title' => current($title)->getValue(),
          'sentences' => $sentences,
        ];
      }
    }

    // The country, thouroughfare, postal_code, and locality member names in the
    // array are inspired by the 'addressfield' MigrateProcessPlugin.
    $address = [
      'address_label' => $additional_data['location_name'] . ' - ' . $coordinate_string,
      'description' => empty($description_data) ? '' : current($description_data)->getValue(),
      'northing' => $coordinates['N'] ?? NULL,
      'easting' => $coordinates['E'] ?? NULL,
      'country' => 'FI',
      'thoroughfare' => $street_name . ' ' . $address_item->getStreetNumber(),
      'postal_code' => $address_item->getPostalCode(),
      'locality' => $locality_name,
      'region' => $region_name,
    ] + static::ADDRESS_FIELD_EMPTY_SOURCE;

    $address_hash = hash('sha512', serialize($address));
    $address['address_hash'] = $address_hash;
    $address['accessibility_information'] = json_encode($accessibility_information);

    $existing = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties([
        'type' => 'map_point',
        'field_address_hash' => $address_hash,
      ]);

    // If there is a map point node with the hash
    // and if there is accessibility information available,
    // update the node because the migration with the current strucutre
    // doesn't update the exsiting map point nodes.
    if ((reset($existing) instanceof NodeInterface) && !empty($accessibility_information)) {
      $node = reset($existing);
      if ($node->hasTranslation($additional_data['language'])) {
        $translated_node = $node->getTranslation($additional_data['language']);
        $translated_node->set('field_access_info_sentences_json', json_encode($accessibility_information));
        $translated_node->save();
      }
    }

  }

  /**
   * {@inheritdoc}
   */
  public static function processLocationOther(V9VmOpenApiAddressLocation &$address, $key, array $additional_data) {
    $coordinates = self::getCoordinatesForAddressLocation($address);
    if (is_array($coordinates)) {
      /** @var \Drupal\tre_node_location_coordinate_conversion\Service\PointToRegionName $converter */
      $converter = $additional_data['point_to_address_converter'];
      $region_name = $converter->convertPointToName($coordinates['E'], $coordinates['N']);
      $coordinate_string = self::formatCoordinatesAsString($coordinates);
    }
    else {
      $region_name = '';
      $coordinate_string = '(ei koordinaatteja)';
    }
    $address_item = $address->getOtherAddress();

    $street_name = current(self::getOpenApiLanguageItemStringsByLanguage($address_item->getAdditionalInformation(), $additional_data['language']))->getValue();

    $locality_data = is_null($address_item->getMunicipality()) ? $address_item->getPostOffice() : $address_item->getMunicipality()
      ->getName();
    $locality_name = current(self::getOpenApiLanguageItemStringsByLanguage($locality_data, $additional_data['language']))->getValue();

    // The country, thouroughfare, postal_code, and locality member names in the
    // array are inspired by the 'addressfield' MigrateProcessPlugin.
    $address = [
      'address_label' => $additional_data['location_name'] . ' - ' . $coordinate_string,
      'description' => '',
      'northing' => $coordinates['N'] ?? NULL,
      'easting' => $coordinates['E'] ?? NULL,
      'country' => 'FI',
      'thoroughfare' => $street_name,
      'postal_code' => $address_item->getPostalCode(),
      'locality' => $locality_name,
      'region' => $region_name,
    ] + static::ADDRESS_FIELD_EMPTY_SOURCE;
  }

  /**
   * {@inheritdoc}
   */
  public static function processLocationPoBox(&$address, $key, array $additional_data) {
    $address_item = $address->getPostOfficeBoxAddress();

    $street_name = current(self::getOpenApiLanguageItemStringsByLanguage($address_item->getPostOfficeBox(), $additional_data['language']))->getValue();

    $locality_data = $address_item->getPostOffice();
    $locality_name = current(self::getOpenApiLanguageItemStringsByLanguage($locality_data, $additional_data['language']))->getValue();

    // The country, thouroughfare, postal_code, and locality member names in the
    // array are inspired by the 'addressfield' MigrateProcessPlugin.
    $address = [
      'country' => 'FI',
      'thoroughfare' => $street_name,
      'postal_code' => $address_item->getPostalCode(),
      'locality' => mb_convert_case($locality_name, MB_CASE_TITLE),
    ] + static::ADDRESS_FIELD_EMPTY_SOURCE;
  }

  /**
   * {@inheritdoc}
   */
  public static function processLocationStreetPostal(&$address, $key, array $additional_data) {
    $address_item = $address->getStreetAddress();

    $street_name = current(self::getOpenApiLanguageItemStringsByLanguage($address_item->getStreet(), $additional_data['language']))->getValue();

    $locality_data = $address_item->getPostOffice();
    $locality_name = current(self::getOpenApiLanguageItemStringsByLanguage($locality_data, $additional_data['language']))->getValue();

    $additional_information_strings = self::getOpenApiLanguageItemStringsByLanguage($address_item->getAdditionalInformation(), $additional_data['language']);

    $thoroughfare_data = [
      $street_name,
      $address_item->getStreetNumber(),
      empty($additional_information_strings) ? '' : current($additional_information_strings)->getValue(),
    ];

    // The country, thouroughfare, postal_code, and locality member names in the
    // array are inspired by the 'addressfield' MigrateProcessPlugin.
    $address = [
      'country' => 'FI',
      'thoroughfare' => implode(' ', array_filter($thoroughfare_data)),
      'postal_code' => $address_item->getPostalCode(),
      'locality' => mb_convert_case($locality_name, MB_CASE_TITLE),
    ] + static::ADDRESS_FIELD_EMPTY_SOURCE;
  }

  /**
   * {@inheritDoc}
   */
  public static function processPhoneNumbers(array $phone_numbers, string $language): ?array {
    $phones = [];
    $phone_number_data_in_language = array_filter($phone_numbers, function ($number) use ($language) {
      return $number->getLanguage() == $language;
    });

    $phone_charge_types_string_map = [
      'fi' => [
        'Chargeable' => 'paikallisverkko- tai matkapuhelinmaksu',
        'FreeOfCharge' => 'maksuton',
      ],
      'en' => [
        'Chargeable' => 'local/mobile network fee',
        'FreeOfCharge' => 'free of charge',
      ],
    ];

    foreach ($phone_number_data_in_language as $number) {
      $number_type = $number instanceof V4VmOpenApiPhoneWithType ? $number->getType() : 'Phone';
      $charge_type = $phone_charge_types_string_map[$language][$number->getServiceChargeType()] ?? '';

      $phone_info_parts = array_filter([$charge_type]);
      $phone_info = sprintf('(%s)', implode(', ', $phone_info_parts));
      $info = $number->getAdditionalInformation();

      $phones[] = [
        'title' => Unicode::truncate($info, 255, FALSE, TRUE),
        'label' => Unicode::truncate($phone_info, 255, FALSE, TRUE),
        'charged' => $number->getServiceChargeType(),
        'type' => $number_type,
        'country_code' => $number->getPrefixNumber(),
        'number' => $number->getPrefixNumber() . $number->getNumber(),
      ];
    }

    return $phones;
  }

  /**
   * {@inheritDoc}
   */
  public static function processRegularOpeningHours(V11VmOpenApiServiceHour &$hours_entry, $key, array $additional_data) {
    $hours_type = $additional_data['hours_type'];

    $info = '';
    $additional_info_data = $hours_entry->getAdditionalInformation();
    if (!empty($additional_info_data)) {
      $raw_info = self::getOpenApiLanguageItemStringsByLanguage($additional_info_data, $additional_data['language']);

      if (!empty($raw_info)) {
        $info = current($raw_info)->getValue();
      }
    }

    if ($hours_entry->getIsAlwaysOpen()) {
      $daily_opening_value = [
        'starthours' => '0',
        'endhours' => '0',
        'comment' => '',
      ];

      $daily_opening_hours = [];
      foreach (range(0, 6) as $day) {
        $daily_opening_hours[$day] = ['day' => $day] + $daily_opening_value;
      }
      $hours_entry = [
        'info' => $info,
        'hours' => $daily_opening_hours,
      ];

      return;
    }

    $actual_entry = [];
    $day_mapping = [
      'Sunday' => 0,
      'Monday' => 1,
      'Tuesday' => 2,
      'Wednesday' => 3,
      'Thursday' => 4,
      'Friday' => 5,
      'Saturday' => 6,
    ];

    $is_overnight_service_hour = $hours_type === 'OverMidnight';
    $index = 1;
    foreach ($day_mapping as $weekday => $array_key) {
      $opening_hours_entries = $hours_entry->getOpeningHour();
      $value_keys = self::findOpeningHourEntryByWeekday($weekday, $opening_hours_entries);
      if ($value_keys !== FALSE) {
        foreach ($value_keys as $value_key) {
          $opening_hour = $opening_hours_entries[$value_key];

          $start_time_with_seconds = $opening_hour->getFrom();
          $start_time_formatted = str_replace(':', '', substr($start_time_with_seconds, 0, -3));

          $end_time_with_seconds = $opening_hour->getTo();
          $end_time_formatted = str_replace(':', '', substr($end_time_with_seconds, 0, -3));

          $actual_entry[$index] = [
            'day' => $array_key,
            'starthours' => $start_time_formatted,
            'endhours' => $is_overnight_service_hour ? '0000' : $end_time_formatted,
            'comment' => '',
          ];

          if ($is_overnight_service_hour) {
            $index = $index + 1;
            $last_day_of_week = array_key_last($day_mapping) === $weekday;
            $actual_entry[] = [
              'day' => $last_day_of_week ? 0 : $array_key + 1,
              'starthours' => '0000',
              'endhours' => $end_time_formatted,
              'comment' => '',
            ];
          }
          $index = $index + 1;
        }
      }
    }

    $hours_entry = [
      'info' => $info,
      'hours' => $actual_entry,
    ];

  }

  /**
   * {@inheritdoc}
   */
  public static function processServiceHours(array &$values, array $opening_hours_types_with_processors, array $service_hours, string $language) {
    $hours_by_type = [];
    foreach (array_keys($opening_hours_types_with_processors) as $hours_type) {
      $hours_by_type[$hours_type] = array_filter($service_hours, function ($hours) use ($hours_type) {
        return $hours->getServiceHourType() == $hours_type;
      });
    }

    $opening_hours_values = [];
    foreach ($hours_by_type as $hours_type => $hours_entries) {
      $values_key = $opening_hours_types_with_processors[$hours_type]['value type'];
      if (!isset($opening_hours_values[$values_key])) {
        $opening_hours_values[$values_key] = [];
      }
      $processor_function = [
        __CLASS__,
        $opening_hours_types_with_processors[$hours_type]['processor'],
      ];
      $processor_additional_data = [
        'location_name' => $values['name'],
        'language' => $language,
        'hours_type' => $hours_type,
      ];
      array_walk($hours_entries, $processor_function, $processor_additional_data);
      $hours_entries = array_filter($hours_entries);
      $sortable_date_sorter = function ($a, $b) {
        if (!isset($a["sortable_date"]) || !isset($b["sortable_date"])) {
          return 0;
        }
        return $a["sortable_date"] - $b["sortable_date"];
      };
      usort($hours_entries, $sortable_date_sorter);
      if (!empty($hours_entries)) {
        $opening_hours_values[$values_key] = array_merge($opening_hours_values[$values_key], $hours_entries);
      }
    }

    // Flatten the opening hours information into single-level arrays for easier
    // processing.
    foreach ($opening_hours_values as $values_key => $hours_entries) {
      $hours_key = $values_key . '_hours';
      // This produces fields 'regular_daily_hours_hours' and
      // 'exception_hours_hours'.
      if (!isset($values[$hours_key])) {
        $values[$hours_key] = [];
      }

      $info_key = $values_key . '_info';
      // This produces fields 'regular_daily_hours_info' and
      // 'exception_hours_info'.
      if (!isset($values[$info_key])) {
        $values[$info_key] = [];
      }

      // Since the delta has significance in matching the values of the two
      // different fields in the destination, let's make the deltas match
      // between the two source values as well.
      foreach (array_values($hours_entries) as $delta => $hours_with_info) {
        $values[$hours_key][$delta] = $hours_with_info['hours'] ?? NULL;
        $values[$info_key][$delta] = $hours_with_info['info'] ?? NULL;
      }
    }
  }

  /**
   * {@inheritDoc}
   */
  public static function processWebPages(array $web_pages, string $language): ?array {
    $web_pages_list = [];

    $web_page_data_in_language = array_filter($web_pages, function ($web_page) use ($language) {
      return $web_page->getLanguage() == $language;
    });

    foreach ($web_page_data_in_language as $web_page) {
      $is_attachment = ($web_page instanceof VmOpenApiAttachment) || ($web_page instanceof VmOpenApiAttachmentWithType);
      $web_pages_list[] = [
        'title' => $is_attachment ? $web_page->getName() : $web_page->getValue(),
        'uri' => $web_page->getUrl(),
      ];
    }

    return empty($web_pages_list) ? NULL : $web_pages_list;
  }

  /**
   * Formats coordinates as a single string.
   *
   * @param array $coordinates
   *   Associative array with keys 'N' for northing value and 'E' for easting
   *   value.
   *
   * @return string
   *   The formatted value.
   */
  private static function formatCoordinatesAsString(array $coordinates) {
    return sprintf('%sN %sE', $coordinates['N'], $coordinates['E']);
  }

  /**
   * Fetches coordinate values from an AddressLocation object.
   *
   * The coordinate values are placed differently based on the type and the
   * subtype values of the object.
   *
   * @param \Tampere\PtvV11\PtvModel\V9VmOpenApiAddressLocation $address
   *   The AddressLocation object.
   *
   * @return array|null
   *   Associative array with keys 'N' for northing value and 'E' for easting
   *   value, if successful. Null otherwise.
   */
  private static function getCoordinatesForAddressLocation(V9VmOpenApiAddressLocation $address): ?array {
    switch ($address->getType() . '.' . $address->getSubType()) {
      case 'Location.Single':
        $easting = $address->getStreetAddress()->getLongitude();
        $northing = $address->getStreetAddress()->getLatitude();
        break;

      case 'Location.Other':
        $easting = $address->getOtherAddress()->getLongitude();
        $northing = $address->getOtherAddress()->getLatitude();
        break;
    }

    if (!empty($northing) && !empty($easting)) {
      // Drupal configuration has been set to only allow 5 decimals.
      return [
        'N' => round(floatval($northing), 5),
        'E' => round(floatval($easting), 5),
      ];
    }

    return NULL;
  }

}
