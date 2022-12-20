<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\node\NodeInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;

/**
 * Internal link with link text paragraph preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.paragraph__internal_link_with_link_text",
 *   hook = "paragraph__internal_link_with_link_text"
 * )
 */
class InternalLinkWithLinkText extends TrePreProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $paragraph = $variables['paragraph'];

    if (!$paragraph->get('field_link_text')->isEmpty()) {
      $link_text = $paragraph->get('field_link_text')->getString();

      $variables['link_text'] = $link_text;
    }

    if (!$paragraph->get('field_internal_link')->isEmpty()) {
      $referenced_entities = $paragraph->get('field_internal_link')->referencedEntities();
      $referenced_node = reset($referenced_entities);

      if ($referenced_node instanceof NodeInterface) {
        /** @var \Drupal\node\NodeInterface $translated_node */
        $translated_node = $this->entityRepository->getTranslationFromContext($referenced_node);

        $referenced_node_id = $translated_node->id();
        $variables['#cache']['tags'][] = "node:{$referenced_node_id}";

        $node_is_published = $translated_node->isPublished();
        if (!$node_is_published) {
          return $variables;
        }

        $link_url = $translated_node->toUrl()->toString();

        $variables['link_url'] = $link_url;
      }
    }

    return $variables;
  }

}
