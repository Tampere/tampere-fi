<?php

namespace Drupal\tre_ptv_import;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Tampere\PtvV11\PtvApi\OrganizationApi;
use Tampere\PtvV11\PtvModel\V11VmOpenApiServiceChannels;
use Tampere\PtvV11\PtvModel\V11VmOpenApiServiceHour;
use Tampere\PtvV11\PtvModel\V9VmOpenApiAddressLocation;

/**
 * Interface for data helpers for PTV data.
 */
interface PtvDataHelpersInterface {

  const ADDRESS_TYPE_PROCESSORS = [
    'Location.Single' => [
      'processor' => 'processLocationSingle',
      'value type' => 'street_addresses',
    ],
    'Postal.PostOfficeBox' => [
      'processor' => 'processLocationPoBox',
      'value type' => 'postal_address',
    ],
    'Postal.Street' => [
      'processor' => 'processLocationStreetPostal',
      'value type' => 'postal_address',
    ],
    'Location.Other' => [
      'processor' => 'processLocationOther',
      'value type' => 'street_addresses',
    ],
  ];

  /**
   * Empty values for addressfield process plugin.
   *
   * @var array
   *
   * @see \Drupal\address\Plugin\migrate\process\AddressField::transform()
   */
  const ADDRESS_FIELD_EMPTY_SOURCE = [
    'country' => NULL,
    'administrative_area' => NULL,
    'locality' => NULL,
    'dependent_locality' => NULL,
    'postal_code' => NULL,
    'thoroughfare' => NULL,
    'premise' => NULL,
    'organisation_name' => NULL,
    'first_name' => NULL,
    'last_name' => NULL,
  ];

  /**
   * Helper method to find opening hours corresponding to a certain day.
   *
   * @param string $weekday
   *   The name of the weekday in English, capitalized. Example: Monday.
   * @param array $opening_hours_entries
   *   The entries to filter for the weekday.
   *
   * @return false|array
   *   If there is at least one matching entry or more with the correct weekday,
   *   returns an array containing their positions in the array (the array key).
   *   Otherwise returns false.
   */
  public static function findOpeningHourEntryByWeekday(string $weekday, array $opening_hours_entries);

  /**
   * Formats the date and time information for an exception opening hour entry.
   *
   * There are several different options for the date+time combo:
   * - single date: ::getValidFrom() is the only method returning a value.
   * - date interval: both ::getValidFrom() and ::getValidTo() return values.
   * - time interval: only ::getOpeningHour() returns a value. This is marked,
   *   as per observations so far, for a time interval happening on Mondays.
   * - date+time interval: ::getValidFrom(), ::getValidTo() and
   *   ::getOpeningHour() all return values.
   *
   * @param \Tampere\PtvV11\PtvModel\V11VmOpenApiServiceHour $exception_hour
   *   The service hour entry to format.
   *
   * @return array
   *   It contains the following items:
   *   - date_value: A formatted text depicting the exception time(s).
   *   - sortable_date: A formatted text depicting the sortable exception
   *     date(s)
   */
  public static function formatExceptionHourDatePart(V11VmOpenApiServiceHour $exception_hour);

  /**
   * Formats opening hour entries into string.
   *
   * @param \Tampere\PtvV11\PtvModel\V8VmOpenApiDailyOpeningTime[] $opening_hours
   *   The opening hour information to format.
   *
   * @return string
   *   Uses a set of rules to choose a suitable format for the formatting of the
   *   opening hour information.
   */
  public static function formatOpeningHours(array $opening_hours);

  /**
   * Formats a 'timestamp' in format 13:00:00 into 13.00.
   *
   * @param string $time_entry
   *   A 'timestamp' in format H:i:s (e.g. 08:00:00).
   *
   * @return string
   *   Formatted timestamp, e.g. 8.00.
   */
  public static function formatOpeningHourTime(string $time_entry);

