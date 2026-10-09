<?php

declare(strict_types=1);

namespace Drupal\tre_ptv_import\Service\Migration;

use Drupal\Component\Utility\Unicode;
use Drupal\node\NodeInterface;
use Drupal\tre_node_location_coordinate_conversion\Service\PointToRegionName;
use OpenAPI\Client\Model\ServiceLocationChannelResponse;

/**
 * Maps PTV ServiceLocation DTOs to the existing migration source contract.
 */
final class PtvServiceLocationSourceMapper {

  /**
   * Empty address source values expected by the address field migration process.
   */
  private const ADDRESS_FIELD_EMPTY_SOURCE = [
    'administrative_area' => NULL,
    'dependent_locality' => NULL,
    'premise' => NULL,
    'organisation_name' => NULL,
    'first_name' => NULL,
    'last_name' => NULL,
  ];

  /**
   * Constructs the service location source mapper.
   */
  public function __construct(
    private readonly PtvServiceHoursMapper $serviceHoursMapper,
    private readonly PointToRegionName $pointToRegionNameConverter,
  ) {}

  /**
   * Maps one ServiceLocation into existing migration source values.
   *
   * @param \OpenAPI\Client\Model\ServiceLocationChannelResponse $serviceLocation
   *   The generated concrete ServiceLocation DTO.
   * @param string $language
   *   The requested content language.
   * @param array<string, mixed> $accessibilityByServicePointId
   *   Accessibility information keyed by service point ID.
   *
   * @return array<string, mixed>
   *   Existing migration source values, or an empty array when the row should
   *   be skipped for the requested language.
   */
  public function map(
    ServiceLocationChannelResponse $serviceLocation,
    string $language,
    array $accessibilityByServicePointId = [],
    array $areas = [], 
  ): array {
    $language = trim($language);

    $locality = $this->getLocality($areas, $language);
    $placeAreaText = $this->getAreaText($areas, $language);

    if ($language === '') {
      throw new \InvalidArgumentException(
        'The requested service location language cannot be empty.'
      );
    }

    $channelType = $serviceLocation->getServiceChannelType();

    if ($channelType !== 'ServiceLocation') {
      throw new \RuntimeException(
        'Expected a ServiceLocation channel, got "' . (string) $channelType . '".'
      );
    }

    $localizedVersion = $this->getLocalizedVersion($serviceLocation, $language);

    if ($localizedVersion === NULL) {
      return [];
    }

    $name = $this->getOptionalStringFromGetter($localizedVersion, 'getName');

    if ($name === NULL) {
      return [];
    }

    $serviceLocationId = $this->getOptionalStringFromGetter(
      $serviceLocation,
      'getContentId'
    );

    if ($serviceLocationId === NULL) {
      throw new \RuntimeException(
        'PTV service location does not contain a valid contentId value.'
      );
    }

    $streetAddresses = [];
    $postalAddresses = [];
    $geographicalAreas = [];
    $epsgPoints = [];

    $location = $this->getObjectFromGetter($serviceLocation, 'getLocation');

    if ($location !== NULL) {
      foreach ($this->getArrayFromGetter($location, 'getStreetAddresses') as $address) {
        if (!is_object($address)) {
          continue;
        }

        $mappedAddress = $this->mapPhysicalStreetAddress(
          $address,
          $name,
          $language,
          $accessibilityByServicePointId,
          $locality,
        );

        if ($mappedAddress === NULL) {
          continue;
        }

        $streetAddresses[] = $mappedAddress['address'];

        if ($mappedAddress['region'] !== NULL) {
          $geographicalAreas[$mappedAddress['region']] = [
            'name' => $mappedAddress['region'],
          ];
        }

        if ($mappedAddress['epsg_point'] !== NULL) {
          $epsgPoints[$mappedAddress['epsg_point']] = $mappedAddress['epsg_point'];
        }
      }
    }

    $contactAddresses = $this->getObjectFromGetter(
      $serviceLocation,
      'getContactAddresses'
    );

    if ($contactAddresses !== NULL) {
      foreach ($this->getArrayFromGetter(
        $contactAddresses,
        'getPostOfficeBoxAddresses'
      ) as $address) {
        if (!is_object($address)) {
          continue;
        }

        $mappedAddress = $this->mapPostOfficeBoxAddress($address, $language, $locality);

        if ($mappedAddress !== NULL) {
          $postalAddresses[] = $mappedAddress;
        }
      }

      foreach ($this->getArrayFromGetter(
        $contactAddresses,
        'getStreetAddresses'
      ) as $address) {
        if (!is_object($address)) {
          continue;
        }

        $mappedAddress = $this->mapPostalStreetAddress($address, $language, $locality);

        if ($mappedAddress !== NULL) {
          $postalAddresses[] = $mappedAddress;
        }
      }
    }

    $hoursValues = [];
    $this->serviceHoursMapper->map(
      $hoursValues,
      $serviceLocation->getServiceHours(),
      $language
    );

    $regularHours = $this->mergeRegularHourGroups($hoursValues);

    return [
      'uuid' => $serviceLocationId,
      'name' => $name,
      'additional_name' => $this->getOptionalStringFromGetter(
        $localizedVersion,
        'getAlternativeName'
      ) ?? '',
      'street_addresses' => $streetAddresses,
      'postal_address' => $postalAddresses,
      'geographical_areas' => array_values($geographicalAreas),
      'epsg_points' => array_values($epsgPoints),
      'description' => $this->getOptionalStringFromGetter(
        $localizedVersion,
        'getDescription'
      ),
      'summary' => $this->getOptionalStringFromGetter(
        $localizedVersion,
        'getSummary'
      ),
      'regular_hours_hours' => $regularHours['hours'],
      'regular_hours_info' => $regularHours['info'],
      'exception_hours_info' => $this->getStringList(
        $hoursValues['exception_hours_info'] ?? []
      ),
      'phones' => $this->mapPhoneNumbers(
        $this->getArrayFromGetter($localizedVersion, 'getContactPhoneNumbers'),
        $language
      ),
      'web_pages' => $this->mapWebPages(
        $this->getArrayFromGetter($localizedVersion, 'getContactWebPages'),
        $name
      ),
      'emails' => $this->mapEmails(
        $this->getArrayFromGetter($localizedVersion, 'getContactEmails')
      ),
      'place_area_text' => $placeAreaText,
    ];
  }

