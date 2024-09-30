<?php

namespace Drupal\tre_preprocess_embedded_content_and_map_tabs\Plugin\Preprocess;

use Drupal\Core\Site\Settings;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Embedded content and map tabs paragraph type preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.paragraph__urban_planning_list_and_map",
 *   hook = "paragraph__urban_planning_listing_and_map"
 * )
 */
class UrbanPlanningListingAndMapParagraph extends ListingAndMapParagraphBase {

  /**
   * The taxonomy vocabularies for the listing.
   */
  protected const AVAILABLE_TAXONOMY_VOCABULARIES = [
    'topics',
    'keywords',
    'geographical_areas',
  ];

  /**
   * The content type specific taxonomy vocabularies for the listing.
   */
  protected const CONTENT_TYPE_SPECIFIC_TAXONOMY_VOCABULARIES = [];

  protected const VIEW_ID = 'urban_planning_embedded_content_tab';

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $paragraph = $variables['paragraph'];
    $container_paragraph_id = $paragraph->id();

    $current_language_id = $this->languageManager->getCurrentLanguage()->getId();

    if (!($paragraph instanceof ParagraphInterface)) {
      return $variables;
    }

    /** @var \Drupal\paragraphs\ParagraphInterface $translated_paragraph */
    $translated_paragraph = $this->entityRepository->getTranslationFromContext($paragraph);

    $paragraph_displayed_content_types_field_values = $translated_paragraph->get('field_tabs_displayed_ct')->getValue();
    $selected_content_types = $this->helperFunctions->getListFieldValues($paragraph_displayed_content_types_field_values);

    $selected_taxonomy_condition_group = $translated_paragraph->get('field_taxonomy_combination')->getString();
    $zoom_level = intval($this->helperFunctions->getFieldValueString($translated_paragraph, 'field_search_zoom_level'));

    $selected_taxonomy_values = $this->helperFunctions->getParagraphTaxonomyTerms($translated_paragraph, self::AVAILABLE_TAXONOMY_VOCABULARIES);

    $extra_conditions = [];

    if (!$translated_paragraph->get('field_urban_planning_status')->isEmpty()) {
      $status_values = $translated_paragraph->get('field_urban_planning_status')->getValue();
      if (count($status_values) === 1) {
        $status_value = reset($status_values)['value'];

        // The allowed values of
        // paragraph.urban_planning_listing_and_map.field_urban_planning_status
        // and node.*.field_computed_status match, so the $status_value can be
        // used directly.
        $condition = [
          'field' => 'field_computed_status',
          'value' => $status_value,
          'operator' => '=',
        ];
        $extra_conditions[] = $condition;
      }
    }

    if (!$translated_paragraph->get('field_visibility')->isEmpty()) {
      $visibility_values = $translated_paragraph->get('field_visibility')->getValue();
      if (count($visibility_values) === 1) {
        $visibility_value = reset($visibility_values)['value'];

        // The allowed values of
        // paragraph.urban_planning_listing_and_map.field_visbility and
        // node.*.field_computed_visibility match, so the $visibility_value
        // can be used directly.
        $condition = [
          'field' => 'field_computed_visibility',
          'value' => $visibility_value,
          'operator' => '=',
        ];
        $extra_conditions[] = $condition;
      }
    }

    $tab_list_node_ids = $this->getNodeIds(
      $current_language_id,
      $selected_taxonomy_values,

      [],
      $selected_content_types,
      $selected_taxonomy_condition_group,
      $extra_conditions,
    );

    // Prevent system from loading all nodes due to empty argument.
    if (empty($tab_list_node_ids)) {
      return $variables;
    }

    $locations = $this->getLocationData($tab_list_node_ids, $container_paragraph_id);