  /**
   * Extracts a single string value by type and language from an array of items.
   *
   * @param \Tampere\PtvV11\PtvModel\VmOpenApiLocalizedListItem[] $descriptions_raw
   *   The raw array of descriptions.
   * @param string $language
   *   The language to use in filtering the values.
   * @param string $type
   *   The type of the list item to use in filtering the values.
   *
   * @return string
   *   The desired item values concatenated into a string.
   */
  public static function getDescriptionStringByLanguageAndType(array $descriptions_raw, string $language, string $type): string;

  /**
   * Gets the name of an organization by a given ID by language (if possible).
   *
   * @param string $organization_id
   *   The ID for the organization to get the name for.
   * @param string $language
   *   The language to use for extracting language-aware texts.
   * @param \Tampere\PtvV11\PtvApi\OrganizationApi $organization_api_connection
   *   A Swagger API instance capable of making requests to the organization
   *   API.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache bin to store organization names in.
   * @param \Drupal\Component\Datetime\TimeInterface $timeService
   *   The time service.
   *
   * @return string
   *   The name of the organization matching the ID.
   *
   * @throws \Tampere\PtvV11\ApiException
   *   Upon API errors.
   */
  public static function getOrganizationNameByLanguage(
    string $organization_id,
    string $language,
    OrganizationApi $organization_api_connection,
    CacheBackendInterface $cache,
    TimeInterface $timeService
  ): string;

  /**
   * Creates a string representation of areas a service is available in.
   *
   * Currently only handles the Municipality type.
   *
   * @todo In the future there might be a need to handle the other types:
   * - HospitalDistrict
   * - Region
   * - BusinessSubRegion
   *
   * @param \Tampere\PtvV11\PtvModel\VmOpenApiArea[] $areas
   *   The areas for a service, accessible by the getAreas() method.
   * @param string $language
   *   The language to use for extracting language-aware texts.
   *
   * @return string|null
   *   HTML text for area information, or null if no info available.
   */
  public static function processAreas(array $areas, string $language): ?string;

  /**
   * Process email addresses for placement in email fields.
   *
   * @param \Tampere\PtvV11\PtvModel\VmOpenApiLanguageItem[] $emails
   *   The emails associated with a service, accessible by the 'getEmails()' or
   *   equivalent method.
   * @param string $language
   *   The language to use for extracting language-aware texts.
   *
   * @return array|null
   *   Emails in an array if successful, otherwise null.
   */
  public static function processEmails(array $emails, string $language): ?array;

  /**
   * Callback for array_walk - transforms V11VmOpenApiServiceHour into array.
   *
   * @param \Tampere\PtvV11\PtvModel\V11VmOpenApiServiceHour $hours_entry
   *   The service hours object to transform. As per the definition of
   *   array_walk this parameter is transformed in place into an array.
   * @param int $key
   *   The array key. Unused but necessary in the signature to be able to accept
   *   the third argument.
   * @param array $additional_data
   *   Additional data to use in the processing. Must contain at least the
   *   following items:
   *   - 'language': the content language for the data.
   *   - 'hours_type': the type of service hour being processed,
   *     e.g. 'OverMidnight' or 'DaysOfTheWeek'.
   *
   * @throws \Exception
   */
  public static function processExceptionalOpeningHours(V11VmOpenApiServiceHour &$hours_entry, $key, array $additional_data);

  /**
   * Callback for array_walk - transforms V9VmOpenApiAddressLocation into array.
   *
   * This one handles AddressLocation objects whose type is Location and subtype
   * Single.
   *
   * @param \Tampere\PtvV11\PtvModel\V9VmOpenApiAddressLocation $address
   *   The address object to transform. As per the definition of array_walk this
   *   parameter is transformed in place into an array.
   * @param int $key
   *   The array key. Unused but necessary in the signature to be able to accept
   *   the third argument.
   * @param array $additional_data
   *   Additional data to use in the processing. Must contain at least the
   *   following items:
   *   - 'language': the content language for the data;
   *   - 'location_name': the name to add in the label of this datum.
   */
  public static function processLocationSingle(V9VmOpenApiAddressLocation &$address, $key, array $additional_data);

