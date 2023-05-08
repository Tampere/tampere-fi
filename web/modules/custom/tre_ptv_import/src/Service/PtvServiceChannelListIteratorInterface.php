<?php

namespace Drupal\tre_ptv_import\Service;

/**
 * Interface for Service Channel listings.
 */
interface PtvServiceChannelListIteratorInterface extends \Iterator {

  /**
   * Sets the UUIDs to use in fetching the service channel data from the API.
   *
   * @param string[] $guids
   *   An array of UUID strings of the service channels to fetch.
   */
  public function setGuids(array $guids): void;

}
