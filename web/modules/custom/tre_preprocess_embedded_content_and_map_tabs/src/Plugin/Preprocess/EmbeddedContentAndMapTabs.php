<?php

namespace Drupal\tre_preprocess_embedded_content_and_map_tabs\Plugin\Preprocess;

use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;
use Drupal\Core\Site\Settings;
use Drupal\views\Views;

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

      // Prevent system from loading all nodes due to empty argument.
      if (empty($tab_list_node_ids)) {
        return $variables;
      }

      $query = $this->db->select('node__field_addresses', 'nfa');
      $query->join('node__field_epsg_3067_easting', 'nfe', 'nfe.entity_id = nfa.field_addresses_target_id');
      $query->join('node__field_epsg_3067_northing', 'nfn', 'nfn.entity_id = nfa.field_addresses_target_id');

      $query->fields('nfa', ['entity_id'])
        ->fields('nfe', ['field_epsg_3067_easting_value'])
        ->fields('nfn', ['field_epsg_3067_northing_value'])
        ->condition('nfa.entity_id', $tab_list_node_ids, 'IN');

      $result1 = $query->execute()->fetchAll();

      $locations = [];

      foreach ($result1 as $location) {
        $locations[] = [
          'nid' => $location->entity_id,
          'latitude' => $location->field_epsg_3067_northing_value,
          'longitude' => $location->field_epsg_3067_easting_value,
        ];
      }

      $query = $this->db->select('node__field_epsg_3067_easting', 'nfe');
      $query->join('node__field_epsg_3067_northing', 'nfn', 'nfn.entity_id = nfe.entity_id');

      $query->fields('nfe', ['entity_id'])
        ->fields('nfe', ['field_epsg_3067_easting_value'])
        ->fields('nfn', ['field_epsg_3067_northing_value'])
        ->condition('nfe.entity_id', $tab_list_node_ids, 'IN');

      $result2 = $query->execute()->fetchAll();

      foreach ($result2 as $location) {
        $locations[] = [
          'nid' => $location->entity_id,
          'latitude' => $location->field_epsg_3067_northing_value,
          'longitude' => $location->field_epsg_3067_easting_value,
        ];
      }

      $content_listing_block = $this->getRenderedView($paragraph);
      if (empty($content_listing_block)) {
        return $variables;
      }
      $variables['content_listing_block'] = $content_listing_block;
      $variables['tab_map_content'] = $this->getMap($translated_paragraph);

      $variables['#attached']['drupalSettings']['tampere']['embeddedContentAndMapTabs']['locations'][$container_paragraph_id] = $locations;
      $variables['#attached']['drupalSettings']['tampere']['currentLanguage'] = $this->languageManager->getCurrentLanguage()->getId();
      $variables['#attached']['drupalSettings']['tampere']['mmlMapIframeDomain'] = Settings::get('mml_map_iframe_domain');

      $this->renderer->addCacheableDependency($variables['content_listing_block'], $variables['paragraph']);
    }
    return $variables;
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

  /**
   * Renders a certain view based on paragraph values.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph entity to base the view on.
   *
   * @return array|null
   *   The view as a renderable array if successful. Null if unsuccessful.
   */
  protected function getRenderedView(ParagraphInterface $paragraph) {
    $view = Views::getView('embedded_content_tab');
    $block_machine_name = 'content_listing_block';

    if (empty($view) || !$view->access($block_machine_name)) {
      return NULL;
    }

    $view->setDisplay($block_machine_name);

    $paragraph_displayed_content_types_field_values = $paragraph->get('field_tabs_displayed_content_tps')->getValue();
    $selected_content_types = $this->helperFunctions->getListFieldValues($paragraph_displayed_content_types_field_values);

    // '+' for OR, ',' for AND
    $content_type_argument = implode('+', $selected_content_types);

    $selected_taxonomy_values = $this->helperFunctions->getParagraphTaxonomyTerms($paragraph, self::AVAILABLE_TAXONOMY_VOCABULARIES);
    $selected_taxonomy_condition_group = $paragraph->get('field_taxonomy_combination')->getString();

    $combined_taxonomy_values = [];
    foreach ($selected_taxonomy_values as $list) {
      foreach ($list as $tid) {
        $combined_taxonomy_values[$tid] = $tid;
      }
    }

    $taxonomy_argument_glue = $selected_taxonomy_condition_group == 'or' ? '+' : ',';
    $taxonomy_argument = empty($combined_taxonomy_values) ? 'all' : implode($taxonomy_argument_glue, $combined_taxonomy_values);
    $view->setArguments([$content_type_argument, $taxonomy_argument]);

    $view->preExecute();
    $view->execute();
    $view->postExecute();
    $renderable = $view->buildRenderable();

    if ($renderable) {
      $this->renderer->addCacheableDependency($renderable, $view);
    }

    return $renderable;
  }

}
