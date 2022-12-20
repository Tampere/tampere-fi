<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\node\NodeInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;

/**
 * Cta pattern preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.breadcrumb",
 *   hook = "breadcrumb"
 * )
 */
class Breadcrumb extends TrePreProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $current_node = $this->routeMatch->getParameter('node');

    if (!($current_node instanceof NodeInterface) || $current_node->bundle() !== 'content_in_other_language') {
      return $variables;
    }

    $content_language = $current_node->get('field_language')->getString();
    $variables['content_language'] = $content_language;

    return $variables;
  }

}
