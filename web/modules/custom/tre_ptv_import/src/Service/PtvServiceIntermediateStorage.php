<?php

namespace Drupal\tre_ptv_import\Service;

use Drupal\Component\Serialization\SerializationInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Site\Settings;
use Drupal\tre_ptv_import\PtvDataHelpersInterface;
use Drupal\tre_ptv_import\PtvServiceIntermediateStorageInterface;
use Tampere\PtvV11\PtvModel\V11VmOpenApiElectronicChannel;
use Tampere\PtvV11\PtvModel\V11VmOpenApiPhoneChannel;
use Tampere\PtvV11\PtvModel\V11VmOpenApiPrintableFormChannel;
use Tampere\PtvV11\PtvModel\V11VmOpenApiService;
use Tampere\PtvV11\PtvModel\V11VmOpenApiServiceLocationChannel;
use Tampere\PtvV11\PtvModel\V11VmOpenApiWebPageChannel;

/**
 * Storage facilities for PTV data.
 */
class PtvServiceIntermediateStorage implements PtvServiceIntermediateStorageInterface {

  const TAXONOMY_TERM_STORAGE_TABLE_FIELDS = [
    'id',
    'type',
    'name_fi',
    'name_en',
    'data',
  ];

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private Connection $db;

  /**
   * The iterator for PTV services.
   *
   * @var \Iterator
   */
  private \Iterator $serviceListIterator;

  /**
   * The iterator for PTV service channels.
   *
   * @var \Iterator
   */
  private \Iterator $serviceChannelIterator;

  /**
   * Cache for PTV services.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  private CacheBackendInterface $serviceCache;

  /**
   * Cache for PTV service channels.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  private CacheBackendInterface $serviceChannelCache;

  /**
   * PTV data helpers.
   *
   * @var \Drupal\tre_ptv_import\PtvDataHelpersInterface
   */
  private PtvDataHelpersInterface $dataHelpers;

  /**
   * The serialization service.
   *
   * @var \Drupal\Component\Serialization\SerializationInterface
   */
  private SerializationInterface $serialization;

  /**
   * Constructs the class instance.
   */
  public function __construct(Connection $database, PtvDataHelpersInterface $ptv_data_helpers, \Iterator $service_list_iterator, \Iterator $service_channel_iterator, CacheBackendInterface $service_cache, CacheBackendInterface $service_channel_cache, SerializationInterface $serialization) {
    $this->db = $database;
    $this->serviceListIterator = $service_list_iterator;
    $this->serviceChannelIterator = $service_channel_iterator;
    $this->serviceCache = $service_cache;
    $this->serviceChannelCache = $service_channel_cache;
    $this->dataHelpers = $ptv_data_helpers;
    $this->serialization = $serialization;
  }

  /**
   * {@inheritdoc}
   */
  public function getServicesFromStorage(): array {
    $query = $this->db->select('tre_ptv_import_service', 'service')->fields('service');
    $result = $query->execute()->fetchAll();
    $services = [];

    foreach ($result as $row) {
      $services[] = $this->serialization::decode($row->data);
    }

    return $services;
  }

