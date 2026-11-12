<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\node\NodeInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;

/**
 * Node link DS field preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.field__node_link",
 *   hook = "field__node_link"
 * )
 */
class NodeLinkField extends TrePreProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    /** @var \Drupal\node\NodeInterface $node */
    $node = $variables['element']['#object'];

    if (!($node instanceof NodeInterface)) {
      return $variables;
    }

    // An unsaved node has no ID, so it has no URL (e.g. rendering in presave).
    if ($node->isNew()) {
      return $variables;
    }

    $node_url = $node->toUrl()->setAbsolute(TRUE)->toString();

    $variables['link_text'] = $node->label();
    $variables['link_url'] = $node_url;

    return $variables;
  }

}
