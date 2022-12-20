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
use Tampere\PtvV11\PtvModel\V11VmOpenApiElectronicChannel;
use Tampere\PtvV11\PtvModel\V11VmOpenApiPhoneChannel;
use Tampere\PtvV11\PtvModel\V11VmOpenApiPrintableFormChannel;
use Tampere\PtvV11\PtvModel\V11VmOpenApiWebPageChannel;

/**
 * Plugin class for source plugin for PTV Service Channels.
 *
 * @MigrateSource(
 *   id = "ptv_service_channels"
 * )
 */
class PtvServiceChannels extends SqlBase {

  const SERVICE_CHANNEL_TYPE_ELECTRIC = 'EChannel';
  const SERVICE_CHANNEL_TYPE_WEB = 'WebPage';
  const SERVICE_CHANNEL_TYPE_PHONE = 'Phone';
  const SERVICE_CHANNEL_TYPE_FORM = 'PrintableForm';

  /**
   * The database table to use in the source data query.
   */
  const TABLE = 'tre_ptv_import_channel';

  /**
   * The class name that represents the service locations amongst all channels.
   *
   * As service locations are handled separately from the other channels,
   * this is used to exclude the locations from the channels handled here.
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
    $query = $this->select(static::TABLE, 'channel')
      ->fields('channel');

    $query->where('channel.type <> :type', [':type' => static::SERVICE_LOCATION_TYPE]);

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = [
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
      'languages' => '(array, optional) Array of strings (language codes) for the languages the service channel is available in.',
      'areas_text' => '(string, formatted text) Formatted text requiring <ul> and <h3> element support from the text format, listing areas for the service.',
      'organization' => '(string, optional) The name of the organization.',
      'regular_daily_hours_hours' => '(array, optional) The regular daily opening hours.',
      'regular_overnight_hours_hours' => '(array, optional) The regular overnight opening hours.',
      'exception_hours_info' => '(string, optional) The exception hours.',
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
        'alias' => 'service_channel',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $service_channel = $this->serialization::decode($row->getSourceProperty('data'));

    if (!($service_channel instanceof V11VmOpenApiElectronicChannel) && !($service_channel instanceof V11VmOpenApiPhoneChannel) && !($service_channel instanceof V11VmOpenApiPrintableFormChannel) && !($service_channel instanceof V11VmOpenApiWebPageChannel)) {
      return FALSE;
    }

    $values = self::mangleServiceChannelIntoSourceValues(
      $service_channel,
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
   * Transforms service channel objects into source row data.
   *
   * @param \Tampere\PtvV11\PtvModel\V11VmOpenApiPhoneChannel|\Tampere\PtvV11\PtvModel\V11VmOpenApiPrintableFormChannel|\Tampere\PtvV11\PtvModel\V11VmOpenApiElectronicChannel|\Tampere\PtvV11\PtvModel\V11VmOpenApiWebPageChannel $service_channel
   *   The service channel object to transform.
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
  public static function mangleServiceChannelIntoSourceValues(
    $service_channel,
    string $language,
    PtvDataHelpersInterface $data_helpers,
    PtvServiceIntermediateStorageInterface $ptv_storage,
    OrganizationApi $organization_api_connection,
    CacheBackendInterface $organizationNameCache,
    TimeInterface $timeService
  ): array {
    $values = [
      'uuid' => $service_channel->getId(),
    ];

    $values['name'] = $data_helpers::getDescriptionStringByLanguageAndType($service_channel->getServiceChannelNames(), $language, 'Name');

    if (empty($values['name'])) {
      return [];
    }

    $values['description'] = $data_helpers::getDescriptionStringByLanguageAndType($service_channel->getServiceChannelDescriptions(), $language, 'Description');
    $values['summary'] = $data_helpers::getDescriptionStringByLanguageAndType($service_channel->getServiceChannelDescriptions(), $language, 'Summary');

    $service_channel_type = $service_channel->getServiceChannelType();
    $values['service_channel_type'] = $service_channel_type;

    if ($service_channel_type === self::SERVICE_CHANNEL_TYPE_ELECTRIC || $service_channel_type === self::SERVICE_CHANNEL_TYPE_FORM) {
      $values['attachments'] = $data_helpers::processWebPages($service_channel->getAttachments(), $language);
    }

    if ($service_channel_type === self::SERVICE_CHANNEL_TYPE_ELECTRIC || $service_channel_type === self::SERVICE_CHANNEL_TYPE_WEB) {
      $values['accessibility'] = self::processAccessibilityLinks($service_channel->getAccessibilityClassification(), $language);
    }

    if ($service_channel_type === self::SERVICE_CHANNEL_TYPE_ELECTRIC) {
      $values['electronic_signature_required'] = $service_channel->getRequiresSignature();
      $values['electronic_id_required'] = $service_channel->getRequiresAuthentication();
    }

    if ($service_channel_type === self::SERVICE_CHANNEL_TYPE_FORM) {
      $address_types_with_processors = PtvDataHelpersInterface::ADDRESS_TYPE_PROCESSORS;

      $service_channel_delivery_addresses = $service_channel->getDeliveryAddresses();

      $addresses_by_type = [];

      foreach (array_keys($address_types_with_processors) as $address_type) {
        $addresses_by_type[$address_type] = array_filter($service_channel_delivery_addresses, function ($addr) use ($address_type) {
          return 'Postal.' . $addr->getSubType() == $address_type;
        });
      }

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
          'language' => $language,
          'data_helpers' => $data_helpers,
        ];
        array_walk($addresses, $processor_function, $processor_additional_data);
        $values[$values_key] = array_merge($values[$values_key], $addresses);
      }

      $values['delivery_details'] = self::processDeliveryAddressTexts($service_channel_delivery_addresses, $language, $data_helpers);
      $values['form_receiver'] = self::processDeliveryAddressReceiver($service_channel_delivery_addresses, $language, $data_helpers);
      $values['forms'] = self::processFormLinks($service_channel->getChannelUrls(), $language);
    }

    $values['web_pages'] = $data_helpers::processWebPages($service_channel->getWebPages(), $language);

    $support_phone_numbers = $service_channel->getSupportPhones();
    if (!empty($support_phone_numbers)) {
      $values['support_phones'] = $data_helpers::processPhoneNumbers($support_phone_numbers, $language);
    }

    $support_emails = $service_channel->getSupportEmails();
    if (!empty($support_emails)) {
      $values['support_emails'] = $data_helpers::processEmails($support_emails, $language);
    }

    if ($service_channel_type === self::SERVICE_CHANNEL_TYPE_PHONE) {
      $phone_numbers = $service_channel->getPhoneNumbers();
      $values['phones'] = $data_helpers::processPhoneNumbers($phone_numbers, $language);
    }

    $values['languages'] = $service_channel->getLanguages();

    $values['areas_text'] = $data_helpers::processAreas($service_channel->getAreas(), $language);

    $values['organization'] = $data_helpers::getOrganizationNameByLanguage(
      $service_channel->getOrganizationId(),
      $language,
      $organization_api_connection,
      $organizationNameCache,
      $timeService
    );

    $opening_hours_types_with_processors = [
      'DaysOfTheWeek' => [
        'processor' => 'processRegularOpeningHours',
        'value type' => 'regular_daily_hours',
      ],
      'OverMidnight' => [
        'processor' => 'processRegularOpeningHours',
        'value type' => 'regular_overnight_hours',
      ],
      'Exceptional' => [
        'processor' => 'processExceptionalOpeningHours',
        'value type' => 'exception_hours',
      ],
    ];

    $service_hours = $service_channel->getServiceHours();
    $data_helpers->processServiceHours($values, $opening_hours_types_with_processors, $service_hours, $language);

    return $values;
  }

  /**
   * Process accessibility classifications for placement in link field.
   *
   * @param \Tampere\PtvV11\PtvModel\VmOpenApiAccessibilityClassification[] $accessibility_classifications
   *   The accessibility classifications associated with a service channel,
   *   accessible by the 'getAccessibilityClassification()' method.
   * @param string $language
   *   The language to use for extracting language-aware texts.
   *
   * @return array|null
   *   Accessibility statement web pages in an array if successful,
   *   otherwise null.
   */
  public static function processAccessibilityLinks(array $accessibility_classifications, string $language): ?array {
    $link_list = [];

    $link_data_in_language = array_filter($accessibility_classifications, function ($accessibility_classification) use ($language) {
      return $accessibility_classification->getLanguage() == $language;
    });

    foreach ($link_data_in_language as $accessibility_classification) {
      $accessibility_statement_web_page = $accessibility_classification->getAccessibilityStatementWebPage();

      if (empty($accessibility_statement_web_page)) {
        continue;
      }

      $link_list[] = [
        'title' => $accessibility_classification->getAccessibilityStatementWebPageName(),
        'uri' => $accessibility_statement_web_page,
      ];
    }

    return empty($link_list) ? NULL : $link_list;
  }

