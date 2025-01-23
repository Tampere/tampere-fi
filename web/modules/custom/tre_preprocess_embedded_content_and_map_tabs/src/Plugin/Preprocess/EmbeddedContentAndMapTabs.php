<?php

namespace Drupal\tre_preprocess_embedded_content_and_map_tabs\Plugin\Preprocess;

use Drupal\Core\Site\Settings;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\tre_preprocess_utility_functions\Utils\HelperFunctionsInterface;

/**
 * Embedded content and map tabs paragraph type preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.paragraph__embedded_content_and_map_tabs",
 *   hook = "paragraph__embedded_content_and_map_tabs"
 * )
 */
class EmbeddedContentAndMapTabs extends ListingAndMapParagraphBase {

  /**
   * The taxonomy vocabularies for the listing.
   */
  protected const AVAILABLE_TAXONOMY_VOCABULARIES = [
    'topics',
    'keywords',
    'life_situations',
    'geographical_areas',
  ];

  /**
   * The content type specific taxonomy vocabularies for the listing.
   */
  protected const CONTENT_TYPE_SPECIFIC_TAXONOMY_VOCABULARIES = [];

  /**
   * The id of the view to use for the content listing.
   */
  protected const VIEW_ID = 'embedded_content_tab';

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

    $paragraph_displayed_content_types_field_values = $translated_paragraph->get('field_tabs_displayed_content_tps')->getValue();
    $selected_content_types = $this->helperFunctions->getListFieldValues($paragraph_displayed_content_types_field_values);

    $selected_taxonomy_condition_group = $translated_paragraph->get('field_taxonomy_combination')->getString();
    $zoom_level = intval($this->helperFunctions->getFieldValueString($translated_paragraph, 'field_search_zoom_level'));

    $selected_taxonomy_values = $this->helperFunctions->getParagraphTaxonomyTerms($translated_paragraph, static::AVAILABLE_TAXONOMY_VOCABULARIES);
    $selected_content_type_specific_taxonomy_values = $this->helperFunctions->getParagraphTaxonomyTerms($translated_paragraph, array_keys(static::CONTENT_TYPE_SPECIFIC_TAXONOMY_VOCABULARIES));

    $ignore_omit_checkbox = $this->helperFunctions->getFieldValueString($paragraph, 'field_ignore_omit_checkbox') === HelperFunctionsInterface::BOOLEAN_FIELD_TRUE;
    $paragraph_show_hours = $this->helperFunctions->getFieldValueString($translated_paragraph, 'field_show_hours');
    if ($paragraph_show_hours == HelperFunctionsInterface::BOOLEAN_FIELD_TRUE) {
      $show_hours = 'true';
    }
    else {
      $show_hours = 'false';
    }

    $tab_list_node_ids = $this->getNodeIds(
      $current_language_id,
      $selected_taxonomy_values,
      $selected_content_type_specific_taxonomy_values,
      $selected_content_types,
      $ignore_omit_checkbox,
      $selected_taxonomy_condition_group,
      [],
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
    $variables['content_listing_block']['#attached']['drupalSettings']['container_paragraph_id']= $container_paragraph_id;
    $variables['#attached']['drupalSettings']['tampere']['embeddedContentAndMapTabs']['zoomLevels'][$container_paragraph_id] = $zoom_level;
    $variables['#attached']['drupalSettings']['tampere']['embeddedContentAndMapTabs']['locations'][$container_paragraph_id] = $locations;
    $variables['#attached']['drupalSettings']['tampere']['currentLanguage'] = $this->languageManager->getCurrentLanguage()->getId();
    $variables['#attached']['drupalSettings']['tampere']['mmlMapIframeDomain'] = Settings::get('mml_map_iframe_domain');
    $variables['#attached']['drupalSettings']['tampere']['showHours'] = $show_hours;

    $this->renderer->addCacheableDependency($variables['content_listing_block'], $variables['paragraph']);
    return $variables;
  }

  /**
   * {@inheritdoc}
   */
  protected function getViewDisplayId(ParagraphInterface $paragraph): string {
    $block_machine_name = 'content_listing_block';
    $hide_area_filter_value = $this->helperFunctions->getFieldValueString($paragraph, 'field_hide_area_filter');
    $hide_area_filter = $hide_area_filter_value == HelperFunctionsInterface::BOOLEAN_FIELD_TRUE;

    if ($hide_area_filter) {
      $block_machine_name = 'content_listing_block_without_area_filter';
    }

    $show_hours_value = $this->helperFunctions->getFieldValueString($paragraph, 'field_show_hours');
    $show_hours = $show_hours_value == HelperFunctionsInterface::BOOLEAN_FIELD_TRUE;

    if ($show_hours) {
      $block_machine_name = 'content_listing_block_with_hours';
      if ($hide_area_filter) {
        $block_machine_name = 'content_listing_block_without_area_filter_with_hours';
      }
    }

    return $block_machine_name;
  }

}
