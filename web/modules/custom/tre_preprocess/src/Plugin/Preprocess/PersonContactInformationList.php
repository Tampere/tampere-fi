<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\Query\Sql\Condition;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;

/**
 * Contact information list preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.paragraph__persons_contact_info_list",
 *   hook = "paragraph__persons_contact_information_list"
 * )
 */
class PersonContactInformationList extends TrePreProcessPluginBase {

  /**
   * The view mode to use for contact information content by default.
   */
  const DEFAULT_VIEW_MODE = 'person_contact_information_list_content_view_mode';

  /**
   * The taxonomy fields to use when selecting the contact information.
   *
   * Contains fields for topics and keywords taxonomies.
   */
  const TOPICS_AND_KEYWORDS_TAXONOMY_FIELDS = [
    'topics',
    'keywords',
  ];

  /**
   * The taxonomy fields that has or relation for the information selection.
   *
   * Contains field for organizational unit taxonomy.
   */
  const ORGANIZATIONAL_UNIT_TAXONOMY_FIELD = [
    'hr_organizational_unit',
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

    $topics_and_keywords_taxonomy_values = $this->helperFunctions->getParagraphTaxonomyTerms($translated_paragraph, self::TOPICS_AND_KEYWORDS_TAXONOMY_FIELDS);
    $organizational_unit_taxonomy_values = $this->helperFunctions->getParagraphTaxonomyTerms($translated_paragraph, self::ORGANIZATIONAL_UNIT_TAXONOMY_FIELD);

    $selected_taxonomy_condition_group = $translated_paragraph->get('field_taxonomy_combination')->getString();

    $current_language_id = $this->languageManager->getCurrentLanguage()->getId();
    $contact_information_node_ids = $this->getContactInformationNodeIds($current_language_id, $topics_and_keywords_taxonomy_values, $organizational_unit_taxonomy_values, $selected_taxonomy_condition_group);

    if (empty($contact_information_node_ids)) {
      return $variables;
    }

    $contact_information_nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($contact_information_node_ids);

    // Person listing: if main phone number not available,
    // use 1st additional phone.
    // Even though phone numbers are now being imported from IPaaS, the decision
    // was to keep this logic in place (2022-05-28).
    foreach ($contact_information_nodes as $contact_information_node) {
      if ($contact_information_node instanceof ContentEntityInterface) {
        $content_type = $contact_information_node->bundle();
        if ($content_type === 'person') {
          $primary_phone = $contact_information_node->get('field_phone')->getValue();
          $first_additional_phone = NULL;
          if (!$contact_information_node->get('field_additional_phones')->isEmpty()) {
            $first_additional_phone = $contact_information_node->get('field_additional_phones')[0]->getValue();
          }
          if (empty($primary_phone) && !empty($first_additional_phone)) {
            $first_additional_phone_number = $first_additional_phone['telephone_number'];
            $phone_arr = [
              "value" => $first_additional_phone_number,
            ];
            $outer_arr = [];
            array_push($outer_arr, $phone_arr);
            $contact_information_node->set('field_phone', $outer_arr);
          }
        }
      }
    }

    $contact_information_render_arrays = [];
    foreach ($contact_information_nodes as $contact_information_node) {
      $contact_information_render_arrays[] = $this->entityTypeManager->getViewBuilder('node')->view($contact_information_node, self::DEFAULT_VIEW_MODE);
    }

    $variables['#cache']['tags'][] = "node_list:person";

    $variables['contact_information_items'] = $contact_information_render_arrays;

    return $variables;
  }