  /**
   * Process delivery address texts.
   *
   * @param \Tampere\PtvV11\PtvModel\V8VmOpenApiAddressDelivery[] $delivery_addresses
   *   The delivery addresses for a form service channel,
   *   accessible by the 'getDeliveryAddresses()' method.
   * @param string $language
   *   The language to use for extracting language-aware texts.
   * @param \Drupal\tre_ptv_import\PtvDataHelpersInterface $data_helpers
   *   The PtvDataHelpers service to use.
   *
   * @return string
   *   Delivery address texts as a string.
   */
  public static function processDeliveryAddressTexts(array $delivery_addresses, string $language, PtvDataHelpersInterface $data_helpers): string {
    $all_delivery_details = [];

    foreach ($delivery_addresses as $delivery_address) {
      $delivery_address_in_text = $delivery_address->getDeliveryAddressInText();

      if (!empty($delivery_address_in_text)) {
        $delivery_details = array_map(function ($item) {
          return $item->getValue();
        }, $data_helpers::getOpenApiLanguageItemStringsByLanguage($delivery_address_in_text, $language));

        $all_delivery_details[] = implode("\n", $delivery_details);
      }
    }

    return implode("\n", $all_delivery_details);
  }

  /**
   * Process delivery address receiver.
   *
   * @param \Tampere\PtvV11\PtvModel\V8VmOpenApiAddressDelivery[] $delivery_addresses
   *   The delivery addresses for a form service channel,
   *   accessible by the 'getDeliveryAddresses()' method.
   * @param string $language
   *   The language to use for extracting language-aware texts.
   * @param \Drupal\tre_ptv_import\PtvDataHelpersInterface $data_helpers
   *   The PtvDataHelpers service to use.
   *
   * @return string
   *   Delivery address receiver as a string.
   */
  public static function processDeliveryAddressReceiver(array $delivery_addresses, string $language, PtvDataHelpersInterface $data_helpers): string {
    $all_delivery_receivers = [];
    $index = 0;

    if (!empty($delivery_addresses)) {
      while (count($all_delivery_receivers) == 0) {
        $delivery_receiver = $delivery_addresses[$index]->getReceiver();

        if (!empty($delivery_receiver)) {
          $delivery_details = array_map(function ($item) {
            return $item->getValue();
          }, $data_helpers::getOpenApiLanguageItemStringsByLanguage($delivery_receiver, $language));

          $all_delivery_receivers[] = implode(", ", $delivery_details);
        }
        $index++;
      }
    }

    return implode("\n", $all_delivery_receivers);
  }

  /**
   * Process form URLs for placement in multi-value link field.
   *
   * @param \Tampere\PtvV11\PtvModel\VmOpenApiLocalizedListItem[] $form_urls
   *   The form URLs associated with a service, accessible by the
   *   'getChannelUrls()' method.
   * @param string $language
   *   The language to use for extracting language-aware texts.
   *
   * @return array|null
   *   Form links in an array if successful, otherwise null.
   */
  public static function processFormLinks(array $form_urls, string $language): ?array {
    $form_links = [];

    $form_url_data_in_language = array_filter($form_urls, function ($form_url) use ($language) {
      return $form_url->getLanguage() == $language;
    });

    foreach ($form_url_data_in_language as $form_url) {
      $form_links[] = [
        'uri' => $form_url->getValue(),
      ];
    }

    return empty($form_links) ? NULL : $form_links;
  }

}
