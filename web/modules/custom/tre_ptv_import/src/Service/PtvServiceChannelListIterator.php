<?php

namespace Drupal\tre_ptv_import\Service;

use Drupal\tre_ptv_import\PtvV11\PtvApi\CorrectedServiceChannelApi;

/**
 * An iterator class for PTV service channel definitions.
 */
class PtvServiceChannelListIterator implements \Iterator {

  /**
   * The current position of the iterator.
   *
   * @var int
   */
  protected int $position = 0;

  /**
   * A Swagger API instance capable of making paged requests to the API.
   *
   * @var \Drupal\tre_ptv_import\PtvV11\PtvApi\CorrectedServiceChannelApi
   */
  protected CorrectedServiceChannelApi $apiConnection;

  /**
   * The GUIDs to use in the API request.
   *
   * @var string[]
   */
  protected array $guids;

  /**
   * Whether to include headers in the service channel data.
   *
   * Bool as string, so 'true' = true and 'false' = false.
   *
   * @var string
   */
  protected string $includeHeaders;

  /**
   * Array of service channel data.
   *
   * Array that consists of objects of following types:
   * - \Tampere\PtvV11\PtvModel\V11VmOpenApiElectronicChannel
   * - \Tampere\PtvV11\PtvModel\V11VmOpenApiServiceLocationChannel
   * - \Tampere\PtvV11\PtvModel\V11VmOpenApiPhoneChannel
   * - \Tampere\PtvV11\PtvModel\V11VmOpenApiPrintableFormChannel
   * - \Tampere\PtvV11\PtvModel\V11VmOpenApiWebPageChannel.
   *
   * @var array
   */
  protected array $items = [];

  /**
   * Constructs the ServiceChannel iterator.
   *
   * Note: for the iteration to work properly, a list of ServiceChannel GUIDs
   * must be set using the ::setGuids method.
   */
  public function __construct(CorrectedServiceChannelApi $api_instance, string $include_headers) {
    $this->apiConnection = $api_instance;

    $this->includeHeaders = $include_headers;
  }

  /**
   * Sets the GUIDs of the service channels to request from the API.
   *
   * @param string[] $guids
   *   The GUIDs of the service channels to request.
   *
   * @throws \Tampere\PtvV11\ApiException
   *   If the API request fails.
   */
  public function setGuids(array $guids) {
    if (count($guids) > 100) {
      throw new \InvalidArgumentException("Too many guids at once.");
    }
    $this->guids = $guids;
    $this->getPageItems();
  }

  /**
   * {@inheritdoc}
   */
  public function current() {
    return $this->getByListPosition($this->position);
  }

  /**
   * {@inheritdoc}
   */
  public function next() {
    $this->position++;
  }

  /**
   * {@inheritdoc}
   */
  public function key() {
    return $this->position;
  }

  /**
   * {@inheritdoc}
   */
  public function rewind() {
    $this->position = 0;
  }

  /**
   * {@inheritdoc}
   */
  public function valid() {
    return !is_null($this->getByListPosition($this->position));
  }

  /**
   * Helper method for the iterator functionality.
   *
   * @param int $position
   *   The position from the list requested.
   *
   * @return mixed|null
   *   An object of one of the following types, or NULL:
   *   - \Tampere\PtvV11\PtvModel\V11VmOpenApiElectronicChannel
   *   - \Tampere\PtvV11\PtvModel\V11VmOpenApiServiceLocationChannel
   *   - \Tampere\PtvV11\PtvModel\V11VmOpenApiPhoneChannel
   *   - \Tampere\PtvV11\PtvModel\V11VmOpenApiPrintableFormChannel
   *   - \Tampere\PtvV11\PtvModel\V11VmOpenApiWebPageChannel.
   */
  protected function getByListPosition(int $position) {
    if (isset($this->items[$position])) {
      return $this->items[$position];
    }

    return NULL;
  }

  /**
   * Fetches service channels from the API.
   *
   * @throws \Tampere\PtvV11\ApiException
   *   If the API request fails.
   */
  protected function getPageItems() {
    $this->items = $this->apiConnection->apiV11ServiceChannelListGet($this->guids, $this->includeHeaders);
  }

}
