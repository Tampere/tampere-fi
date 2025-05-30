<?php

namespace Drupal\tre_preprocess_embedded_content_and_map_tabs\Plugin\Preprocess;

use Drupal\tre_preprocess\TrePreProcessPluginBase;

/**
 * Embedded content tab views view preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.views_view__embedded_content_tab",
 *   hook = "views_view__embedded_content_tab"
 * )
 */
class EmbeddedContentTabViewsView extends TrePreProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $view = $variables['view'];

    // The description is passed as the second contextual argument.
    $variables['footer'] = $view->args[1] ?? '';
    $variables['total_rows'] = $view->total_rows;
    $variables['current_page_rows'] = count($view->result);
    return $variables;
  }

}
