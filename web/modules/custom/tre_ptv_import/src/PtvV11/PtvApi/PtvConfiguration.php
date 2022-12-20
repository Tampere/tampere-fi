<?php

namespace Drupal\tre_ptv_import\PtvV11\PtvApi;

use Tampere\PtvV11\Configuration;

/**
 * Wrapper for getting API config with a hostname set.
 */
class PtvConfiguration extends Configuration {

  /**
   * Constructs the configuration object.
   */
  public function __construct(string $api_hostname) {
    parent::__construct();
    $this->setHost($api_hostname);
  }

}
