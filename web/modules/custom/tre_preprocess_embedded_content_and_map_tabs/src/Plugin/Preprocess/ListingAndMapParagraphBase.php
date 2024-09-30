<?php

namespace Drupal\tre_preprocess_embedded_content_and_map_tabs\Plugin\Preprocess;

use Drupal\Core\Site\Settings;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;
use Drupal\tre_preprocess_utility_functions\Utils\HelperFunctions;
use Drupal\views\Views;

/**
 * Base class for listing and map type paragraphs.
 */
abstract class ListingAndMapParagraphBase extends TrePreProcessPluginBase {

  protected const CONTENT_TYPE_SPECIFIC_TAXONOMY_VOCABULARIES = [];

  /**
   * The view mode to use for the paragraph content in the map view by default.
   */
  protected const DEFAULT_MAP_ITEM_VIEW_MODE = 'map_tab_card_view_mode';

  /**
   * The view mode to use for the paragraph content in the list view by default.
   */
  protected const DEFAULT_LIST_ITEM_VIEW_MODE = 'content_tab_card_view_mode';

  /**
   * The id of the view to use for the content listing.
   */
  protected const VIEW_ID = '';

  /**
   * Returns node IDs that match the taxonomy terms given as parameter.
   *
   * @param string $current_language_id
   *   The ID for the currently active user interface language.
   * @param array $taxonomy_values
   *   An array of taxonomy terms keyed by taxonomy vocabulary.
   * @param array $content_type_specific_taxonomy_values
   *   An array of content type specific taxonomy terms key'd by taxonomy
   *   vocabulary.
   * @param array $content_types
   *   The content types to include in the results.
   * @param string $condition_group
   *   The condition group to use for combining the taxonomy terms.
   * @param array $extra_conditions
   *   An array of extra conditions to add in the query, if any. The format for
   *   one condition is noted below.
   *
   * @code
   *   [
   *     'field' => '<field_name>',
   *     'value' => '<value>',
   *     'operator' => '<operator>'
   *   ]
   * @endcode
   *
   * @return array|null
   *   An array of node IDs if successful. Null otherwise.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getNodeIds(
    string $current_language_id,
    array $taxonomy_values,
    array $content_type_specific_taxonomy_values,
    array $content_types,
    string $condition_group = 'or',
    array $extra_conditions = [],
  ): ?array {

    if (empty($content_types)) {
      return NULL;
    }

    $node_query = $this->entityTypeManager->getStorage('node')->getQuery()->accessCheck(TRUE);

    $has_content_type_specific_taxonomy_values_selected = !(count($content_type_specific_taxonomy_values, COUNT_RECURSIVE) <= count(array_keys($content_type_specific_taxonomy_values)));
    if ($has_content_type_specific_taxonomy_values_selected) {
      $content_types_with_selected_content_type_specific_taxonomy_values = [];
      foreach ($content_type_specific_taxonomy_values as $taxonomy => $terms) {
        if (!empty($terms)) {
          $content_type_with_specific_taxonomy_values = static::CONTENT_TYPE_SPECIFIC_TAXONOMY_VOCABULARIES[$taxonomy]['content_type'];
          $field_name = static::CONTENT_TYPE_SPECIFIC_TAXONOMY_VOCABULARIES[$taxonomy]['field_name'];

          $content_types_with_selected_content_type_specific_taxonomy_values[] = $content_type_with_specific_taxonomy_values;

          $orCondition = $node_query->orConditionGroup();
          $orCondition->condition('type', $content_type_with_specific_taxonomy_values, '<>');
          $orCondition->condition($field_name, $terms, 'IN', $current_language_id);
          $node_query->condition($orCondition);
        }
      }
    }

    // If AND has been selected for the condition group and there are content
    // type specific taxonomy values selected, only the content types connected
    // with those terms should be shown in the results.
    if ($condition_group === 'and' && $has_content_type_specific_taxonomy_values_selected) {
      $node_query->condition('type', $content_types_with_selected_content_type_specific_taxonomy_values, 'IN');
    }
    else {
      $node_query->condition('type', $content_types, 'IN');
    }

    $node_query
      ->condition('status', NodeInterface::PUBLISHED)
      ->condition('langcode', $current_language_id)
      ->range(0, 400)
      ->sort('title', 'ASC');

    $no_taxonomy_terms_selected = (count($taxonomy_values, COUNT_RECURSIVE) <= count(array_keys($taxonomy_values)));
    if ($no_taxonomy_terms_selected) {
      return $node_query->execute();
    }

    $orCondition = NULL;
    if ($condition_group == 'or') {
      $orCondition = $node_query->orConditionGroup();
    }

    foreach ($taxonomy_values as $taxonomy => $terms) {
      if (!empty($terms)) {
        if ($condition_group === 'and') {
          foreach ($terms as $term) {
            $node_query->condition($node_query->andConditionGroup()
              ->condition("field_{$taxonomy}", $term, '=', $current_language_id));
          }
        }
        elseif (!empty($orCondition)) {
          $orCondition->condition("field_{$taxonomy}", $terms, 'IN', $current_language_id);
        }
      }
    }

    foreach ($extra_conditions as $condition) {
      if ($condition_group === 'and') {
        $node_query->condition($condition['field'], $condition['value'], $condition['operator'], $current_language_id);
      }
      elseif (!empty($orCondition)) {
        $orCondition->condition($condition['field'], $condition['value'], $condition['operator'], $current_language_id);
      }
    }

    if (!empty($orCondition)) {
      $node_query->condition($orCondition);
    }

    return $node_query->execute();
  }

  /**
   * Returns a map as a render array for the given paragraph.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph displaying the map.
   *
   * @return array
   *   A map as a render array if successful. Empty array otherwise.
   */
  protected function getMap(ParagraphInterface $paragraph) {
    $map = [];

    if (!($paragraph instanceof ParagraphInterface)) {
      return $map;
    }

    $paragraph_id = $paragraph->id();
    $paragraph_title = $paragraph->get('field_title')->getString();
    $iframe_src_url = Settings::get('mml_map_base_url');

    $map = [
      'frame' => [
        '#type' => 'inline_template',
        '#attached' => [
          'library' => [
            'tre_preprocess_embedded_content_and_map_tabs/rpc',
          ],
        ],
        '#template' => '<iframe id="{{ iframe_id }}" data-paragraph="{{ paragraph_id }}" data-ignore-cookie-blocking="true" title="{% trans %} Map: {{ title }} {% endtrans %}" src="{{ url }}" allow="geolocation" style="border: none; width: 100%; height: 100%;"></iframe>',
        '#context' => [
          'iframe_id' => 'map-' . $paragraph_id,
          'paragraph_id' => $paragraph_id,
          'url' => $iframe_src_url,
          'title' => $paragraph_title,
        ],
      ],
    ];

    return $map;
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

  /**
   * Renders a certain view based on paragraph values.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph entity to base the view on.
   * @param array $node_ids
   *   The IDs of the nodes to display in the view.
   *
   * @return array|null
   *   The view as a renderable array if successful. Null if unsuccessful.
   */
  protected function getRenderedView(ParagraphInterface $paragraph, array $node_ids) : ?array {
    $view = Views::getView(static::VIEW_ID);
    $block_machine_name = $this->getViewDisplayId($paragraph);

    if (empty($view) || !$view->access($block_machine_name)) {
      return NULL;
    }

    $view->setDisplay($block_machine_name);

    $nid_argument = implode('+', $node_ids);

    $view->setArguments([$nid_argument]);

    if (
      $paragraph->hasField('field_description_text')
      && !$paragraph->get('field_description_text')->isEmpty()
    ) {
      $paragraph_description = $paragraph->get('field_description_text')->getString();

      $options = [
        'id' => 'area_text_custom',
        'table' => 'views',
        'field' => 'area_text_custom',
        'relationship' => 'none',
        'group_type' => 'none',
        'admin_label' => '',
        'empty' => TRUE,
        'tokenize' => FALSE,
        'content' => $paragraph_description,
        'plugin_id' => 'text_custom',
      ];

      $view->setHandler($block_machine_name, 'footer', 'area_text_custom', $options);
    }

    $view->preExecute();
    $view->execute();
    $view->postExecute();
    $renderable = $view->buildRenderable();

    if ($renderable) {
      $this->renderer->addCacheableDependency($renderable, $view);
    }

    return $renderable;
  }

  /**
   * Gets the correct machine name for the display to be used in the list view.
   *
   * The display name is based solely on the data in the paragraph.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph used to deduce the name of the view display.
   *
   * @return string
   *   The id of the display in the content listing view.
   */
  abstract protected function getViewDisplayId(ParagraphInterface $paragraph);

}
