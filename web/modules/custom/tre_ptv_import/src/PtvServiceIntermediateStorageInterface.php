<?php

namespace Drupal\tre_ptv_import;

use Tampere\PtvV11\PtvModel\V11VmOpenApiService;

/**
 * Interface for communication methods with PTV API and intermediate storage.
 */
interface PtvServiceIntermediateStorageInterface {

  const SERVICE_CHANNEL_ELECTRONIC = 'V11VmOpenApiElectronicChannel';
  const SERVICE_CHANNEL_PHONE = 'V11VmOpenApiPhoneChannel';
  const SERVICE_CHANNEL_FORM = 'V11VmOpenApiPrintableFormChannel';
  const SERVICE_CHANNEL_LOCATION = 'V11VmOpenApiServiceLocationChannel';
  const SERVICE_CHANNEL_WEB = 'V11VmOpenApiWebPageChannel';

  /**
   * Fetches all PTV services from intermediate storage.
   *
   * @return \Tampere\PtvV11\PtvModel\V11VmOpenApiService[]
   *   The Service definitions.
   */
  public function getServicesFromStorage(): array;

  /**
   * Fetches a single PTV service from intermediate storage by UUID.
   *
   * @param string $uuid
   *   The UUID of the PTV service.
   *
   * @return \Tampere\PtvV11\PtvModel\V11VmOpenApiService
   *   The service by the given UUID.
   */
  public function getServiceFromStorage(string $uuid): ?V11VmOpenApiService;

  /**
   * Fetches all PTV service channels from intermediate storage.
   *
   * @return array
   *   Array that consists of objects of following types:
   *   - \Tampere\PtvV11\PtvModel\V11VmOpenApiElectronicChannel
   *   - \Tampere\PtvV11\PtvModel\V11VmOpenApiServiceLocationChannel
   *   - \Tampere\PtvV11\PtvModel\V11VmOpenApiPhoneChannel
   *   - \Tampere\PtvV11\PtvModel\V11VmOpenApiPrintableFormChannel
   *   - \Tampere\PtvV11\PtvModel\V11VmOpenApiWebPageChannel
   */
  public function getServiceChannelsFromStorage(): array;

  /**
   * Fetches a single PTV service channel from intermediate storage by UUID.
   *
   * @param string $uuid
   *   The UUID of the PTV service channel.
   *
   * @return \Tampere\PtvV11\PtvModel\V11VmOpenApiElectronicChannel|\Tampere\PtvV11\PtvModel\V11VmOpenApiPhoneChannel|\Tampere\PtvV11\PtvModel\V11VmOpenApiPrintableFormChannel|\Tampere\PtvV11\PtvModel\V11VmOpenApiServiceLocationChannel|\Tampere\PtvV11\PtvModel\V11VmOpenApiWebPageChannel
   *   The service channel.
   */
  public function getServiceChannelFromStorageById(string $uuid): ?object;

  /**
   * Fetches PTV service channels of given type from intermediate storage.
   *
   * @param string $type
   *   The type of service channel to fetch. One of the SERVICE_CHANNEL_*
   *   constants.
   *
   * @return array
   *   Array that consists of objects of one of the following types:
   *   - \Tampere\PtvV11\PtvModel\V11VmOpenApiElectronicChannel
   *   - \Tampere\PtvV11\PtvModel\V11VmOpenApiServiceLocationChannel
   *   - \Tampere\PtvV11\PtvModel\V11VmOpenApiPhoneChannel
   *   - \Tampere\PtvV11\PtvModel\V11VmOpenApiPrintableFormChannel
   *   - \Tampere\PtvV11\PtvModel\V11VmOpenApiWebPageChannel
   */
  public function getServiceChannelFromStorageByType(string $type): array;

  /**
   * Fetches PTV service channels of for a given service from storage.
   *
   * @param \Tampere\PtvV11\PtvModel\V11VmOpenApiService $service
   *   The PTV service to fetch the service channels for.
   *
   * @return array
   *   Array that consists of objects of following types:
   *   - \Tampere\PtvV11\PtvModel\V11VmOpenApiElectronicChannel
   *   - \Tampere\PtvV11\PtvModel\V11VmOpenApiServiceLocationChannel
   *   - \Tampere\PtvV11\PtvModel\V11VmOpenApiPhoneChannel
   *   - \Tampere\PtvV11\PtvModel\V11VmOpenApiPrintableFormChannel
   *   - \Tampere\PtvV11\PtvModel\V11VmOpenApiWebPageChannel
   */
  public function getServiceChannelsFromStorageByService(V11VmOpenApiService $service): array;

  /**
   * Fetches PTV service channels of given type for a service from storage.
   *
   * @param \Tampere\PtvV11\PtvModel\V11VmOpenApiService $service
   *   The PTV service to fetch the service channels for.
   * @param string $type
   *   The type of service channel to fetch. One of the SERVICE_CHANNEL_*
   *   constants.
   *
   * @return array
   *   Array that consists of objects of one of the following types:
   *   - \Tampere\PtvV11\PtvModel\V11VmOpenApiElectronicChannel
   *   - \Tampere\PtvV11\PtvModel\V11VmOpenApiServiceLocationChannel
   *   - \Tampere\PtvV11\PtvModel\V11VmOpenApiPhoneChannel
   *   - \Tampere\PtvV11\PtvModel\V11VmOpenApiPrintableFormChannel
   *   - \Tampere\PtvV11\PtvModel\V11VmOpenApiWebPageChannel
   */
  public function getServiceChannelsFromStorageByServiceByType(V11VmOpenApiService $service, string $type): array;

