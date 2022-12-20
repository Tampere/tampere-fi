<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\node\NodeInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;

/**
 * Link list paragraph preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.paragraph__link_list",
 *   hook = "paragraph__link_list"
 * )
 */
class LinkList extends TrePreProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $paragraph = $variables['paragraph'];
    $links = $paragraph->get('field_links')->referencedEntities();
    $link_array = [];

    // Go through all the links.
    foreach ($links as $link_item) {

      // External link.
      if ($link_item->hasField('field_external_link_w_link_text') && !$link_item->get('field_external_link_w_link_text')->isEmpty()) {
        $external_link = $link_item->get('field_external_link_w_link_text');

        $external_link_url = $this->helperFunctions->getExternalLinkFieldUrl($external_link);
        $external_link_title = $this->helperFunctions->getExternalLinkFieldTitle($external_link);

        $link_array[] = [
          'name' => $external_link_title,
          'link_url' => $external_link_url,
          'icon_name' => 'external',
        ];
      }

      // Internal link.
      if ($link_item->hasField('field_internal_link') && !$link_item->get('field_internal_link')->isEmpty()) {
        // Get link url.
        $referenced_entities = $link_item->get('field_internal_link')->referencedEntities();
        $link_node = reset($referenced_entities);

        if (!($link_node instanceof NodeInterface)) {
          continue;
        }

        /** @var \Drupal\node\NodeInterface $translated_link_node */
        $translated_link_node = $this->entityRepository->getTranslationFromContext($link_node);

        $variables['#cache']['tags'][] = "node:{$link_node->id()}";

        $node_is_published = $translated_link_node->isPublished();
        if (!$node_is_published) {
          continue;
        }

        $url = $translated_link_node->toUrl()->toString();

        // Get link text.
        $link_text = '';
        if (!$link_item->get('field_link_text')->isEmpty()) {
          $link_text = $link_item->get('field_link_text')->getValue();
        }

        // Create link array.
        $link = [
          'name' => $link_text[0]['value'],
          'link_url' => $url,
          'icon_name' => 'arrow',
        ];

        // Push link array in array.
        $link_array[] = $link;
      }
    }
    $variables['link_array'] = $link_array;

    return $variables;
  }

}
