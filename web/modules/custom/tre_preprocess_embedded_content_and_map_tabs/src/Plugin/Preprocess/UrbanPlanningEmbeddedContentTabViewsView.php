<?php

namespace Drupal\tre_preprocess_embedded_content_and_map_tabs\Plugin\Preprocess;

/**
 * Urban planning embedded content tab views view preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.views_view__ub_embedded_content_tab",
 *   hook = "views_view__urban_planning_embedded_content_tab"
 * )
 */
class UrbanPlanningEmbeddedContentTabViewsView extends EmbeddedContentTabViewsView {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $view = $variables['view'];
    $variables['total_rows'] = $view->total_rows;
    $variables['current_page_rows'] = count($view->result);
    return $variables;
  }

}
