<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;

/**
 * Project and organization card paragraph preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.paragraph__project_or_organization_card",
 *   hook = "paragraph__project_or_organization_card"
 * )
 */
class ProjectOrOrganizationCard extends TrePreProcessPluginBase {

  /**
   * The view mode to use for the Zoning information card.
   */
  const LIFTUP_VIEW_MODE = 'zoning_city_planning_construction_project_card_view_mode';

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $paragraph = $variables['paragraph'];

    $parent_node_id = $paragraph->getParentEntity()->getParentEntity()->id();

    if ($paragraph instanceof ParagraphInterface) {
      /** @var \Drupal\paragraphs\ParagraphInterface $translated_card_paragraph */
      $translated_card_paragraph = $this->entityRepository->getTranslationFromContext($paragraph);

      if (!$translated_card_paragraph->get('field_project_or_organization')->isEmpty()) {
        $referenced_entities = $translated_card_paragraph->get('field_project_or_organization')->referencedEntities();
        $project_or_organization_node = reset($referenced_entities);

        if ($project_or_organization_node instanceof NodeInterface) {
          /** @var \Drupal\node\NodeInterface $translated_project_or_organization_node */
          $translated_project_or_organization_node = $this->entityRepository->getTranslationFromContext($project_or_organization_node);

          $project_or_organization_node_id = $translated_project_or_organization_node->id();
          $variables['#cache']['tags'][] = "node:{$project_or_organization_node_id}";

          $node_is_published = $translated_project_or_organization_node->isPublished();
          if (!$node_is_published) {
            return $variables;
          }

          $displayed_fields_field_values = $translated_card_paragraph->get('field_displayed_fields')->getValue();
          $fields_selected_for_display = [];
          foreach ($displayed_fields_field_values as $key => $field_value) {
            $fields_selected_for_display[$key] = $field_value['value'];
          }

          if (in_array('name', $fields_selected_for_display, TRUE)) {
            $variables['project_or_organization_title'] = $translated_project_or_organization_node->getTitle();
          }

          if (in_array('description', $fields_selected_for_display, TRUE)) {
            if (!$translated_project_or_organization_node->get('field_description')->isEmpty()) {
              $variables['project_or_organization_description'] = $translated_project_or_organization_node->get('field_description')->view(self::LIFTUP_VIEW_MODE);
            }
          }

          if (in_array('contacts', $fields_selected_for_display, TRUE)) {
            /** @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface $paragraph_field_items */
            $paragraph_field_items = $translated_project_or_organization_node->get('field_contact_info_paragraph');
            /** @var \Drupal\node\NodeInterface|null $translated_person_node */
            $translated_person_node = $this->getTranslatedPersonNodeFromParagraphFieldValues($paragraph_field_items);
            if (!is_null($translated_person_node)) {
              $variables['project_or_organization_contact_name'] = $translated_person_node->getTitle();

              if (!$translated_person_node->get('field_email')->isEmpty()) {
                $variables['project_or_organization_contact_email_address'] = $translated_person_node->get('field_email')->getString();
              }

              /** @var \Drupal\Core\Entity\EntityViewBuilderInterface $node_view_builder */
              $node_view_builder = $this->entityTypeManager->getViewBuilder('node');
              $variables['project_or_organization_contact_details'] = $node_view_builder->view($translated_person_node, self::LIFTUP_VIEW_MODE);
            }

          }

          if ($parent_node_id != $project_or_organization_node_id) {
            $variables['project_or_organization_url'] = $translated_project_or_organization_node->toUrl()->toString(TRUE)->getGeneratedUrl();
          }
        }
      }
    }

    return $variables;
  }

  /**
   * Finds the first referenced person node from a paragraph.
   *
   * The paragraph must have a field called 'field_person_liftup' for this
   * method to work correctly.
   *
   * @param \Drupal\Core\Field\EntityReferenceFieldItemListInterface $paragraph_field_items
   *   The paragraphs field items to find the person node from.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The person node if found, null otherwise.
   */
  private function getTranslatedPersonNodeFromParagraphFieldValues(EntityReferenceFieldItemListInterface $paragraph_field_items) {
    if ($paragraph_field_items->isEmpty()) {
      return NULL;
    }
    $paragraphs = $paragraph_field_items->referencedEntities();
    $first_paragraph = reset($paragraphs);
    /** @var \Drupal\paragraphs\ParagraphInterface $translated_paragraph */
    $translated_paragraph = $this->entityRepository->getTranslationFromContext($first_paragraph);
    if (!$translated_paragraph->hasField('field_person_liftup') || $translated_paragraph->get('field_person_liftup')->isEmpty()) {
      return NULL;
    }
    $referenced_contact_information_entities = $translated_paragraph->get('field_person_liftup')->referencedEntities();
    $referenced_person_node = reset($referenced_contact_information_entities);
    if (!($referenced_person_node instanceof EntityInterface)) {
      return NULL;
    }

    $translated_person_node = $this->entityRepository->getTranslationFromContext($referenced_person_node);

    return $translated_person_node;
  }

}
