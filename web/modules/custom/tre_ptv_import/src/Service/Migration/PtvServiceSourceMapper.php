<?php

declare(strict_types=1);

namespace Drupal\tre_ptv_import\Service\Migration;

use OpenAPI\Client\Model\ServiceResponse;

/**
 * Maps PTV service DTOs to the existing migration source contract.
 */
final class PtvServiceSourceMapper {

  /**
   * Maps service channel types to existing migration source fields.
   */
  private const CHANNEL_TYPE_TO_SOURCE_FIELD = [
    'ServiceLocation' => 'service_locations',
    'EService' => 'eservice_channels',
    'TelephoneService' => 'phone_service_channels',
    'WebPage' => 'web_page_service_channels',
    'PrintableForm' => 'form_service_channels',
  ];

  /**
   * Maps service charge types to existing migration source values.
   */
  private const CHARGE_TYPE_MAP = [
    'Free' => 'FreeOfCharge',
    'Charged' => 'Chargeable',
  ];

  /**
   * Constructs the service source mapper.
   */
  public function __construct(
    private readonly PtvOrganizationNameResolver $organizationNameResolver,
  ) {}

  /**
   * Maps one service into existing migration source values.
   *
   * Returns an empty array when the requested language does not have a name.
   * This preserves the current source-plugin behavior: a migration row is
   * skipped when a localized title is missing.
   *
   * @param \OpenAPI\Client\Model\ServiceResponse $service
   *   The generated service DTO.
   * @param array<int, array<string, mixed>> $connections
   *   The connections belonging to the service.
   * @param string $language
   *   The requested content language.
   * @param array<string, string> $channelTypesById
   *   Channel content IDs keyed to their service channel type values.
   * @param string|null $areasText
   *   Already formatted localized area text. Area code resolution is handled
   *   separately from this mapper.
   *
   * @return array<string, mixed>
   *   Existing migration source values, or an empty array when the row should
   *   be skipped for the requested language.
   */
  public function map(
    ServiceResponse $service,
    array $connections,
    string $language,
    array $channelTypesById,
    ?string $areasText = NULL,
  ): array {
    $language = trim($language);

    if ($language === '') {
      throw new \InvalidArgumentException(
        'The requested service language cannot be empty.'
      );
    }

    $serviceId = $this->getOptionalStringFromGetter($service, 'getContentId');

    if ($serviceId === NULL) {
      throw new \RuntimeException(
        'PTV service payload does not contain a valid contentId value.'
      );
    }

    $localizedVersion = $this->getLocalizedVersion($service, $language);

    if ($localizedVersion === NULL) {
      return [];
    }

    $name = $this->getOptionalStringFromGetter($localizedVersion, 'getName');

    if ($name === NULL) {
      return [];
    }

    [$serviceVouchersInUse, $serviceVoucherLinks] = $this->mapVoucher(
      $localizedVersion
    );

    $values = [
      'uuid' => $serviceId,
      'name' => $name,
      'alternative_name' => $this->getOptionalStringFromGetter(
        $localizedVersion,
        'getAlternativeName'
      ),
      'description' => $this->getOptionalStringFromGetter(
        $localizedVersion,
        'getDescription'
      ),
      'summary' => $this->getOptionalStringFromGetter(
        $localizedVersion,
        'getSummary'
      ),
      'user_instruction' => $this->getOptionalStringFromGetter(
        $localizedVersion,
        'getUserInstructions'
      ),
      'requirements' => $this->getOptionalStringFromGetter(
        $localizedVersion,
        'getConditions'
      ),
      'chargeability' => $this->mapChargeType($service->getChargeType()),
      'chargeability_info' => $this->getOptionalStringFromGetter(
        $localizedVersion,
        'getChargeTypeAdditionalInformation'
      ),
      'service_vouchers_in_use' => $serviceVouchersInUse,
      'service_voucher_links' => $serviceVoucherLinks,
      'languages' => $this->getStringListFromGetter(
        $service,
        'getServiceLanguages'
      ),
      'service_producer' => $this->mapProducerNames(
        $service,
        $language
      ),
      'service_responsible' => $this->mapResponsibleOrganizationName(
        $service,
        $language
      ),
      'service_other_responsible' => $this->mapOtherResponsibleNames(
        $service,
        $language
      ),
      'areas_text' => $this->normalizeOptionalString($areasText),
      'life_situations' => $this->mergeUniqueStringLists(
        $this->getStringListFromGetter($service, 'getLifeEvents'),
        $this->getStringListFromGetter($service, 'getTargetGroups'),
      ),
      'topics' => $this->getStringListFromGetter(
        $service,
        'getServiceClasses'
      ),
      'keywords' => $this->getStringListFromGetter(
        $service,
        'getOntologyTerms'
      ),
      'service_locations' => [],
      'eservice_channels' => [],
      'phone_service_channels' => [],
      'web_page_service_channels' => [],
      'form_service_channels' => [],
    ];

    $channelReferences = $this->mapChannelReferences(
      $connections,
      $channelTypesById
    );

    foreach ($channelReferences as $sourceField => $channelIds) {
      $values[$sourceField] = $channelIds;
    }

    return $values;
  }