  /**
   * Maps one physical street address to the map point source structure.
   *
   * @return array{address: array<string, mixed>, region: string|null, epsg_point: string|null}|null
   *   The mapped address and derived map values, or NULL when the address does
   *   not contain a usable street name.
   */
  private function mapPhysicalStreetAddress(
    object $address,
    string $locationName,
    string $language,
    array $accessibilityByServicePointId,
    string $locality,
  ): ?array {
    $streetName = $this->getLocalizedStringWithFallback(
      $this->getObjectFromGetter($address, 'getStreetName'),
      $language
    );

    if ($streetName === NULL) {
      return NULL;
    }

    $thoroughfare = $this->joinNonEmptyStrings([
      $streetName,
      $this->getOptionalStringFromGetter($address, 'getAddressDetail'),
    ]);

    $coordinates = $this->getArrayFromGetter($address, 'getCoordinates');
    $coordinate = reset($coordinates);
    $easting = NULL;
    $northing = NULL;
    $region = NULL;
    $epsgPoint = NULL;

    if (is_object($coordinate)) {
      $system = $this->getOptionalStringFromGetter($coordinate, 'getSystem');
      $coordinateEasting = $this->getNumericValueFromGetter(
        $coordinate,
        'getEasting'
      );
      $coordinateNorthing = $this->getNumericValueFromGetter(
        $coordinate,
        'getNorthing'
      );

      if (
        $system === 'EPSG:3067'
        && $coordinateEasting !== NULL
        && $coordinateNorthing !== NULL
      ) {
        $easting = $coordinateEasting;
        $northing = $coordinateNorthing;

        $regionValue = $this->pointToRegionNameConverter->convertPointToName(
          (float) $easting,
          (float) $northing
        );

        if (is_string($regionValue) && trim($regionValue) !== '') {
          $region = trim($regionValue);
        }

        $epsgPoint = $easting . ' ' . $northing;
      }
    }

    $coordinateLabel = $easting !== NULL && $northing !== NULL
      ? $northing . 'N ' . $easting . 'E'
      : '(ei koordinaatteja)';

    $addressForHash = [
      'address_label' => $locationName . ' - ' . $coordinateLabel,
      'description' => $this->getLocalizedStringWithFallback(
        $this->getObjectFromGetter($address, 'getAdditionalInformation'),
        $language
      ),
      'northing' => $northing,
      'easting' => $easting,
      'country' => 'FI',
      'thoroughfare' => $thoroughfare,
      'postal_code' => $this->getOptionalStringFromGetter(
        $address,
        'getPostalCode'
      ),
      'locality' => $locality,
      'region' => $region,
    ] + self::ADDRESS_FIELD_EMPTY_SOURCE;

    $addressHash = hash('sha512', serialize($addressForHash));
    unset($addressForHash['region']);

    $servicePointId = $this->getOptionalStringFromGetter(
      $address,
      'getAccessibilityRegisterServicePointId'
    );

    $addressForHash['address_hash'] = $addressHash;
    $addressForHash['accessibility_information'] = $this->encodeAccessibilityInformation(
      $accessibilityByServicePointId,
      $servicePointId,
      $language
    );

    $this->updateExistingMapPointAccessibility(
      $addressHash,
      $addressForHash['accessibility_information'],
      $language
    );

    return [
      'address' => $addressForHash,
      'region' => $region,
      'epsg_point' => $epsgPoint,
    ];
  }