  /**
   * Callback for array_walk - transforms V9VmOpenApiAddressLocation into array.
   *
   * This one handles AddressLocation objects whose type is Location and subtype
   * Other.
   *
   * @param \Tampere\PtvV11\PtvModel\V9VmOpenApiAddressLocation $address
   *   The address object to transform. As per the definition of array_walk this
   *   parameter is transformed in place into an array.
   * @param int $key
   *   The array key. Unused but necessary in the signature to be able to accept
   *   the third argument.
   * @param array $additional_data
   *   Additional data to use in the processing. Must contain at least the
   *   following items:
   *   - 'language': the content language for the data;
   *   - 'location_name': the name to add in the label of this datum.
   */
  public static function processLocationOther(V9VmOpenApiAddressLocation &$address, $key, array $additional_data);

  /**
   * Callback for array_walk - transforms V9VmOpenApiAddressLocation into array.
   *
   * This one handles AddressLocation objects whose type is Postal and subtype
   * PostOfficeBox.
   *
   * @param \Tampere\PtvV11\PtvModel\V9VmOpenApiAddressLocation|\Tampere\PtvV11\PtvModel\V8VmOpenApiAddressDelivery $address
   *   The address object to transform. As per the definition of array_walk this
   *   parameter is transformed in place into an array.
   * @param int $key
   *   The array key. Unused but necessary in the signature to be able to accept
   *   the third argument.
   * @param array $additional_data
   *   Additional data to use in the processing. Must contain at least the
   *   following items:
   *   - 'language': the content language for the data.
   */
  public static function processLocationPoBox(&$address, $key, array $additional_data);

  /**
   * Callback for array_walk - transforms V9VmOpenApiAddressLocation into array.
   *
   * This one handles AddressLocation objects whose type is Postal and subtype
   * Street.
   *
   * @param \Tampere\PtvV11\PtvModel\V9VmOpenApiAddressLocation|\Tampere\PtvV11\PtvModel\V8VmOpenApiAddressDelivery $address
   *   The address object to transform. As per the definition of array_walk this
   *   parameter is transformed in place into an array.
   * @param int $key
   *   The array key. Unused but necessary in the signature to be able to accept
   *   the third argument.
   * @param array $additional_data
   *   Additional data to use in the processing. Must contain at least the
   *   following items:
   *   - 'language': the content language for the data.
   */
  public static function processLocationStreetPostal(&$address, $key, array $additional_data);

  /**
   * Helps create a processable phone number array from service phone numbers.
   *
   * @param \Tampere\PtvV11\PtvModel\V4VmOpenApiPhoneWithType[]|\Tampere\PtvV11\PtvModel\V4VmOpenApiPhone[] $phone_numbers
   *   The phone numbers to process.
   * @param string $language
   *   The language in which the data is desired.
   *
   * @return array|null
   *   The phone numbers ready for processing in a migration process pipeline.
   *   Null otherwise.
   */
  public static function processPhoneNumbers(array $phone_numbers, string $language): ?array;

  /**
   * Callback for array_walk - transforms V11VmOpenApiServiceHour into array.
   *
   * The resulting array adheres to the format of OfficeHoursItem::schema from
   * the office_hours module but also includes the information embedded in any
   * of the opening hours items in the API.
   *
   * @param \Tampere\PtvV11\PtvModel\V11VmOpenApiServiceHour $hours_entry
   *   The service hours object to transform. As per the definition of
   *   array_walk this parameter is transformed in place into an array.
   * @param int $key
   *   The array key. Unused but necessary in the signature to be able to accept
   *   the third argument.
   * @param array $additional_data
   *   Additional data to use in the processing. Must contain at least the
   *   following items:
   *   - 'language': the content language for the data.
   *   - 'hours_type': the type of service hour being processed,
   *     e.g. 'OverMidnight' or 'DaysOfTheWeek'.
   *
   * @throws \Exception
   *
   * @see \Drupal\office_hours\Plugin\Field\FieldType\OfficeHoursItem
   */
  public static function processRegularOpeningHours(V11VmOpenApiServiceHour &$hours_entry, $key, array $additional_data);

