<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\node\NodeInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;

/**
 * Embedded content tab unformatted views view preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.view__embedded_content_tab",
 *   hook = "views_view_unformatted__embedded_content_tab"
 * )
 */
class EmbeddedContentTabViewsViewUnformatted extends TrePreProcessPluginBase {

  /**
   * The view mode to use for the paragraph content in the list view by default.
   */
  const DEFAULT_LIST_ITEM_VIEW_MODE = 'content_tab_card_view_mode';

  protected const DISPLAY_IDS = [
    "content_listing_block",
    "content_listing_block_without_area_filter",
  ];

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    if (!in_array($variables['view']->current_display, self::DISPLAY_IDS, TRUE)) {
      return $variables;
    }
    $tab_list_content = [];
    $item_counter = 1;
    $rows = $variables['rows'];
    foreach ($rows as $row) {
      $node = $row['content']['#node'];
      if (!($node instanceof NodeInterface)) {
        continue;
      }
      $node_id = $node->id();
      /** @var \Drupal\node\NodeInterface $translated_node */
      $translated_node = $this->entityRepository->getTranslationFromContext($node);
      $translated_node_title = $translated_node->getTitle();
      $translated_node_content = $this->entityTypeManager->getViewBuilder('node')->view($translated_node, self::DEFAULT_LIST_ITEM_VIEW_MODE);

      $tab_list_content_item_id = $node_id . $item_counter;

      $tab_list_content[] = [
        'id' => $tab_list_content_item_id,
        'dl_heading' => $translated_node_title,
        'dl_content' => $translated_node_content,
      ];

      $item_counter++;
    }

    $variables['tab_list_content'] = $tab_list_content;
    return $variables;
  }

}