  /**
   * Encodes accessibility information into the existing migration format.
   */
  private function encodeAccessibilityInformation(
    array $accessibilityByServicePointId,
    ?string $servicePointId,
    string $language,
  ): ?string {
    if ($servicePointId === NULL) {
      return NULL;
    }

    $accessibilityInformation =
      $accessibilityByServicePointId[$servicePointId][$language] ?? [];

    if (!is_array($accessibilityInformation) || $accessibilityInformation === []) {
      return NULL;
    }

    try {
      return json_encode(
        $accessibilityInformation,
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
      );
    }
    catch (\JsonException $exception) {
      throw new \RuntimeException(
        'Unable to encode accessibility information for service point "'
        . $servicePointId
        . '".',
        0,
        $exception
      );
    }
  }

  /**
   * Updates accessibility information on an existing map point translation.
   */
  private function updateExistingMapPointAccessibility(
    string $addressHash,
    ?string $accessibilityInformation,
    string $language,
  ): void {
    $existingMapPoints = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties([
        'type' => 'map_point',
        'field_address_hash' => $addressHash,
      ]);

    $mapPoint = reset($existingMapPoints);

    if (
      !$mapPoint instanceof NodeInterface
      || !$mapPoint->hasTranslation($language)
    ) {
      return;
    }

    $translatedMapPoint = $mapPoint->getTranslation($language);
    $currentAccessibilityInformation = $translatedMapPoint
      ->get('field_access_info_sentences_json')
      ->value;

    if ($currentAccessibilityInformation === $accessibilityInformation) {
      return;
    }

    $translatedMapPoint->set(
      'field_access_info_sentences_json',
      $accessibilityInformation
    );
    $translatedMapPoint->save();
  }

  /**
   * Maps one post office box address to the postal address source structure.
   */
  private function mapPostOfficeBoxAddress(
    object $address,
    string $language,
    string $locality,
  ): ?array {
    $postOfficeBox = $this->getLocalizedStringWithFallback(
      $this->getObjectFromGetter($address, 'getPostOfficeBox'),
      $language
    );

    if ($postOfficeBox === NULL) {
      return NULL;
    }

    return [
      'country' => 'FI',
      'thoroughfare' => $postOfficeBox,
      'postal_code' => $this->getOptionalStringFromGetter(
        $address,
        'getPostalCode'
      ),
      'locality' => $locality,
    ] + self::ADDRESS_FIELD_EMPTY_SOURCE;
  }