  /**
   * {@inheritdoc}
   */
  public function getServiceFromStorage(string $uuid): ?V11VmOpenApiService {
    $query = $this->db->select('tre_ptv_import_service', 'service')->fields('service');
    $query->condition('uuid', $uuid);
    $result = $query->execute()->fetch();

    if (isset($result->data)) {
      return $this->serialization::decode($result->data);
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getServiceChannelsFromStorage(): array {
    $query = $this->db->select('tre_ptv_import_channel', 'channel')->fields('channel');
    $result = $query->execute()->fetchAll();
    $service_channels = [];

    foreach ($result as $row) {
      $service_channels[] = $this->serialization::decode($row->data);
    }

    return $service_channels;
  }

  /**
   * {@inheritdoc}
   */
  public function getServiceChannelFromStorageById(string $uuid): ?object {
    $query = $this->db->select('tre_ptv_import_channel', 'channel')->fields('channel');
    $query->condition('uuid', $uuid);
    $result = $query->execute()->fetch();

    if (isset($result->data)) {
      return $this->serialization::decode($result->data);
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getServiceChannelFromStorageByType(string $type): array {
    $query = $this->db->select('tre_ptv_import_channel', 'channel')->fields('channel');
    $query->condition('type', $type);
    $result = $query->execute()->fetchAll();
    $service_channels = [];

    foreach ($result as $row) {
      $service_channels[] = $this->serialization::decode($row->data);
    }

    return $service_channels;
  }

  /**
   * {@inheritdoc}
   */
  public function getServiceChannelsFromStorageByService(V11VmOpenApiService $service): array {
    $service_channel_ids = $this->getChannelIdsFromServices([$service]);
    if (empty($service_channel_ids)) {
      return [];
    }
    $query = $this->db->select('tre_ptv_import_channel', 'channel')->fields('channel');
    $query->condition('uuid', $service_channel_ids, 'IN');

    $result = $query->execute()->fetchAll();
    $service_channels = [];

    foreach ($result as $row) {
      $service_channels[] = $this->serialization::decode($row->data);
    }

    return $service_channels;
  }

  /**
   * {@inheritdoc}
   */
  public function getServiceChannelsFromStorageByServiceByType(V11VmOpenApiService $service, string $type): array {
    $service_channel_ids = $this->getChannelIdsFromServices([$service]);
    $query = $this->db->select('tre_ptv_import_channel', 'channel')->fields('channel');
    $query->condition('uuid', $service_channel_ids, 'IN');

    $result = $query->execute()->fetchAll();
    $service_channels = [];

    foreach ($result as $row) {
      if ($row->type === $type) {
        $service_channels[] = $this->serialization::decode($row->data);
      }
    }

    return $service_channels;
  }

  /**
   * {@inheritdoc}
   */
  public function wipePtvData() {
    $this->db->truncate('tre_ptv_import_channel')->execute();
    $this->db->truncate('tre_ptv_import_service')->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function getServicesFromApi(bool $refresh_cache = FALSE): array {
    $services = [];
    $services_cache = $this->serviceCache->get('tre_ptv_import_service_list_iterator');
    if ($refresh_cache || !$services_cache || !isset($services_cache->data) || !is_array($services_cache->data)) {
      foreach ($this->serviceListIterator as $service) {
        $services[] = $service;
      }

      $this->serviceCache->set('tre_ptv_import_service_list_iterator', $services);
    }
    return $services;
  }

  /**
   * {@inheritdoc}
   */
  public function insertServices(array $services) {
    $upsert_service_query = $this->db->upsert('tre_ptv_import_service');
    $upsert_service_query->fields(['uuid', 'data']);
    $upsert_service_query->key('uuid');
    foreach ($services as $service) {
      $service_record = [
        $service->getId(),
        $this->serialization::encode($service),
      ];

      $upsert_service_query->values($service_record);
    }
    $upsert_service_query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function getServiceChannelsForServicesFromApi(array $services, bool $refresh_cache = FALSE): array {
    $channel_ids = $this->getChannelIdsFromServices($services);

    $actual_channels = $this->getServiceChannelsByIdsFromApi($channel_ids, $refresh_cache);

    return $actual_channels;
  }

  /**
   * {@inheritdoc}
   */
  public function insertServiceChannels(array $channel_data) {
    if (empty($channel_data)) {
      return;
    }

    $first_channel = reset($channel_data);

    if (!$this->typeCheckServiceChannel($first_channel)) {
      throw new \InvalidArgumentException("Channel data does not contain the right kind of data.");
    }
    $upsert_service_channel_query = $this->db->upsert('tre_ptv_import_channel');
    $upsert_service_channel_query->fields(['uuid', 'type', 'data']);
    $upsert_service_channel_query->key('uuid');

    foreach ($channel_data as $channel) {
      $channel_record = [
        $channel->getId(),
        $channel->getModelName(),
        $this->serialization::encode($channel),
      ];

      $upsert_service_channel_query->values($channel_record);
    }

    $upsert_service_channel_query->execute();
  }

  /**
   * Validates that the given object is in one of the PTV service channel types.
   *
   * @param mixed $channel_candidate
   *   The object to check.
   *
   * @return bool
   *   TRUE if the given object is of one of the acceptable types.
   */
  private function typeCheckServiceChannel($channel_candidate) {
    $correct_type = ($channel_candidate instanceof V11VmOpenApiWebPageChannel) ||
      ($channel_candidate instanceof V11VmOpenApiPrintableFormChannel) ||
      ($channel_candidate instanceof V11VmOpenApiPhoneChannel) ||
      ($channel_candidate instanceof V11VmOpenApiServiceLocationChannel) ||
      ($channel_candidate instanceof V11VmOpenApiElectronicChannel);

    return $correct_type;
  }

  /**
   * {@inheritdoc}
   */
  public function getServiceChannelsByIdsFromApi(array $channel_ids, bool $refresh_cache): array {
    $channel_chunks = array_chunk($channel_ids, Settings::get('ptv_service_channel_import_batch_size', 20));
    $all_channels = [];

    foreach ($channel_chunks as $chunk) {
      $actual_channels = [];
      $chunk_hash = sha1($this->serialization::encode($chunk));
      $cid = 'tre_ptv_import.service_channel_list.' . $chunk_hash;
      $channels_chunk_cache = $this->serviceChannelCache->get($cid);

      if ($refresh_cache || !$channels_chunk_cache || !isset($channels_chunk_cache->data) || !is_array($channels_chunk_cache->data)) {
        $this->serviceChannelIterator->setGuids($chunk);

        foreach ($this->serviceChannelIterator as $service_channel_channels_object) {
          $channel_model = $this->dataHelpers::getServiceChannelObjectFromServiceChannelsObject($service_channel_channels_object);
          $actual_channels[] = $channel_model;
        }

        $this->serviceChannelCache->set($cid, $actual_channels);
      }
      else {
        $actual_channels = $channels_chunk_cache->data;
      }
      $all_channels = array_merge($all_channels, $actual_channels);
    }
    return $all_channels;
  }

  /**
   * Gets Service channel IDs from each of the Service classes.
   *
   * The results can be used in fetching the channel data from the API in
   * separate requests.
   *
   * @param \Tampere\PtvV11\PtvModel\V11VmOpenApiService[] $services
   *   The services to fetch the channel data for.
   *
   * @return string[]
   *   The Service channel UUIDs.
   */
  private function getChannelIdsFromServices(array $services): array {
    $channel_ids = [];
    foreach ($services as $service) {
      foreach ($service->getServiceChannels() as $channel_channel) {
        $channel_id = $channel_channel->getServiceChannel()->getId();
        // Ensure uniqueness by indexing by ID.
        $channel_ids[$channel_id] = $channel_id;
      }
    }
    return $channel_ids;
  }

  /**
   * {@inheritdoc}
   */
  public function insertKeywordsSourceData(array $services) {
    $keyword_source_upsert_query = $this->db->upsert('tre_ptv_import_ontology_term');
    $keyword_source_upsert_query->fields(self::TAXONOMY_TERM_STORAGE_TABLE_FIELDS);
    $keyword_source_upsert_query->key('id');

    foreach ($services as $service) {
      foreach ($service->getOntologyTerms() as $term) {
        $term_finnish_names = $this->dataHelpers::getOpenApiLanguageItemStringsByLanguage($term->getName(), 'fi');
        $term_english_names = $this->dataHelpers::getOpenApiLanguageItemStringsByLanguage($term->getName(), 'en');
        $finnish_name = !empty($term_finnish_names) ? current($term_finnish_names)->getValue() : NULL;
        $english_name = !empty($term_english_names) ? current($term_english_names)->getValue() : NULL;

        $row = [
          $term->getUri(),
          $term->getModelName(),
          $finnish_name,
          $english_name,
          $this->serialization::encode($term),
        ];

        $keyword_source_upsert_query->values($row);
      }
    }

    $keyword_source_upsert_query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function insertTopicsSourceData(array $services) {
    $topics_source_upsert_query = $this->db->upsert('tre_ptv_import_service_class');
    $topics_source_upsert_query->fields(self::TAXONOMY_TERM_STORAGE_TABLE_FIELDS);
    $topics_source_upsert_query->key('id');

    foreach ($services as $service) {
      foreach ($service->getServiceClasses() as $class) {
        $class_finnish_names = $this->dataHelpers::getOpenApiLanguageItemStringsByLanguage($class->getName(), 'fi');
        $class_english_names = $this->dataHelpers::getOpenApiLanguageItemStringsByLanguage($class->getName(), 'en');
        $finnish_name = !empty($class_finnish_names) ? current($class_finnish_names)->getValue() : NULL;
        $english_name = !empty($class_english_names) ? current($class_english_names)->getValue() : NULL;

        $row = [
          $class->getNewUri(),
          $class->getModelName(),
          $finnish_name,
          $english_name,
          $this->serialization::encode($class),
        ];

        $topics_source_upsert_query->values($row);
      }
    }

    $topics_source_upsert_query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function insertLifeSituationSourceData(array $services) {
    $life_situation_source_upsert_query = $this->db->upsert('tre_ptv_import_target_groups_and_life_event');
    $life_situation_source_upsert_query->fields(self::TAXONOMY_TERM_STORAGE_TABLE_FIELDS);
    $life_situation_source_upsert_query->key('id');

    foreach ($services as $service) {
      foreach ($service->getTargetGroups() as $group) {
        $group_finnish_names = $this->dataHelpers::getOpenApiLanguageItemStringsByLanguage($group->getName(), 'fi');
        $group_english_names = $this->dataHelpers::getOpenApiLanguageItemStringsByLanguage($group->getName(), 'en');
        $finnish_name = !empty($group_finnish_names) ? current($group_finnish_names)->getValue() : NULL;
        $english_name = !empty($group_english_names) ? current($group_english_names)->getValue() : NULL;

        $row = [
          $group->getNewUri(),
          $group->getModelName(),
          $finnish_name,
          $english_name,
          $this->serialization::encode($group),
        ];

        $life_situation_source_upsert_query->values($row);
      }

      foreach ($service->getLifeEvents() as $event) {
        $event_finnish_names = $this->dataHelpers::getOpenApiLanguageItemStringsByLanguage($event->getName(), 'fi');
        $event_english_names = $this->dataHelpers::getOpenApiLanguageItemStringsByLanguage($event->getName(), 'en');
        $finnish_name = !empty($event_finnish_names) ? current($event_finnish_names)->getValue() : NULL;
        $english_name = !empty($event_english_names) ? current($event_english_names)->getValue() : NULL;

        $row = [
          $event->getNewUri(),
          $event->getModelName(),
          $finnish_name,
          $english_name,
          $this->serialization::encode($event),
        ];

        $life_situation_source_upsert_query->values($row);
      }
    }

    $life_situation_source_upsert_query->execute();
  }

}