  /**
   * Gets the localized generated DTO for one service language.
   */
  private function getLocalizedVersion(
    ServiceResponse $service,
    string $language,
  ): ?object {
    $languageVersions = $this->getObjectFromGetter(
      $service,
      'getLanguageVersions'
    );

    if ($languageVersions === NULL) {
      return NULL;
    }

    $getter = match ($language) {
      'fi' => 'getFi',
      'en' => 'getEn',
      default => NULL,
    };

    if ($getter === NULL || !method_exists($languageVersions, $getter)) {
      return NULL;
    }

    $localizedVersion = $languageVersions->{$getter}();

    return is_object($localizedVersion) ? $localizedVersion : NULL;
  }

  /**
   * Maps service chargeType to the existing migration source value.
   */
  private function mapChargeType(mixed $chargeType): ?string {
    if ($chargeType === NULL) {
      return NULL;
    }

    if (!is_string($chargeType) || trim($chargeType) === '') {
      throw new \RuntimeException(
        'PTV service chargeType must be a string or null.'
      );
    }

    $chargeType = trim($chargeType);

    if (!isset(self::CHARGE_TYPE_MAP[$chargeType])) {
      throw new \RuntimeException(
        'Unsupported PTV service chargeType "' . $chargeType . '".'
      );
    }

    return self::CHARGE_TYPE_MAP[$chargeType];
  }

  /**
   * Maps one localized voucher structure.
   *
   * @return array{0: bool, 1: array<int, array{uri: string, title: string}>}
   *   Whether vouchers are in use and normalized voucher links.
   */
  private function mapVoucher(object $localizedVersion): array {
    $voucher = $this->getObjectFromGetter($localizedVersion, 'getVoucher');

    if ($voucher === NULL) {
      return [FALSE, []];
    }

    $voucherType = $this->getOptionalStringFromGetter(
      $voucher,
      'getVoucherType'
    );

    if ($voucherType !== 'WithUrl') {
      return [FALSE, []];
    }

    $links = [];

    foreach ($this->getArrayFromGetter($voucher, 'getUrls') as $urlData) {
      $link = $this->mapVoucherLink($urlData);

      if ($link === NULL) {
        continue;
      }

      $links[$link['uri']] = $link;
    }

    return [TRUE, array_values($links)];
  }

  /**
   * Maps one voucher URL entry.
   *
   * @return array{uri: string, title: string}|null
   *   A migration-ready link or NULL when the entry is empty.
   */
  private function mapVoucherLink(mixed $urlData): ?array {
    if (is_string($urlData)) {
      $uri = trim($urlData);

      return $uri === ''
        ? NULL
        : [
          'uri' => $uri,
          'title' => '',
        ];
    }

    if (is_array($urlData)) {
      $uri = $this->getFirstOptionalString(
        $urlData,
        ['url', 'uri']
      );

      if ($uri === NULL) {
        return NULL;
      }

      $title = $this->getFirstOptionalString(
        $urlData,
        ['name', 'title', 'value']
      ) ?? '';

      return [
        'uri' => $uri,
        'title' => $title,
      ];
    }

    if (!is_object($urlData)) {
      return NULL;
    }

    $uri = $this->getOptionalStringFromGetter($urlData, 'getUrl')
      ?? $this->getOptionalStringFromGetter($urlData, 'getUri');

    if ($uri === NULL) {
      return NULL;
    }

    $title = $this->getOptionalStringFromGetter($urlData, 'getName')
      ?? $this->getOptionalStringFromGetter($urlData, 'getTitle')
      ?? $this->getOptionalStringFromGetter($urlData, 'getValue')
      ?? '';

    return [
      'uri' => $uri,
      'title' => $title,
    ];
  }