  /**
   * Maps one contact street address to the postal address source structure.
   */
  private function mapPostalStreetAddress(
    object $address,
    string $language,
    string $locality,
  ): ?array {
    $streetName = $this->getLocalizedStringWithFallback(
      $this->getObjectFromGetter($address, 'getStreetName'),
      $language
    );

    if ($streetName === NULL) {
      return NULL;
    }

    return [
      'country' => 'FI',
      'thoroughfare' => $this->joinNonEmptyStrings([
        $streetName,
        $this->getOptionalStringFromGetter($address, 'getAddressDetail'),
        $this->getLocalizedStringWithFallback(
          $this->getObjectFromGetter($address, 'getAdditionalInformation'),
          $language
        ),
      ]),
      'postal_code' => $this->getOptionalStringFromGetter(
        $address,
        'getPostalCode'
      ),
      'locality' => $locality,
    ] + self::ADDRESS_FIELD_EMPTY_SOURCE;
  }

  /**
   * Maps phone DTOs to the existing migration source format.
   *
   * @param object[] $phoneNumbers
   *   Generated phone DTOs.
   * @param string $language
   *   The requested content language.
   *
   * @return array<int, array<string, string|null>>
   *   Phone source values.
   */
  private function mapPhoneNumbers(array $phoneNumbers, string $language): array {
    $phones = [];

    foreach ($phoneNumbers as $phoneNumber) {
      if (!is_object($phoneNumber)) {
        continue;
      }

      $number = $this->getOptionalStringFromGetter($phoneNumber, 'getNumber');

      if ($number === NULL) {
        continue;
      }

      $dialCode = $this->getOptionalStringFromGetter(
        $phoneNumber,
        'getDialCode'
      );
      $fullNumber = ($dialCode ?? '') . $number;

      $phones[$fullNumber] = [
        'title' => Unicode::truncate(
          $this->getOptionalStringFromGetter(
            $phoneNumber,
            'getAdditionalInformation'
          ) ?? '',
          255,
          FALSE,
          TRUE
        ),
        'label' => $this->getPhoneChargeLabel(
          $this->getOptionalStringFromGetter($phoneNumber, 'getChargeType'),
          $language
        ),
        'charged' => $this->getOptionalStringFromGetter(
          $phoneNumber,
          'getChargeType'
        ),
        'type' => $this->getOptionalStringFromGetter($phoneNumber, 'getType'),
        'country_code' => $dialCode,
        'number' => $fullNumber,
      ];
    }

    return array_values($phones);
  }

  /**
   * Gets the generated charge type to the existing telephone label text.
   */
  private function getPhoneChargeLabel(?string $chargeType, string $language): string {
    $labels = [
      'fi' => [
        'Charged' => '(paikallisverkko- tai matkapuhelinmaksu)',
        'Free' => '(maksuton)',
      ],
      'en' => [
        'Charged' => '(local/mobile network fee)',
        'Free' => '(free of charge)',
      ],
    ];

    return $labels[$language][$chargeType] ?? '';
  }

  /**
   * Maps web page DTOs to the existing migration source format.
   *
   * @param object[] $webPages
   *   Generated web page DTOs.
   * @param string $fallbackTitle
   *   Fallback link title.
   *
   * @return array<int, array{title: string, uri: string}>
   *   Link source values.
   */
  private function mapWebPages(array $webPages, string $fallbackTitle): array {
    $links = [];
    $index = 0;

    foreach ($webPages as $webPage) {
      if (!is_object($webPage)) {
        continue;
      }

      $url = $this->getOptionalStringFromGetter($webPage, 'getUrl');

      if ($url === NULL) {
        continue;
      }

      $index++;
      $links[$url] = [
        'title' => $this->getOptionalStringFromGetter($webPage, 'getName')
          ?? ($index === 1 ? $fallbackTitle : $fallbackTitle . ' ' . $index),
        'uri' => $url,
      ];
    }

    return array_values($links);
  }