  /**
   * Returns node IDs that match the taxonomy terms given as parameter.
   *
   * @param string $current_language_id
   *   The ID for the currently active user interface language.
   * @param array $topics_and_keywords_taxonomy_values
   *   An array of topics and keywords related taxonomy terms.
   * @param array $organisational_unit_taxonomy_values
   *   An array of organizational unit related taxonomy terms.
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
    array $topics_and_keywords_taxonomy_values,
    array $organisational_unit_taxonomy_values,
    string $condition_group = 'or'
  ): ?array {

    $node_query = $this->entityTypeManager->getStorage('node')->getQuery();
    $node_query
      ->accessCheck(TRUE)
      ->condition('type', 'person')
      ->condition('status', 1)
      ->condition('langcode', $current_language_id)
      ->range(0, 100)
      ->sort('field_last_name', 'ASC');

    // The $taxonomy_values array is never really empty(), because it
    // contains an array for every available taxonomy vocabulary.
    $no_topics_and_keywords_taxonomy_terms_selected = (count($topics_and_keywords_taxonomy_values, COUNT_RECURSIVE) <= count(array_keys($topics_and_keywords_taxonomy_values)));
    $no_organizational_unit_taxonomy_terms_selected = (count($organisational_unit_taxonomy_values, COUNT_RECURSIVE) <= count(array_keys($organisational_unit_taxonomy_values)));
    if ($no_topics_and_keywords_taxonomy_terms_selected && $no_organizational_unit_taxonomy_terms_selected) {
      return $node_query->execute();
    }

    // If one of the organizational unit taxonomy terms AND/OR one of the
    // topics AND/OR keywords taxonomy terms are matching with person's
    // categorization information, then person is listed in paragraph.
    if ($condition_group == 'or') {
      if (!$no_organizational_unit_taxonomy_terms_selected) {
        /** @var \Drupal\Core\Entity\Query\Sql\Condition $condition */
        $condition = $node_query->orConditionGroup();
        // Since node.person.field_hr_organizational_unit is not translatable,
        // querying by language yields no results.
        $node_query->condition($this->constructCondition($organisational_unit_taxonomy_values, $condition, NULL));
      }
      if (!$no_topics_and_keywords_taxonomy_terms_selected) {
        /** @var \Drupal\Core\Entity\Query\Sql\Condition $condition */
        $condition = $node_query->orConditionGroup();
        $node_query->condition($this->constructCondition($topics_and_keywords_taxonomy_values, $condition, $current_language_id));
      }
    }
    // If all taxonomy terms are matching with person's categorization
    // infromation, then person is listed in paragraph. Organizational
    // unit is an exception and those terms needs to have 'or' condition.
    elseif ($condition_group == 'and') {
      if (!$no_organizational_unit_taxonomy_terms_selected) {
        /** @var \Drupal\Core\Entity\Query\Sql\Condition $condition */
        $condition = $node_query->orConditionGroup();
        // Since node.person.field_hr_organizational_unit is not translatable,
        // querying by language yields no results.
        $node_query->condition($this->constructCondition($organisational_unit_taxonomy_values, $condition, NULL));
      }
      // 'And' condition needs to be created a bit differently than 'or'
      // condition group to make fields with multiple values work with 'and'
      // condition.
      // With 'and' condition we need to create a separate condition from each
      // term.
      if (!$no_topics_and_keywords_taxonomy_terms_selected) {
        foreach ($topics_and_keywords_taxonomy_values as $taxonomy => $terms) {
          if (!empty($terms)) {
            foreach ($terms as $term) {
              $condition = $node_query->andConditionGroup();
              $node_query->condition($condition->condition("field_{$taxonomy}", $term, '=', $current_language_id));
            }
          }
        }
      }
    }

    return $node_query->execute();
  }

  /**
   * Constructs condition which is used to query nodes.
   *
   * @param array $taxonomy_values
   *   An array of taxonomy terms by taxonomy vocabulary.
   * @param \Drupal\Core\Entity\Query\Sql\Condition $condition
   *   Sql condition object.
   * @param string|null $langcode
   *   The language code to set for the condition.
   *
   * @return \Drupal\Core\Entity\Query\Sql\Condition
   *   Sql condition object.
   */
  protected function constructCondition(array $taxonomy_values, Condition $condition, $langcode) {
    foreach ($taxonomy_values as $taxonomy => $terms) {
      if (!empty($terms)) {
        foreach ($terms as $term) {
          $condition->condition("field_{$taxonomy}", $term, '=', $langcode);
        }
      }
    }

    return $condition;
  }

}
