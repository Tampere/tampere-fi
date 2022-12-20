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

    $node_url = $node->toUrl()->setAbsolute(TRUE)->toString();

    $link_text = preg_replace("(^https?://)", "", $node_url);

    $variables['link_text'] = $link_text;
    $variables['link_url'] = $node_url;

    return $variables;
  }

}