  /**
   * Maps email DTOs to the existing migration source format.
   *
   * @param object[] $emails
   *   Generated email DTOs.
   *
   * @return string[]
   *   Email source values.
   */
  private function mapEmails(array $emails): array {
    $values = [];

    foreach ($emails as $email) {
      if (!is_object($email)) {
        continue;
      }

      $value = $this->getOptionalStringFromGetter($email, 'getEmail');

      if ($value !== NULL) {
        $values[$value] = $value;
      }
    }

    return array_values($values);
  }

  /**
   * Merges regular daily and overnight hour groups into the source contract.
   *
   * The migration expects one array of hour groups and a matching array of
   * group headings. The indexes must stay aligned:
   *
   * regular_hours_hours[0] => regular_hours_info[0]
   * regular_hours_hours[1] => regular_hours_info[1]
   *
   * @param array<string, mixed> $hoursValues
   *   Values produced by PtvServiceHoursMapper.
   *
   * @return array{hours: array<int, array<int, array<string, mixed>>>, info: array<int, string|null>}
   *   Regular hour groups and their headings.
   */
  private function mergeRegularHourGroups(array $hoursValues): array {
    $hours = [];
    $info = [];

    $this->appendRegularHourGroups(
      $hours,
      $info,
      $hoursValues['regular_daily_hours_hours'] ?? [],
      $hoursValues['regular_daily_hours_info'] ?? []
    );

    $this->appendRegularHourGroups(
      $hours,
      $info,
      $hoursValues['regular_overnight_hours_hours'] ?? [],
      $hoursValues['regular_overnight_hours_info'] ?? []
    );

    return [
      'hours' => $hours,
      'info' => $info,
    ];
  }

  /**
   * Appends regular hour groups and keeps heading indexes aligned.
   *
   * @param array<int, array<int, array<string, mixed>>> $hours
   *   Merged regular hour groups.
   * @param array<int, string|null> $info
   *   Merged regular hour headings.
   * @param mixed $groups
   *   Candidate hour groups.
   * @param mixed $groupInfo
   *   Candidate group headings.
   */
  private function appendRegularHourGroups(
    array &$hours,
    array &$info,
    mixed $groups,
    mixed $groupInfo,
  ): void {
    if (!is_array($groups)) {
      return;
    }

    $groupInfo = is_array($groupInfo) ? array_values($groupInfo) : [];

    foreach (array_values($groups) as $index => $group) {
      if (!is_array($group) || $group === []) {
        continue;
      }

      $hours[] = $group;

      $infoValue = $groupInfo[$index] ?? NULL;
      $info[] = is_string($infoValue) && trim($infoValue) !== ''
        ? trim($infoValue)
        : NULL;
    }
  }

  /**
   * Gets a list of unique usable strings.
   *
   * @return string[]
   *   String values.
   */
  private function getStringList(mixed $value): array {
    if (!is_array($value)) {
      return [];
    }

    $values = [];

    foreach ($value as $item) {
      if (!is_string($item)) {
        continue;
      }

      $item = trim($item);

      if ($item !== '') {
        $values[$item] = $item;
      }
    }

    return array_values($values);
  }

  /**
   * Gets the localized generated DTO for one ServiceLocation language.
   */
  private function getLocalizedVersion(
    ServiceLocationChannelResponse $serviceLocation,
    string $language,
  ): ?object {
    $languageVersions = $serviceLocation->getLanguageVersions();

    if (!is_object($languageVersions)) {
      return NULL;
    }

    $getter = match ($language) {
      'fi' => 'getFi',
      'en' => 'getEn',
      'sv' => 'getSv',
      default => NULL,
    };

    if ($getter === NULL || !method_exists($languageVersions, $getter)) {
      return NULL;
    }

    $localizedVersion = $languageVersions->{$getter}();

    return is_object($localizedVersion) ? $localizedVersion : NULL;
  }

