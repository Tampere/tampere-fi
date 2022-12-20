<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;

/**
 * Popup card pattern preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.pattern_popup_card",
 *   hook = "pattern_popup_card"
 * )
 */
class PopupCardPattern extends TrePreProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $pattern_context = $variables['context'];

    $node = $pattern_context->getProperty('entity');

    if (!($node instanceof NodeInterface)) {
      return $variables;
    }

    $popup_card_url = NULL;
    /** @var \Drupal\node\NodeInterface $translated_node */
    $translated_node = $this->entityRepository->getTranslationFromContext($node);
    if ($translated_node->hasField('field_links')) {
      // The popup card should not link anywhere if the 'field_links' field
      // exists but has no value as the field is used in nodes that should
      // not be visible to the end-users.
      if (!$translated_node->get('field_links')->isEmpty()) {
        // The client has requested that the first link be used
        // as the page URL if 'field_links' exists.
        $popup_card_url = Url::fromUri($translated_node->get('field_links')[0]->uri)->toString();

        $node_id = $node->id();
        $variables['#cache']['tags'][] = "node:{$node_id}";
      }
    }
    else {
      $popup_card_url = $translated_node->toUrl()->toString(TRUE)->getGeneratedUrl();
    }

    $variables['popup_card_url'] = $popup_card_url;

    return $variables;
  }

}
