<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\paragraphs\ParagraphInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;
use Drupal\views\Views;

/**
 * Generic listing paragraph preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.paragraph__generic_listing",
 *   hook = "paragraph__generic_listing"
 * )
 */
class GenericListing extends TrePreProcessPluginBase {

  /**
   * The available taxonomy vocabularies for the listing.
   */
  const AVAILABLE_TAXONOMY_VOCABULARIES = [
    'topics',
    'keywords',
    'life_situations',
    'geographical_areas',
    'plan_numbers',
    'other_identifiers',
    'record_numbers',
    'zoning_information_types',
  ];

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {

    $generic_listing_block = $this->getRenderedView($variables['paragraph']);

    if (empty($generic_listing_block)) {
      return $variables;
    }

    $variables['generic_listing_block'] = $generic_listing_block;

    $this->renderer->addCacheableDependency($variables['generic_listing_block'], $variables['paragraph']);

    return $variables;
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

    $view = Views::getView('generic_listing');

    $sort_order_field = $paragraph->get('field_sort_order');
    if (!empty($sort_order_field)) {
      $sort_order_field_value = $sort_order_field->value;
      if ($sort_order_field_value == 'date') {
        $block_machine_name = 'generic_listing_block_date';
      }
      else {
        $block_machine_name = 'generic_listing_block_title';
      }
    }

    if (empty($view) || !$view->access($block_machine_name)) {
      return NULL;
    }

    $view->setDisplay($block_machine_name);

    $paragraph_displayed_content_types_field_values = $paragraph->get('field_gl_displayed_content_types')->getValue();
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