  /**
   * Gets one language-specific string from a generated localized DTO.
   */
  private function getLocalizedString(
    ?object $localizedValue,
    string $language,
  ): ?string {
    if ($localizedValue === NULL) {
      return NULL;
    }

    $getter = match ($language) {
      'fi' => 'getFi',
      'en' => 'getEn',
      'sv' => 'getSv',
      default => NULL,
    };

    if ($getter === NULL) {
      return NULL;
    }

    return $this->getOptionalStringFromGetter($localizedValue, $getter);
  }

  /**
   * Gets one localized string with a fallback to available Finnish, English or
   * Swedish address data.
   */
  private function getLocalizedStringWithFallback(
    ?object $localizedValue,
    string $language,
  ): ?string {
    $languages = array_values(array_unique([
      $language,
      'fi',
      'en',
      'sv',
    ]));

    foreach ($languages as $candidateLanguage) {
      $value = $this->getLocalizedString(
        $localizedValue,
        $candidateLanguage
      );

      if ($value !== NULL) {
        return $value;
      }
    }

    return NULL;
  }

  /**
   * Gets a generated object value from a getter.
   */
  private function getObjectFromGetter(object $model, string $getter): ?object {
    if (!method_exists($model, $getter)) {
      return NULL;
    }

    $value = $model->{$getter}();

    return is_object($value) ? $value : NULL;
  }

  /**
   * Gets a generated array value from a getter.
   *
   * @return array<int, mixed>
   *   The getter value, or an empty array.
   */
  private function getArrayFromGetter(object $model, string $getter): array {
    if (!method_exists($model, $getter)) {
      return [];
    }

    $value = $model->{$getter}();

    return is_array($value) ? $value : [];
  }

  /**
   * Gets an optional non-empty string from a generated DTO getter.
   *
   * The model can be any generated PTV DTO, such as a ServiceLocation,
   * localized language version or address DTO. The getter is the DTO method
   * name to call, for example getContentId(), getName() or getPostalCode().
   *
   * Returns NULL when the getter does not exist, does not return a string or
   * returns an empty string.
   */
  private function getOptionalStringFromGetter(
    object $model,
    string $getter,
  ): ?string {
    if (!method_exists($model, $getter)) {
      return NULL;
    }

    $value = $model->{$getter}();

    if (!is_string($value)) {
      return NULL;
    }

    $value = trim($value);

    return $value === '' ? NULL : $value;
  }

  /**
   * Gets an optional numeric value from a generated model getter.
   */
  private function getNumericValueFromGetter(
    object $model,
    string $getter,
  ): int|float|null {
    if (!method_exists($model, $getter)) {
      return NULL;
    }

    $value = $model->{$getter}();

    if (!is_int($value) && !is_float($value)) {
      return NULL;
    }

    return $value;
  }

  /**
   * Joins non-empty strings with a space.
   */
  private function joinNonEmptyStrings(array $values): string {
    $parts = [];

    foreach ($values as $value) {
      if (is_string($value) && trim($value) !== '') {
        $parts[] = trim($value);
      }
    }

    return implode(' ', $parts);
  }

  private function getLocality(array $areas, string $language): string {
    foreach ($areas as $area) {
      if (!is_array($area)) {
        continue;
      }

      $names = $area['name'] ?? NULL;

      if (!is_array($names)) {
        continue;
      }

      foreach (array_unique([$language, 'fi', 'en', 'sv']) as $candidateLanguage) {
        $name = $names[$candidateLanguage] ?? NULL;

        if (is_string($name) && trim($name) !== '') {
          return trim($name);
        }
      }
    }
    return '';
  }

  private function getAreaText(array $areas, string $language): string {
    $values = [];

    foreach ($areas as $area) {
      if (!is_array($area)) {
        continue;
      }

      $names = $area['name'] ?? NULL;

      if (!is_array($names)) {
        continue;
      }

      foreach (array_unique([$language, 'fi', 'en', 'sv']) as $candidateLanguage) {
        $name = $names[$candidateLanguage] ?? NULL;

        if (is_string($name) && trim($name) !== '') {
          $values[] = trim($name);
          break;
        }
      }
    }

    return implode(', ', array_unique($values));
  }

}