  /**
   * Wipes all the PTV data from intermediate storage.
   */
  public function wipePtvData();

  /**
   * Fetches all PTV service data from API or cache.
   *
   * @param bool $refresh_cache
   *   Whether to get the data afresh from the API (TRUE) or to use the cached
   *   data (FALSE).
   *
   * @return \Tampere\PtvV11\PtvModel\V11VmOpenApiService[]
   *   The PTV services.
   */
  public function getServicesFromApi(bool $refresh_cache = FALSE): array;

  /**
   * Inserts or updates given services in the intermediate storage.
   *
   * @param \Tampere\PtvV11\PtvModel\V11VmOpenApiService[] $services
   *   The PTV services to insert.
   */
  public function insertServices(array $services);

  /**
   * Gets the Service channel data for the Service models from the API.
   *
   * Sadly there is no common interface in the Swagger-codegen-generated model
   * for the API responses, so the documentation of this method is a tad
   * convoluted.
   *
   * @param \Tampere\PtvV11\PtvModel\V11VmOpenApiService[] $services
   *   The services to fetch the channel data for.
   * @param bool $refresh_cache
   *   Whether to get the data afresh from the API (TRUE) or to use the cached
   *   data (FALSE). Defaults to FALSE.
   *
   * @return array
   *   Array that consists of objects of following types:
   *   - \Tampere\PtvV11\PtvModel\V11VmOpenApiElectronicChannel
   *   - \Tampere\PtvV11\PtvModel\V11VmOpenApiServiceLocationChannel
   *   - \Tampere\PtvV11\PtvModel\V11VmOpenApiPhoneChannel
   *   - \Tampere\PtvV11\PtvModel\V11VmOpenApiPrintableFormChannel
   *   - \Tampere\PtvV11\PtvModel\V11VmOpenApiWebPageChannel
   */
  public function getServiceChannelsForServicesFromApi(array $services, bool $refresh_cache = FALSE): array;

  /**
   * Fetches Service channel data from PTV API for the given channel IDs.
   *
   * @param array $channel_ids
   *   The UUIDs of the channels.
   * @param bool $refresh_cache
   *   Whether to get the data afresh from the API (TRUE) or to use the cached
   *   data (FALSE).
   *
   * @return array
   *   Array that consists of objects of following types:
   *   - \Tampere\PtvV11\PtvModel\V11VmOpenApiElectronicChannel
   *   - \Tampere\PtvV11\PtvModel\V11VmOpenApiServiceLocationChannel
   *   - \Tampere\PtvV11\PtvModel\V11VmOpenApiPhoneChannel
   *   - \Tampere\PtvV11\PtvModel\V11VmOpenApiPrintableFormChannel
   *   - \Tampere\PtvV11\PtvModel\V11VmOpenApiWebPageChannel
   */
  public function getServiceChannelsByIdsFromApi(array $channel_ids, bool $refresh_cache): array;

  /**
   * Inserts or updates channels into the intermediate storage.
   *
   * @param array $channel_data
   *   Array that consists of objects of following types:
   *   - \Tampere\PtvV11\PtvModel\V11VmOpenApiElectronicChannel
   *   - \Tampere\PtvV11\PtvModel\V11VmOpenApiServiceLocationChannel
   *   - \Tampere\PtvV11\PtvModel\V11VmOpenApiPhoneChannel
   *   - \Tampere\PtvV11\PtvModel\V11VmOpenApiPrintableFormChannel
   *   - \Tampere\PtvV11\PtvModel\V11VmOpenApiWebPageChannel.
   */
  public function insertServiceChannels(array $channel_data);

  /**
   * Inserts or updates service ontology terms into the intermediate storage.
   *
   * @param \Tampere\PtvV11\PtvModel\V11VmOpenApiService[] $services
   *   The services to for which to insert the ontology terms.
   */
  public function insertKeywordsSourceData(array $services);

  /**
   * Inserts or updates service classes into the intermediate storage.
   *
   * @param \Tampere\PtvV11\PtvModel\V11VmOpenApiService[] $services
   *   The services to for which to insert the service classes.
   */
  public function insertTopicsSourceData(array $services);

  /**
   * Inserts or updates target groups and life events from services in storage.
   *
   * @param \Tampere\PtvV11\PtvModel\V11VmOpenApiService[] $services
   *   The services to for which to insert the terms.
   */
  public function insertLifeSituationSourceData(array $services);

  /**
   * Fetches specific services from PTV API by UUIDs.
   *
   * @param string[] $uuids
   *   The UUIDs for the services to fetch.
   *
   * @return \Tampere\PtvV11\PtvModel\V11VmOpenApiService[]
   *   The services found with the UUIDs, keyed by the UUID. If an UUID produces
   *   no results from the API, it will not be present in the returned array at
   *   all.
   */
  public function getSpecificServicesFromApi(array $uuids): array;

}
