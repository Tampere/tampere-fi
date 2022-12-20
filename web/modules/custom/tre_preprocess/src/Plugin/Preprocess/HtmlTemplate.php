<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\group\Entity\GroupInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;

/**
 * HTML document template preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess_preprocess.preprocess.html",
 *   hook = "html"
 * )
 */
class HtmlTemplate extends TrePreProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $current_group = $this->helperFunctions->getCurrentGroup();
    if ($current_group instanceof GroupInterface) {
      $palette_class = $this->helperFunctions->getGroupPaletteClass($current_group);

      $variables['attributes']['class'][] = $palette_class;
    }

    $route_name = $this->routeMatch->getRouteName();

    if ($route_name == "view.search.page_1") {
      $current_path = $this->currentPath->getPath();
      $group_id_from_path = $this->helperFunctions->getGroupIdFromSearchViewPath($current_path);

      if (!$group_id_from_path) {
        return $variables;
      }

      $group = $this->entityTypeManager->getStorage('group')->load($group_id_from_path);
      if ($group instanceof GroupInterface) {
        $palette_class = $this->helperFunctions->getGroupPaletteClass($group);
        $variables['attributes']['class'][] = $palette_class;
      }
    }

    return $variables;
  }

}
