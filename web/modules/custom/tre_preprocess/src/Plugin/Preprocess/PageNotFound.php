<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\config_pages\Entity\ConfigPages;
use Drupal\tre_preprocess\TrePreProcessPluginBase;

/**
 * Page 404 preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.page__404",
 *   hook = "page__404"
 * )
 */
class PageNotFound extends TrePreProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {

    $page_404_config_page = ConfigPages::config('404_page_content');

    if (!is_null($page_404_config_page)) {
      if (!$page_404_config_page->get('field_page_content')->isEmpty()) {
        $variables['not_found_page_content'] = $page_404_config_page->get('field_page_content')->view('default');
      }
    }

    $this->renderer->addCacheableDependency($variables, $page_404_config_page);

    return $variables;
  }

}
