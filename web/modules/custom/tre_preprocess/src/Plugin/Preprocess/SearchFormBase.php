<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\node\NodeInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;
use Drupal\views\Entity\View;

/**
 * Base class for all search form blocks' preprocessing.
 */
class SearchFormBase extends TrePreProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $view = View::load('search')->getExecutable();
    $view->setDisplay('page_1');
    $url = $view->getDisplay()->getUrl()->toString();
    $query = $view->getExposedInput();

    $variables['action'] = $url;
    $variables['query'] = $query;

    $current_path = $this->currentPath->getPath();
    // Get group_id in current path for search view page.
    $group_id = $this->helperFunctions->getGroupIdFromSearchViewPath($current_path);
    // If $group_id is null, fallbacks to fetching current group.
    $variables['use_search_toggle'] = $this->helperFunctions->isMinisite($group_id);

    $current_node = $this->routeMatch->getParameter('node');
    if ($current_node instanceof NodeInterface) {
      $node_id = $current_node->id();

      $variables['#cache']['tags'][] = "node:{$node_id}";
    }

    return $variables;
  }

}
