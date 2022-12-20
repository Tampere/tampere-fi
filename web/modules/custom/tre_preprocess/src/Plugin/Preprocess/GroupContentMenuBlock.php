<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\node\NodeInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;

/**
 * Group content menu block preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.block__group_content_menu",
 *   hook = "block__group_content_menu"
 * )
 */
class GroupContentMenuBlock extends TrePreProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $group_front_page_details = $this->helperFunctions->getGroupFrontPageDetails();
    $current_node = $this->routeMatch->getParameter('node');

    if (isset($variables['content']['#items'])) {
      $variables['group_sidebar__has_content'] = TRUE;
    }
    else {
      $variables['group_sidebar__has_content'] = FALSE;
    }

    if (isset($variables['content']['#attributes']['region'])) {
      $variables['group_sidebar_region'] = $variables['content']['#attributes']['region'];
    }

    if ($current_node instanceof NodeInterface) {
      $node_id = $current_node->id();

      $variables['#cache']['tags'][] = "node:{$node_id}";
    }

    if (!isset($group_front_page_details)) {
      return $variables;
    }

    [$front_page_url, $front_page_node_id, $front_page_title] = $group_front_page_details;

    if (isset($node_id) && $front_page_node_id === $node_id) {
      $variables['group_front_page_link_extra_classes'] = ['is-active'];
    }
    else {
      $variables['group_front_page_link_extra_classes'] = [];
    }

    $variables['group_front_page_url'] = $front_page_url;
    $variables['group_front_page_title'] = $front_page_title;
    $variables['#cache']['tags'][] = "node:{$front_page_node_id}";

    return $variables;
  }

}