    $content_listing_block = $this->getRenderedView($translated_paragraph, $tab_list_node_ids);
    if (empty($content_listing_block)) {
      return $variables;
    }
    $variables['content_listing_block'] = $content_listing_block;
    $variables['tab_map_content'] = $this->getMap($translated_paragraph);
    $variables['#attached']['drupalSettings']['tampere']['embeddedContentAndMapTabs']['zoomLevels'][$container_paragraph_id] = $zoom_level;
    $variables['#attached']['drupalSettings']['tampere']['embeddedContentAndMapTabs']['locations'][$container_paragraph_id] = $locations;
    $variables['#attached']['drupalSettings']['tampere']['currentLanguage'] = $this->languageManager->getCurrentLanguage()->getId();
    $variables['#attached']['drupalSettings']['tampere']['mmlMapIframeDomain'] = Settings::get('mml_map_iframe_domain');

    $this->renderer->addCacheableDependency($variables['content_listing_block'], $variables['paragraph']);

    return $variables;
  }

  /**
   * {@inheritdoc}
   */
  protected function getViewDisplayId(ParagraphInterface $paragraph): string {

    $excluded_filters = [
      'content_type' => TRUE,
      'field_computed_status' => TRUE,
      'field_computed_visibility' => TRUE,
      'field_geographical_areas' => TRUE,
    ];
    if ($paragraph->get('field_tabs_displayed_ct')->count() !== 1) {
      $excluded_filters['content_type'] = FALSE;
    }
    if ($paragraph->get('field_urban_planning_status')->count() !== 1) {
      $excluded_filters['field_computed_status'] = FALSE;
    }
    if ($paragraph->get('field_visibility')->count() !== 1) {
      $excluded_filters['field_computed_visibility'] = FALSE;
    }
    if ($paragraph->get('field_geographical_areas')->count() !== 1) {
      $excluded_filters['field_geographical_areas'] = FALSE;
    }

    if (count(array_filter($excluded_filters)) === 4) {
      return 'listing_without_area_status_type_visibility';
    }
    elseif (
      $excluded_filters['field_computed_status']
      && $excluded_filters['content_type']
      && $excluded_filters['field_computed_visibility']
    ) {
      return 'listing_without_status_type_visibility';
    }
    elseif (
      $excluded_filters['field_geographical_areas']
      && $excluded_filters['field_computed_status']
      && $excluded_filters['field_computed_visibility']
    ) {
      return 'listing_without_area_status_visibility';
    }
    elseif (
      $excluded_filters['field_geographical_areas']
      && $excluded_filters['content_type']
      && $excluded_filters['field_computed_visibility']
    ) {
      return 'listing_without_area_type_visibility';
    }
    elseif (
      $excluded_filters['field_geographical_areas']
      && $excluded_filters['field_computed_status']
      && $excluded_filters['content_type']
    ) {
      return 'listing_without_area_status_type';
    }
    elseif (
      $excluded_filters['field_computed_status']
      && $excluded_filters['field_computed_visibility']
    ) {
      return 'listing_without_status_visibility';
    }
    elseif (
      $excluded_filters['field_computed_visibility']
      && $excluded_filters['content_type']
    ) {
      return 'listing_without_type_visibility';
    }
    elseif (
      $excluded_filters['field_computed_visibility']
      && $excluded_filters['field_geographical_areas']
    ) {
      return 'listing_without_area_visibility';
    }
    elseif (
      $excluded_filters['field_computed_status']
      && $excluded_filters['content_type']
    ) {
      return 'listing_without_status_type';
    }
    elseif (
      $excluded_filters['field_computed_status']
      && $excluded_filters['field_geographical_areas']
    ) {
      return 'listing_without_area_status';
    }
    elseif (
      $excluded_filters['field_geographical_areas']
      && $excluded_filters['content_type']
    ) {
      return 'listing_without_area_type';
    }
    elseif ($excluded_filters['field_computed_visibility']) {
      return 'listing_without_visibility';
    }
    elseif ($excluded_filters['field_computed_status']) {
      return 'listing_without_status';
    }
    elseif ($excluded_filters['content_type']) {
      return 'listing_without_type';
    }
    elseif ($excluded_filters['field_geographical_areas']) {
      return 'listing_without_area';
    }

    return 'content_listing_block';
  }

}
