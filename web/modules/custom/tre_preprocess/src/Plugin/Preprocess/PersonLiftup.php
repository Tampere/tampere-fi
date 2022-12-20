<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;

/**
 * Person liftup paragraph preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.paragraph__person_liftup",
 *   hook = "paragraph__person_liftup"
 * )
 */
class PersonLiftup extends TrePreProcessPluginBase {

  /**
   * The view mode to use for the Person liftup.
   */
  const LIFTUP_VIEW_MODE = 'contact_information_liftup_view_mode';

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $paragraph = $variables['paragraph'];

    if ($paragraph instanceof ParagraphInterface) {
      /** @var \Drupal\paragraphs\ParagraphInterface $translated_person_liftup */
      $translated_person_liftup = $this->entityRepository->getTranslationFromContext($paragraph);

      if (!$translated_person_liftup->get('field_description_text')->isEmpty()) {
        $variables['contact_legend'] = $translated_person_liftup->get('field_description_text')->getString();
      }

      if (!$translated_person_liftup->get('field_person_liftup')->isEmpty()) {
        $referenced_entities = $translated_person_liftup->get('field_person_liftup')->referencedEntities();
        $person_node = reset($referenced_entities);

        if ($person_node instanceof NodeInterface) {
          /** @var \Drupal\node\NodeInterface $translated_person_node */
          $translated_person_node = $this->entityRepository->getTranslationFromContext($person_node);

          $person_node_id = $translated_person_node->id();
          $variables['#cache']['tags'][] = "node:{$person_node_id}";

          $node_is_published = $translated_person_node->isPublished();
          if (!$node_is_published) {
            return $variables;
          }

          $displayed_fields_field_values = $translated_person_liftup->get('field_pl_displayed_fields')->getValue();
          $fields_selected_for_display = $this->helperFunctions->getListFieldValues($displayed_fields_field_values);

          if (in_array('image', $fields_selected_for_display, TRUE)) {
            if (!$translated_person_node->get('field_image')->isEmpty()) {
              $variables['contact_image'] = $translated_person_node->get('field_image')->view(self::LIFTUP_VIEW_MODE);
            }
          }

          if (in_array('title', $fields_selected_for_display, TRUE)) {
            if (!$translated_person_node->get('field_hr_title')->isEmpty()) {
              $hr_title_referenced_entities = $translated_person_node->get('field_hr_title')->referencedEntities();
              $hr_title_referenced_entity = reset($hr_title_referenced_entities);

              if ($hr_title_referenced_entity instanceof TermInterface) {
                /** @var \Drupal\taxonomy\TermInterface $translated_term */
                $translated_term = $this->entityRepository->getTranslationFromContext($hr_title_referenced_entity);
                $variables['contact_title'] = $translated_term->getName();
              }
            }
          }

          if (in_array('name', $fields_selected_for_display, TRUE)) {
            $variables['contact_full_name'] = $translated_person_node->getTitle();
          }

          if (in_array('area_of_responsibility', $fields_selected_for_display, TRUE)) {
            if (!$translated_person_node->get('field_hr_cost_center')->isEmpty()) {
              $hr_cost_center_referenced_entities = $translated_person_node->get('field_hr_cost_center')->referencedEntities();
              $hr_cost_center_referenced_entity = reset($hr_cost_center_referenced_entities);

              if ($hr_cost_center_referenced_entity instanceof TermInterface) {
                /** @var \Drupal\taxonomy\TermInterface $translated_term */
                $translated_term = $this->entityRepository->getTranslationFromContext($hr_cost_center_referenced_entity);
                $variables['contact_area_of_responsibility'] = $translated_term->getName();
              }
            }
          }

          if (in_array('phone_number', $fields_selected_for_display, TRUE)) {
            if (!$translated_person_node->get('field_phone')->isEmpty()) {
              $variables['contact_phone_number'] = $translated_person_node->get('field_phone')->view(self::LIFTUP_VIEW_MODE);
            }
          }

          // This checks what additional phone fields are selected for the
          // liftup. In order for this to work on the front-end side,
          // additional phone fields must be indexed starting from 0
          // in $phone_numbers.
          // Array $phone_numbers also contains key value pairs in addition to
          // number indexed values.
          $amount_of_additional_phone_number_fields = 5;
          $phone_numbers = [];
          if (!$translated_person_node->get('field_additional_phones')->isEmpty()) {
            $phone_numbers = $translated_person_node->get('field_additional_phones')->view(self::LIFTUP_VIEW_MODE);
          }
          $selected_fields = [];
          for ($i = 0; $i < $amount_of_additional_phone_number_fields; $i++) {
            $field_number = $i + 1;
            if (in_array("additional_phone_number_{$field_number}", $fields_selected_for_display, TRUE)) {
              if (isset($phone_numbers[$i])) {
                array_push($selected_fields, $phone_numbers[$i]);
                unset($phone_numbers[$i]);
              }
            }
            else {
              unset($phone_numbers[$i]);
            }
          }
          foreach (array_reverse($selected_fields) as $field) {
            array_unshift($phone_numbers, $field);
          }
          if (!empty($selected_fields)) {
            $variables['contact_additional_phones'] = $phone_numbers;
          }

          if (in_array('email_address', $fields_selected_for_display, TRUE)) {
            if (!$translated_person_node->get('field_email')->isEmpty()) {
              $variables['contact_email_address'] = $translated_person_node->get('field_email')->view(self::LIFTUP_VIEW_MODE);
            }
          }

          if (in_array('additional_information', $fields_selected_for_display, TRUE)) {
            if (!$translated_person_node->get('field_additional_information')->isEmpty()) {
              $variables['contact_description'] = $translated_person_node->get('field_additional_information')->view(self::LIFTUP_VIEW_MODE);
            }
          }
        }
      }
    }

    return $variables;
  }

}