  /**
   * Maps all producer organization names and free-text producer values.
   */
  private function mapProducerNames(
    ServiceResponse $service,
    string $language,
  ): ?string {
    $producers = $this->getObjectFromGetter($service, 'getProducers');

    if ($producers === NULL) {
      return NULL;
    }

    $organizationIds = [];
    $freeTextValues = [];

    foreach ([
      'getSelfProducedProducers',
      'getProcuredProducers',
      'getOtherProducers',
    ] as $producerGetter) {
      $producerData = $this->getObjectFromGetter($producers, $producerGetter);

      if ($producerData === NULL) {
        continue;
      }

      $organizationIds = $this->mergeUniqueStringLists(
        $organizationIds,
        $this->getStringListFromGetter($producerData, 'getOrganizations')
      );

      $freeTextValues = $this->mergeUniqueStringLists(
        $freeTextValues,
        $this->mapOtherProducerNames(
          $this->getArrayFromGetter($producerData, 'getOther'),
          $language
        )
      );
    }

    $organizationNames = $this->organizationNameResolver->getNames(
      $organizationIds,
      $language
    );

    return $this->joinNames(
      $this->mergeUniqueStringLists(
        $organizationNames,
        $freeTextValues
      )
    );
  }

  /**
   * Maps localized free-text producer names.
   *
   * @param array<int, mixed> $otherProducers
   *   Generated other producer DTOs.
   *
   * @return string[]
   *   Unique non-empty localized names.
   */
  private function mapOtherProducerNames(
    array $otherProducers,
    string $language,
  ): array {
    $values = [];

    foreach ($otherProducers as $otherProducer) {
      if (is_string($otherProducer)) {
        $value = $this->normalizeOptionalString($otherProducer);
      }
      elseif (is_object($otherProducer)) {
        $name = $this->getObjectFromGetter($otherProducer, 'getName');
        $value = $this->getLocalizedString($name, $language);
      }
      else {
        continue;
      }

      if ($value !== NULL) {
        $values[$value] = $value;
      }
    }

    return array_values($values);
  }

  /**
   * Maps the organization responsible for the service.
   */
  private function mapResponsibleOrganizationName(
    ServiceResponse $service,
    string $language,
  ): ?string {
    $organizationId = $this->getOptionalStringFromGetter(
      $service,
      'getOrganizationContentId'
    );

    if ($organizationId === NULL) {
      return NULL;
    }

    return $this->organizationNameResolver->getName(
      $organizationId,
      $language
    );
  }

  /**
   * Maps other responsible organization names.
   */
  private function mapOtherResponsibleNames(
    ServiceResponse $service,
    string $language,
  ): ?string {
    $organizationIds = $this->getStringListFromGetter(
      $service,
      'getOtherResponsibleOrganizations'
    );

    return $this->joinNames(
      $this->organizationNameResolver->getNames(
        $organizationIds,
        $language
      )
    );
  }

  /**
   * Maps service connections to current migration source reference fields.
   *
   * @param array<int, array<string, mixed>> $connections
   *   Raw service connections.
   * @param array<string, string> $channelTypesById
   *   Channel types keyed by channel content ID.
   *
   * @return array<string, string[]>
   *   Channel IDs grouped by existing source field.
   */
  private function mapChannelReferences(
    array $connections,
    array $channelTypesById,
  ): array {
    $references = [];

    foreach (self::CHANNEL_TYPE_TO_SOURCE_FIELD as $sourceField) {
      $references[$sourceField] = [];
    }

    foreach ($connections as $index => $connection) {
      if (!is_array($connection)) {
        throw new \RuntimeException(
          'PTV service connection at index ' . $index . ' is not an array.'
        );
      }

      $channelId = $this->getOptionalString($connection, 'serviceChannelContentId');

      if ($channelId === NULL) {
        throw new \RuntimeException(
          'PTV service connection at index ' . $index . ' does not contain a valid serviceChannelContentId value.'
        );
      }

      $channelType = $channelTypesById[$channelId] ?? NULL;

      if (!is_string($channelType) || trim($channelType) === '') {
        throw new \RuntimeException(
          'PTV service connection references channel "' . $channelId . '" without a known type.'
        );
      }

      $channelType = trim($channelType);
      $sourceField = self::CHANNEL_TYPE_TO_SOURCE_FIELD[$channelType] ?? NULL;

      if ($sourceField === NULL) {
        throw new \RuntimeException(
          'Unsupported PTV service channel type "' . $channelType . '" for channel "' . $channelId . '".'
        );
      }

      $references[$sourceField][$channelId] = $channelId;
    }

    foreach ($references as $sourceField => $channelIds) {
      $references[$sourceField] = array_values($channelIds);
    }

    return $references;
  }

