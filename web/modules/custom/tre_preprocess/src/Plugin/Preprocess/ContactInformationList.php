<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\paragraphs\ParagraphInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;

/**
 * Contact information list preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.paragraph__contact_information_list",
 *   hook = "paragraph__contact_information_list"
 * )
 */
class ContactInformationList extends TrePreProcessPluginBase {

  /**
   * The view mode to use for contact information content by default.
   */
  const DEFAULT_VIEW_MODE = 'contact_information_list_content_view_mode';

  /**
   * The taxonomy vocabularies to use when selecting the contact information.
   */
  const AVAILABLE_TAXONOMY_VOCABULARIES = [
    'topics',
    'keywords',
    'life_situations',
    'geographical_areas',
  ];

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $paragraph = $variables['paragraph'];

    if (!($paragraph instanceof ParagraphInterface)) {
      return $variables;
    }

    /** @var \Drupal\paragraphs\ParagraphInterface $translated_paragraph */
    $translated_paragraph = $this->entityRepository->getTranslationFromContext($paragraph);

    $paragraph_taxonomy_values = $this->helperFunctions->getParagraphTaxonomyTerms($translated_paragraph, self::AVAILABLE_TAXONOMY_VOCABULARIES);

    // Handle transition from old field to new field: if new field is empty (or
    // doesn't exist yet), fall back to the legacy field values.
    if (!$translated_paragraph->hasField('field_displayed_bundles') || $translated_paragraph->get('field_displayed_bundles')->isEmpty()) {
      $displayed_bundles_field = $translated_paragraph->get('field_displayed_content_types');
    }
    else {
      $displayed_bundles_field = $translated_paragraph->get('field_displayed_bundles');
    }

    $paragraph_displayed_content_types_field_values = $displayed_bundles_field->getValue();
    $selected_content_types = $this->helperFunctions->getListFieldValues($paragraph_displayed_content_types_field_values);

    $selected_taxonomy_condition_group = $translated_paragraph->get('field_taxonomy_combination')->getString();

    $current_language_id = $this->languageManager->getCurrentLanguage()->getId();
    $contact_information_node_ids = $this->getContactInformationNodeIds($current_language_id, $paragraph_taxonomy_values, $selected_content_types, $selected_taxonomy_condition_group);

    if (empty($contact_information_node_ids)) {
      return $variables;
    }

    $contact_information_nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($contact_information_node_ids);

    $contact_information_render_arrays = [];
    foreach ($contact_information_nodes as $contact_information_node) {
      $contact_information_render_arrays[] = $this->entityTypeManager->getViewBuilder('node')->view($contact_information_node, self::DEFAULT_VIEW_MODE);
    }

    foreach ($selected_content_types as $content_type) {
      $variables['#cache']['tags'][] = "node_list:{$content_type}";
    }

    $variables['contact_information_items'] = $contact_information_render_arrays;

    return $variables;
  }

  /**
   * Returns node IDs that match the taxonomy terms given as parameter.
   *
   * @param string $current_language_id
   *   The ID for the currently active user interface language.
   * @param array $taxonomy_values
   *   An array of taxonomy terms key'd by taxonomy vocabulary.
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
  protected function getContactInformationNodeIds(
    string $current_language_id,
    array $taxonomy_values,
    array $content_types,
    string $condition_group = 'or'
  ): ?array {

    if (empty($content_types)) {
      return NULL;
    }

    $node_query = $this->entityTypeManager->getStorage('node')->getQuery()->accessCheck(TRUE);
    $node_query
      ->condition('type', $content_types, 'IN')
      ->condition('status', 1)
      ->condition('langcode', $current_language_id)
      ->range(0, 50)
      ->sort('title', 'ASC');

    // The $taxonomy_values array is never really empty(), because it
    // contains an array for every available taxonomy vocabulary.
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
        if ($condition_group == 'and') {
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
