<?php

declare(strict_types=1);

namespace Drupal\tre_ptv_import\Service\Migration;

use OpenAPI\Client\Model\EServiceChannelResponse;
use OpenAPI\Client\Model\ModelInterface;
use OpenAPI\Client\Model\PhoneChannelResponse;
use OpenAPI\Client\Model\PrintableFormChannelResponse;
use OpenAPI\Client\Model\WebPageChannelResponse;

/**
 * Maps PTV service channel DTOs to the existing migration source contract.
 */
final class PtvChannelSourceMapper {

  /**
   * Maps service channel types to the existing migration source values.
   */
  private const CHANNEL_TYPE_MAP = [
    'EService' => 'EChannel',
    'TelephoneService' => 'Phone',
    'PrintableForm' => 'PrintableForm',
    'WebPage' => 'WebPage',
  ];

  /**
   * Constructs the channel source mapper.
   */
  public function __construct(
    private readonly PtvOrganizationNameResolver $organizationNameResolver,
    private readonly PtvServiceHoursMapper $serviceHoursMapper,
  ) {}

  /**
   * Maps one non-location service channel into existing migration source values.
   *
   * Returns an empty array when the requested language does not have a name.
   * This preserves the current source-plugin behavior: a migration row is
   * skipped when a localized title is missing.
   *
   * @param \OpenAPI\Client\Model\ModelInterface $channel
   *   The concrete generated service channel DTO.
   * @param string $language
   *   The requested content language.
   * @param string|null $areasText
   *   Already formatted localized area text.
   *
   * @return array<string, mixed>
   *   Existing migration source values, or an empty array when the row should
   *   be skipped for the requested language.
   */
  public function map(
    ModelInterface $channel,
    string $language,
    ?string $areasText = NULL,
  ): array {
    $language = trim($language);

    if ($language === '') {
      throw new \InvalidArgumentException(
        'The requested service channel language cannot be empty.'
      );
    }

    $channelType = $this->getOptionalStringFromGetter(
      $channel,
      'getServiceChannelType'
    );

    if ($channelType === NULL) {
      throw new \RuntimeException(
        'PTV service channel does not contain a valid serviceChannelType value.'
      );
    }

    if (!isset(self::CHANNEL_TYPE_MAP[$channelType])) {
      throw new \RuntimeException(
        'Unsupported PTV service channel type "' . $channelType . '".'
      );
    }

    if (
      !$channel instanceof EServiceChannelResponse
      && !$channel instanceof PhoneChannelResponse
      && !$channel instanceof PrintableFormChannelResponse
      && !$channel instanceof WebPageChannelResponse
    ) {
      throw new \RuntimeException(
        'PTV channel type "' . $channelType . '" did not deserialize into a supported concrete channel DTO.'
      );
    }

    $localizedVersion = $this->getLocalizedVersion($channel, $language);

    if ($localizedVersion === NULL) {
      return [];
    }

    $name = $this->getOptionalStringFromGetter($localizedVersion, 'getName');

    if ($name === NULL) {
      return [];
    }

    $channelId = $this->getOptionalStringFromGetter($channel, 'getContentId');

    if ($channelId === NULL) {
      throw new \RuntimeException(
        'PTV service channel does not contain a valid contentId value.'
      );
    }

    $values = [
      'uuid' => $channelId,
      'name' => $name,
      'description' => $this->getOptionalStringFromGetter(
        $localizedVersion,
        'getDescription'
      ),
      'summary' => $this->getOptionalStringFromGetter(
        $localizedVersion,
        'getSummary'
      ),
      'service_channel_type' => self::CHANNEL_TYPE_MAP[$channelType],
      'web_pages' => $this->mapWebAddress(
        $this->getObjectFromGetter($localizedVersion, 'getWebAddress'),
        $name
      ),
      'support_phones' => $this->mapPhoneNumbers(
        $this->getArrayFromGetter($localizedVersion, 'getSupportPhoneNumbers')
      ),
      'support_emails' => $this->mapEmails(
        $this->getArrayFromGetter($localizedVersion, 'getSupportEmails')
      ),
      'phones' => NULL,
      'attachments' => $this->mapLinks(
        $this->getArrayFromGetter($localizedVersion, 'getAttachmentLinks'),
        $name
      ),
      'accessibility' => NULL,
      'electronic_signature_required' => FALSE,
      'electronic_id_required' => FALSE,
      'postal_address' => NULL,
      'delivery_details' => NULL,
      'form_receiver' => NULL,
      'forms' => NULL,
      'languages' => $this->getStringListFromGetter(
        $channel,
        'getServiceLanguages'
      ),
      'areas_text' => $this->normalizeOptionalString($areasText),
      'organization' => $this->mapOrganizationName($channel, $language),
      'regular_daily_hours_hours' => [],
      'regular_overnight_hours_hours' => [],
      'exception_hours_info' => [],
    ];

    $this->serviceHoursMapper->map(
      $values,
      method_exists($channel, 'getServiceHours')
        ? $channel->getServiceHours()
        : NULL,
      $language
    );

    if ($channel instanceof EServiceChannelResponse) {
      $values['electronic_id_required'] = $this->getOptionalStringFromGetter(
        $channel,
        'getElectronicIdentification'
      ) === 'Required';

      $values['electronic_signature_required'] = $this->isElectronicSignatureRequired(
        $channel
      );
    }

    if ($channel instanceof PhoneChannelResponse) {
      $values['phones'] = $this->mapPhoneNumbers(
        $this->getArrayFromGetter($localizedVersion, 'getPhoneNumbers')
      );
    }

    if ($channel instanceof PrintableFormChannelResponse) {
      $deliveryAddresses = $this->getArrayFromGetter(
        $channel,
        'getDeliveryAddresses'
      );

      $values['delivery_details'] = $this->mapDeliveryDetails(
        $deliveryAddresses,
        $language
      );

      $values['form_receiver'] = $this->mapFormReceiver(
        $deliveryAddresses,
        $language
      );

      $values['forms'] = $this->mapLinks(
        $this->getArrayFromGetter($localizedVersion, 'getFormFiles'),
        $name
      );
    }

    return $values;
  }

