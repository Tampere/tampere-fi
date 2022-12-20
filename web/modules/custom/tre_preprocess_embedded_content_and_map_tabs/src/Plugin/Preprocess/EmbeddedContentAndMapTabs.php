<?php

namespace Drupal\tre_preprocess_embedded_content_and_map_tabs\Plugin\Preprocess;

use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;
use Drupal\Core\Site\Settings;
use Drupal\tre_preprocess_utility_functions\Utils\HelperFunctionsInterface;

/**
 * Embedded content and map tabs paragraph type preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.paragraph__embedded_content_and_map_tabs",
 *   hook = "paragraph__embedded_content_and_map_tabs"
 * )
 */
class EmbeddedContentAndMapTabs extends TrePreProcessPluginBase {

  /**
   * The view mode to use for the paragraph content in the list view by default.
   */
  const DEFAULT_LIST_ITEM_VIEW_MODE = 'content_tab_card_view_mode';

  /**
   * The view mode to use for the paragraph content in the map view by default.
   */
  const DEFAULT_MAP_ITEM_VIEW_MODE = 'map_tab_card_view_mode';

  /**
   * The taxonomy vocabularies for the listing.
   */
  const AVAILABLE_TAXONOMY_VOCABULARIES = [
    'topics',
    'keywords',
    'life_situations',
    'geographical_areas',
  ];

  /**
   * The content type specific taxonomy vocabularies for the listing.
   */
  const CONTENT_TYPE_SPECIFIC_TAXONOMY_VOCABULARIES = [
    'zoning_information_types' => [
      'content_type' => 'zoning_information',
      'field_name' => 'field_plan_type',
    ],
  ];

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $paragraph = $variables['paragraph'];
    $container_paragraph_id = $paragraph->id();

    $current_language_id = $this->languageManager->getCurrentLanguage()->getId();

