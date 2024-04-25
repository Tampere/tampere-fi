<?php

namespace Drupal\tre_ptv_import\Service;

use Drupal\tre_ptv_import\PtvServiceSingleFetcherInterface;
use Tampere\PtvV11\PtvApi\ServiceApi;
use Tampere\PtvV11\PtvModel\V11VmOpenApiService;
use Tampere\PtvV11\PtvModel\V11VmOpenApiServicesWithPaging;

/**
 * An iterator class for PTV service definitions.
 */
class PtvServiceListIterator implements PtvServiceSingleFetcherInterface {

  /**
   * The current position of the iterator.
   *
   * @var int
   */
  protected int $position = 0;

  /**
   * A Swagger API instance capable of making paged requests to the API.
   *
   * @var \Tampere\PtvV11\PtvApi\ServiceApi
   */
  protected ServiceApi $apiConnection;

  /**
   * The paged API query result.
   *
   * @var \Tampere\PtvV11\PtvModel\V11VmOpenApiServicesWithPaging
   */
  protected V11VmOpenApiServicesWithPaging $model;

  /**
   * The area type to request.
   *
   * @var string
   */
  protected string $areaType;

  /**
   * The area code to request.
   *
   * @var string
   */
  protected string $areaCode;

  /**
   * Whether to include services concerning the whole country.
   *
   * Bool as string, so 'true' = true and 'false' = false.
   *
   * @var string
   */
  protected string $includeWholeCountry;

  /**
   * Whether to include general descriptions (GD) in the service data.
   *
   * Bool as string, so 'true' = true and 'false' = false.
   *
   * @var string
   */
  protected string $includeGeneralDescriptions;

  /**
   * Whether to include headers in the service data.
   *
   * Bool as string, so 'true' = true and 'false' = false.
   *
   * @var string
   */
  protected string $includeHeaders;

  /**
   * Storage for the PTV services fetched from the API.
   *
   * @var \Tampere\PtvV11\PtvModel\V11VmOpenApiService[]
   */
  protected array $items = [];

  /**
   * Storage for the count of pages from the API query.
   *
   * @var int
   */
  protected int $pageCount = 0;

  /**
   * Storage for the page in use for the paged result set of the API query.
   *
   * @var int
   */
  protected int $pageSize = 1;

  /**
   * Constructs the PTV service iterator.
   */
  public function __construct(ServiceApi $api_instance, string $area_type, string $area_code, string $include_whole_country, string $include_general_descriptions, string $include_headers) {
    $this->apiConnection = $api_instance;

    $this->areaType = $area_type;
    $this->areaCode = $area_code;
    $this->includeWholeCountry = $include_whole_country;
    $this->includeGeneralDescriptions = $include_general_descriptions;
    $this->includeHeaders = $include_headers;
  }

  /**
   * {@inheritdoc}
   */
  public function current(): mixed {
    return $this->getByListPosition($this->position);
  }

  /**
   * {@inheritdoc}
   */
  public function next(): void {
    $this->position++;
  }

  /**
   * {@inheritdoc}
   */
  public function key(): mixed {
    return $this->position;
  }

  /**
   * {@inheritdoc}
   */
  public function rewind(): void {
    $this->position = 0;
  }

  /**
   * Helper method for the iterator functionality.
   *
   * @param int $position
   *   The position from the list requested.
   *
   * @return \Tampere\PtvV11\PtvModel\V11VmOpenApiService|null
   *   The service definition from the given position in the list or NULL.
   */
  protected function getByListPosition(int $position): ?V11VmOpenApiService {
    if (empty($this->items)) {
      $this->getPageItems();
    }
    if (isset($this->items[$position])) {
      return $this->items[$position];
    }

    return NULL;
  }

  /**
   * Fetches services from the API.
   *
   * @throws \Tampere\PtvV11\ApiException
   *   If the API request fails.
   */
  protected function getPageItems(): void {
    $counter = 0;

    $page = 1;
    // Note: the API is very unstable and suffers foremost of timeouts.
    // Sometimes the timeouts are cut off at the server which is something we
    // can do nothing about. But at other times it is possible to make use of a
    // longer timeout at our end. Consider increasing the timeout from the
    // default of 30 sec by setting
    // $settings['http_client_config']['timeout'] in settings.php.
    while ($page == 1 || $this->model->getPageNumber() < $this->model->getPageCount()) {
      $this->model = $this->apiConnection->apiV11ServiceListAreaAreaCodeCodeGet($this->areaType,
        $this->areaCode,
        $this->includeWholeCountry,
        $this->includeGeneralDescriptions,
        $page,
        $this->includeHeaders);
      foreach ($this->model->getItemList() as $item) {
        $this->items[$counter++] = $item;
      }
      $page++;
    }

    $this->pageCount = $this->model->getPageCount();
    $this->pageSize = $this->model->getPageSize();
  }

  /**
   * {@inheritdoc}
   */
  public function valid(): bool {
    return !is_null($this->getByListPosition($this->position));
  }

  /**
   * {@inheritdoc}
   */
  public function getSingleServiceFromApi(string $uuid): V11VmOpenApiService {
    if ($this->includeGeneralDescriptions) {
      $response = $this->apiConnection->apiV11ServiceServiceWithGDIdGet($uuid, $this->includeHeaders);
      if ($response instanceof V11VmOpenApiService) {
        return $response;
      }
    }

    $response = $this->apiConnection->apiV11ServiceIdGet($uuid, $this->includeHeaders);
    if ($response instanceof V11VmOpenApiService) {
      return $response;
    }
  }

}