  /**
   * Processes service hours.
   *
   * @param array $values
   *   The values array to transform to include information about the service
   *   hours.
   * @param array $opening_hours_types_with_processors
   *   An array containing information on the targeted opening hours types
   *   with their processors.
   * @param \Tampere\PtvV11\PtvModel\V11VmOpenApiServiceHour[] $service_hours
   *   The service hours for a service, accessible by the 'getServiceHours()'
   *   method.
   * @param string $language
   *   The language that should be in use for the hours.
   */
  public static function processServiceHours(array &$values, array $opening_hours_types_with_processors, array $service_hours, string $language);

  /**
   * Process web pages for placement in multi-value link field.
   *
   * @param \Tampere\PtvV11\PtvModel\V9VmOpenApiWebPage[]|\Tampere\PtvV11\PtvModel\VmOpenApiAttachmentWithType[]|\Tampere\PtvV11\PtvModel\VmOpenApiAttachment[] $web_pages
   *   The web pages associated with a service, accessible by the getWebPages()
   *   method.
   * @param string $language
   *   The language to use for extracting language-aware texts.
   *
   * @return array|null
   *   Web pages in an array if successful, otherwise null.
   */
  public static function processWebPages(array $web_pages, string $language): ?array;

  /**
   * Extracts string values of extracted items in a language from a value array.
   *
   * @param \Tampere\PtvV11\PtvModel\VmOpenApiLanguageItem[] $localized_items
   *   The items to pick from.
   * @param string $language
   *   The language that should be in use for the selected items.
   *
   * @return \Tampere\PtvV11\PtvModel\VmOpenApiLanguageItem[]
   *   The items that have the given language.
   */
  public static function getOpenApiLanguageItemStringsByLanguage(array $localized_items, string $language): array;

  /**
   * Extracts an actual service channel object from the collection object.
   *
   * @param \Tampere\PtvV11\PtvModel\V11VmOpenApiServiceChannels $apiServiceChannelsCollection
   *   The collection object.
   *
   * @return \Tampere\PtvV11\PtvModel\V11VmOpenApiElectronicChannel|\Tampere\PtvV11\PtvModel\V11VmOpenApiServiceLocationChannel|\Tampere\PtvV11\PtvModel\V11VmOpenApiPhoneChannel|\Tampere\PtvV11\PtvModel\V11VmOpenApiPrintableFormChannel|\Tampere\PtvV11\PtvModel\V11VmOpenApiWebPageChannel
   *   The actual service channel object represented by the collection.
   */
  public static function getServiceChannelObjectFromServiceChannelsObject(V11VmOpenApiServiceChannels $apiServiceChannelsCollection);

  /**
   * Separates service location channel objects from service channel objects.
   *
   * @param array $allChannels
   *   An array of different service channel objects of the following types:
   *   - \Tampere\PtvV11\PtvModel\V11VmOpenApiElectronicChannel
   *   - \Tampere\PtvV11\PtvModel\V11VmOpenApiServiceLocationChannel
   *   - \Tampere\PtvV11\PtvModel\V11VmOpenApiPhoneChannel
   *   - \Tampere\PtvV11\PtvModel\V11VmOpenApiPrintableFormChannel
   *   - \Tampere\PtvV11\PtvModel\V11VmOpenApiWebPageChannel.
   *
   * @return array
   *   A two-member array whose first member contains all the
   *   V11VmOpenApiServiceLocationChannel objects from the source array and the
   *   second member the rest of the array.
   */
  public static function separateLocationsFromOtherServiceChannels(array $allChannels): array;

}