  /**
   * Gets the localized generated DTO for one channel language.
   */
  private function getLocalizedVersion(
    object $channel,
    string $language,
  ): ?object {
    $languageVersions = $this->getObjectFromGetter(
      $channel,
      'getLanguageVersions'
    );

    if ($languageVersions === NULL) {
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
   * Maps the channel organization name.
   */
  private function mapOrganizationName(
    object $channel,
    string $language,
  ): ?string {
    $organizationContentId = $this->getOptionalStringFromGetter(
      $channel,
      'getOrganizationContentId'
    );

    if ($organizationContentId === NULL) {
      return NULL;
    }

    return $this->organizationNameResolver->getName(
      $organizationContentId,
      $language
    );
  }

  /**
   * Maps an electronic signature requirement.
   */
  private function isElectronicSignatureRequired(object $channel): bool {
    $signature = $this->getObjectFromGetter(
      $channel,
      'getElectronicSignature'
    );

    return $signature !== NULL
      && $this->getOptionalStringFromGetter($signature, 'getType') === 'Required';
  }

  /**
   * Maps one localized web address to the current link field source format.
   *
   * @return array<int, array{uri: string, title: string}>|null
   *   Link field source values, or NULL when no usable URL exists.
   */
  private function mapWebAddress(
    ?object $webAddress,
    string $fallbackTitle,
  ): ?array {
    if ($webAddress === NULL) {
      return NULL;
    }

    $url = $this->getOptionalStringFromGetter($webAddress, 'getUrl');

    if ($url === NULL) {
      return NULL;
    }

    return [[
      'uri' => $url,
      'title' => $this->getOptionalStringFromGetter(
        $webAddress,
        'getName'
      ) ?? $fallbackTitle,
    ]];
  }

  /**
   * Maps link DTOs to the current link field source format.
   *
   * @param object[] $links
   *   Generated link DTOs.
   * @param string $fallbackTitle
   *   Fallback link title.
   *
   * @return array<int, array{uri: string, title: string}>|null
   *   Link field source values, or NULL when no usable links exist.
   */
  private function mapLinks(array $links, string $fallbackTitle): ?array {
    $mappedLinks = [];
    $index = 0;

    foreach ($links as $link) {
      if (!is_object($link)) {
        continue;
      }

      $url = $this->getOptionalStringFromGetter($link, 'getUrl');

      if ($url === NULL) {
        continue;
      }

      $index++;
      $title = $this->getOptionalStringFromGetter($link, 'getName');

      if ($title === NULL) {
        $title = $index === 1
          ? $fallbackTitle
          : $fallbackTitle . ' ' . $index;
      }

      $mappedLinks[$url] = [
        'uri' => $url,
        'title' => $title,
      ];
    }

    return $mappedLinks === [] ? NULL : array_values($mappedLinks);
  }

  /**
   * Maps email DTOs to the current email field source format.
   *
   * @param object[] $emails
   *   Generated email DTOs.
   *
   * @return string[]|null
   *   Email values, or NULL when no usable email exists.
   */
  private function mapEmails(array $emails): ?array {
    $mappedEmails = [];

    foreach ($emails as $email) {
      if (!is_object($email)) {
        continue;
      }

      $value = $this->getOptionalStringFromGetter($email, 'getEmail');

      if ($value !== NULL) {
        $mappedEmails[$value] = $value;
      }
    }

    return $mappedEmails === [] ? NULL : array_values($mappedEmails);
  }

  /**
   * Maps phone DTOs to the current telephone field source format.
   *
   * @param object[] $phoneNumbers
   *   Generated phone DTOs.
   *
   * @return array<int, array<string, string|null>>|null
   *   Telephone field source values, or NULL when no usable number exists.
   */
  private function mapPhoneNumbers(array $phoneNumbers): ?array {
    $mappedNumbers = [];

    foreach ($phoneNumbers as $phoneNumber) {
      if (!is_object($phoneNumber)) {
        continue;
      }

      $number = $this->getOptionalStringFromGetter(
        $phoneNumber,
        'getNumber'
      );

      if ($number === NULL) {
        continue;
      }

      $dialCode = $this->getOptionalStringFromGetter(
        $phoneNumber,
        'getDialCode'
      );

      $fullNumber = ($dialCode ?? '') . $number;

      $mappedNumbers[$fullNumber] = [
        'title' => $this->getOptionalStringFromGetter(
          $phoneNumber,
          'getAdditionalInformation'
        ),
        'label' => $this->getOptionalStringFromGetter(
          $phoneNumber,
          'getChargeDescription'
        ),
        'charged' => $this->getOptionalStringFromGetter(
          $phoneNumber,
          'getChargeType'
        ),
        'type' => $this->getOptionalStringFromGetter(
          $phoneNumber,
          'getType'
        ),
        'country_code' => $dialCode,
        'number' => $fullNumber,
      ];
    }

    return $mappedNumbers === [] ? NULL : array_values($mappedNumbers);
  }

  /**
   * Maps delivery information from printable form addresses.
   */
  private function mapDeliveryDetails(
    array $deliveryAddresses,
    string $language,
  ): ?string {
    $values = [];

    foreach ($deliveryAddresses as $deliveryAddress) {
      if (!is_object($deliveryAddress)) {
        continue;
      }

      $deliveryInformation = $this->getObjectFromGetter(
        $deliveryAddress,
        'getDeliveryInformation'
      );

      $value = $this->getLocalizedString(
        $deliveryInformation,
        $language
      );

      if ($value !== NULL) {
        $values[$value] = $value;
      }
    }

    return $values === [] ? NULL : implode("\n", array_values($values));
  }

  /**
   * Maps the first localized recipient from printable form addresses.
   */
  private function mapFormReceiver(
    array $deliveryAddresses,
    string $language,
  ): ?string {
    foreach ($deliveryAddresses as $deliveryAddress) {
      if (!is_object($deliveryAddress)) {
        continue;
      }

      $recipient = $this->getObjectFromGetter(
        $deliveryAddress,
        'getRecipient'
      );

      $value = $this->getLocalizedString($recipient, $language);

      if ($value !== NULL) {
        return $value;
      }
    }

    return NULL;
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

    return is_array($value) ? $value : [];
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

    foreach ($this->getArrayFromGetter($model, $getter) as $value) {
      if (!is_string($value)) {
        continue;
      }

      $value = trim($value);

      if ($value !== '') {
        $values[$value] = $value;
      }
    }

    return array_values($values);
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

    $value = $model->{$getter}();

    if (!is_string($value)) {
      return NULL;
    }

    $value = trim($value);

    return $value === '' ? NULL : $value;
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