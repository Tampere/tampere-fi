<?php

namespace Drupal\tre_preprocess_embedded_content_and_map_tabs\Plugin\Preprocess;

use Drupal\node\NodeInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;
use Drupal\tre_preprocess_utility_functions\Utils\HelperFunctions;

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
  const DEFAULT_LIST_ITEM_VIEW_MODE_FORMATTED_DESCRIPTION = 'content_tab_card_view_mode_formatted_description';

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

    // Create a copy of the view to be able to re-render it to
    // fetch all items. All items are needed to show all locations.
    $view_all_locations = \Drupal\views\Views::getView($variables['view']->id());
    $view_all_locations->setDisplay($variables['view']->current_display);

    // Set the pager to zero to fetch all rows.
    $view_all_locations->setItemsPerPage(0);
    $view_all_locations->args = $variables['view']->args;
    $view_all_locations->execute();
    $all_rows = $view_all_locations->result;
    $all_nodes_id = [];
    
    foreach ($all_rows as $row) {
      $node = $row->_entity;
      if (!($node instanceof NodeInterface)) {
        continue;
      }
      $all_nodes_id[] = $node->id();
    }

    $tab_list_content = [];
    $item_counter = 1;
    $rows = $variables['rows'];
    foreach ($rows as $row) {
      $node = $row['content']['#row']->_entity;
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

      if ($translated_node->hasField('field_description_toggle') && $translated_node->get('field_description_toggle')->value) {
        $node_view_mode = self::DEFAULT_LIST_ITEM_VIEW_MODE_FORMATTED_DESCRIPTION;
      }

      $translated_node_content = $this->entityTypeManager->getViewBuilder('node')->view($translated_node, $node_view_mode);

      $tab_list_content_item_id = $node_id . $item_counter;

      // Check that each item is unique.
      if (!in_array($node_id, array_column($tab_list_content, 'nid'))) {
        $tab_list_content[] = [
          'id' => $tab_list_content_item_id,
          'nid' => $node_id,
          'dl_heading' => $translated_node_title,
          'dl_content' => $translated_node_content,
        ];

        $item_counter++;
      }

    }

    $tab_list_node_ids = [];
    foreach ($tab_list_content as $item) {
      array_push($tab_list_node_ids, $item['nid']);
    }

    $view = $variables['view'];
    $container_paragraph_id = $view->element['#attached']['drupalSettings']['container_paragraph_id'];
    $filtered_locations = $this->getLocationData($all_nodes_id, $container_paragraph_id);

    // Because the view is in AJAX mode, when interacting with the view,
    // i.e. changing the filters, only the view gets re-rendered,
    // not the parent paragraph. For that reason, everything that comes
    // from the paragraph to the view, i.e. paragraph ID, won't be avaible
    // after the view is re-rendered. So here we use the view Dom Id to pass
    // the related locations to the frontend.
    if (!empty($container_paragraph_id)) {
      $variables['#attached']['drupalSettings']['tampere']['embeddedContentAndMapTabs']['paragraphToViewHashTable'][$container_paragraph_id] = $view->dom_id;
    }

    // Because after re-rendering, it doesn't have access to exsiting locations,
    // we keep the history of all locations based on timestamps.
    $variables['#attached']['drupalSettings']['tampere']['embeddedContentAndMapTabs']['filteredLocations'][$view->dom_id][time()] = $filtered_locations;

    $variables['tab_list_content'] = $tab_list_content;

    return $variables;
  }

  /**
   * Gets location data for nodes by their IDs.
   *
   * @param array $node_ids
   *   The IDs of the nodes.
   * @param mixed $map_id
   *   The ID of the map to use in determining uniqueness of coordinates.
   *
   * @return array
   *   An array of location arrays, each keyed with 'nid', 'latitude', and
   *   'longitude'.
   */
  protected function getLocationData(array $node_ids, mixed $map_id): array {
    $query = $this->db->select('node__field_epsg_3067_point_strings', 'points');

    $query->fields('points', ['entity_id'])
      ->fields('points', ['field_epsg_3067_point_strings_value'])
      ->condition('points.entity_id', $node_ids, 'IN');

    $result = $query->execute()->fetchAll();

    $locations = [];

    foreach ($result as $location) {
      [$longitude, $latitude] = explode(' ', $location->field_epsg_3067_point_strings_value, 2);
      [$longitude, $latitude] = HelperFunctions::getUniqueCoordinates($longitude, $latitude, (string) $map_id);
      $locations[] = [
        'nid' => $location->entity_id,
        'latitude' => $latitude,
        'longitude' => $longitude,
      ];
    }

    return $locations;
  }

}