    if ($paragraph instanceof ParagraphInterface) {
      /** @var \Drupal\paragraphs\ParagraphInterface $translated_paragraph */
      $translated_paragraph = $this->entityRepository->getTranslationFromContext($paragraph);

      $paragraph_displayed_content_types_field_values = $translated_paragraph->get('field_tabs_displayed_content_tps')->getValue();
      $selected_content_types = $this->helperFunctions->getListFieldValues($paragraph_displayed_content_types_field_values);

      $selected_taxonomy_condition_group = $translated_paragraph->get('field_taxonomy_combination')->getString();

      $selected_taxonomy_values = $this->helperFunctions->getParagraphTaxonomyTerms($translated_paragraph, self::AVAILABLE_TAXONOMY_VOCABULARIES);
      $selected_content_type_specific_taxonomy_values = $this->helperFunctions->getParagraphTaxonomyTerms($translated_paragraph, array_keys(self::CONTENT_TYPE_SPECIFIC_TAXONOMY_VOCABULARIES));

      $tab_list_node_ids = $this->getEmbeddedContentAndMapTabsNodeIds(
        $current_language_id,
        $selected_taxonomy_values,
        $selected_content_type_specific_taxonomy_values,
        $selected_content_types,
        $selected_taxonomy_condition_group,
      );

      $tab_list_nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($tab_list_node_ids);

      foreach ($selected_content_types as $content_type) {
        $variables['#cache']['tags'][] = "node_list:{$content_type}";
      }

      $all_locations = [];
      $tab_list_content = [];
      $tab_map_nodes = [];
      $item_counter = 1;
      foreach ($tab_list_nodes as $node_id => $tab_list_node) {
        if (!($tab_list_node instanceof NodeInterface)) {
          return $variables;
        }

        /** @var \Drupal\node\NodeInterface $translated_node */
        $translated_node = $this->entityRepository->getTranslationFromContext($tab_list_node);
        $translated_node_title = $translated_node->getTitle();
        $translated_node_content = $this->entityTypeManager->getViewBuilder('node')->view($translated_node, self::DEFAULT_LIST_ITEM_VIEW_MODE);

        $locations_for_node = $this->getLocations($translated_node);
        if (!empty($locations_for_node)) {
          $all_locations = array_merge($all_locations, $locations_for_node);

          $map_node_content = $this->entityTypeManager->getViewBuilder('node')->view($translated_node, self::DEFAULT_MAP_ITEM_VIEW_MODE);
          $tab_map_nodes[] = [
            'nid' => $node_id,
            'content' => $map_node_content,
          ];
        }

        $tab_list_content_item_id = $container_paragraph_id . $node_id . $item_counter;

        $tab_list_content[] = [
          'id' => $tab_list_content_item_id,
          'dl_heading' => $translated_node_title,
          'dl_content' => $translated_node_content,
        ];

        $item_counter++;
      }

      if (!empty($tab_map_nodes)) {
        $variables['tab_map_nodes'] = $tab_map_nodes;
        $variables['tab_map_content'] = $this->getMap($translated_paragraph);

        $variables['#attached']['drupalSettings']['tampere']['embeddedContentAndMapTabs']['locations'][$container_paragraph_id] = $all_locations;
        $variables['#attached']['drupalSettings']['tampere']['mmlMapIframeDomain'] = Settings::get('mml_map_iframe_domain');
      }

      $variables['tab_list_content'] = $tab_list_content;
    }
    return $variables;
  }

  /**
   * Returns an array of location coordinates for the given node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to get the locations for.
   *
   * @return array
   *   The location coordinates as an array when they exist,
   *   empty array otherwise.
   */
  protected function getLocations(NodeInterface $node) {
    $locations = [];

    if (!($node instanceof NodeInterface)) {
      return $locations;
    }

    if (!$node->hasField('field_addresses') && !$node->hasField(HelperFunctionsInterface::LATITUDE_FIELD_NAME) && !$node->hasField(HelperFunctionsInterface::LONGITUDE_FIELD_NAME)) {
      return $locations;
    }

    $node_id = $node->id();
    $node_references_content_with_location_data = $node->hasField('field_addresses') && !$node->get('field_addresses')->isEmpty();
    $node_has_latitude_value = $node->hasField(HelperFunctionsInterface::LATITUDE_FIELD_NAME) && !$node->get(HelperFunctionsInterface::LATITUDE_FIELD_NAME)->isEmpty();
    $node_has_longitude_value = $node->hasField(HelperFunctionsInterface::LONGITUDE_FIELD_NAME) && !$node->get(HelperFunctionsInterface::LONGITUDE_FIELD_NAME)->isEmpty();
    $node_has_location_data = $node_has_latitude_value && $node_has_longitude_value;
    if ($node_references_content_with_location_data) {
      // The 'field_addresses' field holds references to 'map_point' content
      // that store information about locations.
      $location_nodes = $node->get('field_addresses')->referencedEntities();

      foreach ($location_nodes as $location_node) {
        if ($location_node instanceof NodeInterface) {
          $location_node_has_latitude_value = $location_node->hasField(HelperFunctionsInterface::LATITUDE_FIELD_NAME) && !$location_node->get(HelperFunctionsInterface::LATITUDE_FIELD_NAME)->isEmpty();
          $location_node_has_longitude_value = $location_node->hasField(HelperFunctionsInterface::LONGITUDE_FIELD_NAME) && !$location_node->get(HelperFunctionsInterface::LONGITUDE_FIELD_NAME)->isEmpty();
          if ($location_node_has_latitude_value && $location_node_has_longitude_value) {
            $longitude = $location_node->get(HelperFunctionsInterface::LONGITUDE_FIELD_NAME)->getString();
            $latitude = $location_node->get(HelperFunctionsInterface::LATITUDE_FIELD_NAME)->getString();

            $locations[] = [
              'nid' => $node_id,
              'latitude' => $latitude,
              'longitude' => $longitude,
            ];
          }
        }
      }
    }
    elseif ($node_has_location_data) {
      $longitude = $node->get(HelperFunctionsInterface::LONGITUDE_FIELD_NAME)->getString();
      $latitude = $node->get(HelperFunctionsInterface::LATITUDE_FIELD_NAME)->getString();

      $locations[] = [
        'nid' => $node_id,
        'latitude' => $latitude,
        'longitude' => $longitude,
      ];
    }

    return $locations;
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

    if (!($paragraph) instanceof ParagraphInterface) {
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
        '#template' => '<iframe id="{{ iframe_id }}" data-paragraph="{{ paragraph_id }}" data-tampere-cookie-information="skip" title="{% trans %} Map: {{ title }} {% endtrans %}" src="{{ url }}" allow="geolocation" style="border: none; width: 100%; height: 100%;"></iframe>',
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
   *
   * @return array|null
   *   An array of node IDs if successful. Null otherwise.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getEmbeddedContentAndMapTabsNodeIds(
    string $current_language_id,
    array $taxonomy_values,
    array $content_type_specific_taxonomy_values,
    array $content_types,
    string $condition_group = 'or'
  ): ?array {

    if (empty($content_types)) {
      return NULL;
    }

    $node_query = $this->entityTypeManager->getStorage('node')->getQuery();

    $has_content_type_specific_taxonomy_values_selected = !(count($content_type_specific_taxonomy_values, COUNT_RECURSIVE) <= count(array_keys($content_type_specific_taxonomy_values)));
    if ($has_content_type_specific_taxonomy_values_selected) {
      $content_types_with_selected_content_type_specific_taxonomy_values = [];
      foreach ($content_type_specific_taxonomy_values as $taxonomy => $terms) {
        if (!empty($terms)) {
          $content_type_with_specific_taxonomy_values = self::CONTENT_TYPE_SPECIFIC_TAXONOMY_VOCABULARIES[$taxonomy]['content_type'];
          $field_name = self::CONTENT_TYPE_SPECIFIC_TAXONOMY_VOCABULARIES[$taxonomy]['field_name'];

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
      ->accessCheck(TRUE)
      ->condition('status', NodeInterface::PUBLISHED)
      ->condition('langcode', $current_language_id)
      ->range(0, 100)
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

    if (!empty($orCondition)) {
      $node_query->condition($orCondition);
    }

    return $node_query->execute();
  }

}
