<?php

namespace Drupal\tre_ptv_import;

use Tampere\PtvV11\PtvModel\V11VmOpenApiService;

/**
 * Defines an interface for fetching indicidual services from PTV API.
 */
interface PtvServiceSingleFetcherInterface extends \Iterator {

  /**
   * Fetches a single service from the PTV API by ID.
   *
   * @param string $uuid
   *   The ID of the service to fetch.
   *
   * @return \Tampere\PtvV11\PtvModel\V11VmOpenApiService
   *   The PTV service data corresponding to the ID.
   *
   * @throws \Tampere\PtvV11\ApiException
   *   If no service is found by the ID or if the ID is malformatted.
   */
  public function getSingleServiceFromApi(string $uuid): V11VmOpenApiService;

}
