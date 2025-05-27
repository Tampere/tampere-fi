<?php

namespace Drupal\tre_cludo_search\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Displays Cludo search page.
 */
class CludoSearchRouteController extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public function content() {
    return [
      '#theme' => 'cludo_search',
      '#search_results_page' => TRUE,
    ];
  }
}