  /**
   * Gets an optional normalized string from a structure.
   */
  private function getOptionalString(
    array $data,
    string $key,
  ): ?string {
    if (!array_key_exists($key, $data) || $data[$key] === NULL) {
      return NULL;
    }

    if (!is_string($data[$key])) {
      throw new \RuntimeException(
        'PTV property "' . $key . '" must be a string or null.'
      );
    }

    return $this->normalizeOptionalString($data[$key]);
  }

  /**
   * Gets the first usable string from a list of possible keys.
   */
  private function getFirstOptionalString(
    array $data,
    array $keys,
  ): ?string {
    foreach ($keys as $key) {
      $value = $this->getOptionalString($data, $key);

      if ($value !== NULL) {
        return $value;
      }
    }

    return NULL;
  }

  /**
   * Gets a generated object value from a getter.
   */
  private function getObjectFromGetter(
    object $model,
    string $getter,
  ): ?object {
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
  private function getArrayFromGetter(
    object $model,
    string $getter,
  ): array {
    if (!method_exists($model, $getter)) {
      return [];
    }

    $value = $model->{$getter}();

    if ($value === NULL) {
      return [];
    }

    if (!is_array($value)) {
      throw new \RuntimeException(
        'PTV getter "' . $getter . '" must return an array or null.'
      );
    }

    return $value;
  }

  /**
   * Gets a generated list of strings from a getter.
   *
   * @return string[]
   *   Unique non-empty strings in their original order.
   */
  private function getStringListFromGetter(
    object $model,
    string $getter,
  ): array {
    $values = [];

    foreach ($this->getArrayFromGetter($model, $getter) as $index => $value) {
      $value = $this->normalizeStringValue(
        $value,
        $getter . ' item at index ' . $index
      );

      if ($value !== NULL) {
        $values[$value] = $value;
      }
    }

    return array_values($values);
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
      default => NULL,
    };

    if ($getter === NULL) {
      return NULL;
    }

    return $this->getOptionalStringFromGetter($localizedValue, $getter);
  }

  /**
   * Gets an optional normalized string from a generated model getter.
   */
  private function getOptionalStringFromGetter(
    object $model,
    string $getter,
  ): ?string {
    if (!method_exists($model, $getter)) {
      return NULL;
    }

    return $this->normalizeStringValue(
      $model->{$getter}(),
      $getter
    );
  }

  /**
   * Normalizes a string-like value from generated DTOs.
   */
  private function normalizeStringValue(
    mixed $value,
    string $label,
  ): ?string {
    if ($value === NULL) {
      return NULL;
    }

    if (is_string($value)) {
      return $this->normalizeOptionalString($value);
    }

    if (is_object($value) && method_exists($value, '__toString')) {
      return $this->normalizeOptionalString((string) $value);
    }

    throw new \RuntimeException(
      'PTV value "' . $label . '" must be a string or null.'
    );
  }

  /**
   * Merges unique strings while retaining their original order.
   */
  private function mergeUniqueStringLists(array ...$lists): array {
    $merged = [];

    foreach ($lists as $list) {
      foreach ($list as $value) {
        if (!is_string($value)) {
          throw new \InvalidArgumentException(
            'String list merging only accepts strings.'
          );
        }

        $value = trim($value);

        if ($value !== '') {
          $merged[$value] = $value;
        }
      }
    }

    return array_values($merged);
  }

  /**
   * Joins names for a current string source field.
   */
  private function joinNames(array $names): ?string {
    $names = $this->mergeUniqueStringLists($names);

    return $names === []
      ? NULL
      : implode(', ', $names);
  }

  /**
   * Normalizes an optional string.
   */
  private function normalizeOptionalString(?string $value): ?string {
    if ($value === NULL) {
      return NULL;
    }

    $value = trim($value);

    return $value === '' ? NULL : $value;
  }

}