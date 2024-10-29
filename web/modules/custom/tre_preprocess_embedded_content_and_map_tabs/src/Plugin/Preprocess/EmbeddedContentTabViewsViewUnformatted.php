<?php

namespace Drupal\tre_preprocess_embedded_content_and_map_tabs\Plugin\Preprocess;

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
  const LIST_ITEM_VIEW_MODE_WITH_HOURS = 'content_tab_card_with_hours_view_mode';

  protected const DISPLAY_IDS = [
    "content_listing_block",
    "content_listing_block_without_area_filter",
  ];

  protected const DISPLAY_WITH_HOURS_IDS = [
    "content_listing_block_with_hours",
    "content_listing_block_without_area_filter_with_hours",
  ];

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    if (!in_array($variables['view']->current_display, array_merge(static::DISPLAY_IDS, static::DISPLAY_WITH_HOURS_IDS), TRUE)) {
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

      $content_type = $translated_node->bundle();
      $node_available_view_modes = $this->entityDisplayRepository->getViewModeOptionsByBundle('node', $content_type);

      $node_view_mode = self::DEFAULT_LIST_ITEM_VIEW_MODE;

      // If the node does not have a List view with hours,
      // it should fall back to the default List view.
      if (in_array($variables['view']->current_display, static::DISPLAY_WITH_HOURS_IDS)
          && array_key_exists(self::LIST_ITEM_VIEW_MODE_WITH_HOURS, $node_available_view_modes)) {
        $node_view_mode = self::LIST_ITEM_VIEW_MODE_WITH_HOURS;
      }

      $translated_node_content = $this->entityTypeManager->getViewBuilder('node')->view($translated_node, $node_view_mode);

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
