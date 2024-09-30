<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Site\Settings;
use Drupal\datetime_range\Plugin\Field\FieldType\DateRangeItem;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;
use Drupal\tre_preprocess_utility_functions\Utils\HelperFunctionsInterface;

/**
 * Zoning project card paragraph preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.paragraph__zoning_project_card",
 *   hook = "paragraph__zoning_project_card"
 * )
 */
class ZoningProjectCard extends TrePreProcessPluginBase {

  /**
   * The view mode to use for the Zoning information card.
   */
  const LIFTUP_VIEW_MODE = 'zoning_city_planning_construction_project_card_view_mode';

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $paragraph = $variables['paragraph'];

    // For some reason, the paragraph may be attached directly to a node
    // or it may be attached to a parent paragraph.
    $parent_node_candidate = $paragraph->getParentEntity();
    $parent_node_id = NULL;
    if ($parent_node_candidate instanceof NodeInterface) {
      $parent_node_id = $parent_node_candidate->id();
    }
    if ($parent_node_candidate instanceof ParagraphInterface) {
      $parent_node_id = $paragraph->getParentEntity()->getParentEntity()->id();
    }

    if ($paragraph instanceof ParagraphInterface) {
      /** @var \Drupal\paragraphs\ParagraphInterface $translated_card_paragraph */
      $translated_card_paragraph = $this->entityRepository->getTranslationFromContext($paragraph);

      if (!$translated_card_paragraph->get('field_zoning_information')->isEmpty()) {
        $referenced_entities = $translated_card_paragraph->get('field_zoning_information')->referencedEntities();
        $zoning_information_node = reset($referenced_entities);

        if ($zoning_information_node instanceof NodeInterface) {
          /** @var \Drupal\node\NodeInterface $translated_zoning_information_node */
          $translated_zoning_information_node = $this->entityRepository->getTranslationFromContext($zoning_information_node);

          $zoning_information_node_id = $translated_zoning_information_node->id();
          $variables['#cache']['tags'][] = "node:{$zoning_information_node_id}";

          $node_is_published = $translated_zoning_information_node->isPublished();
          if (!$node_is_published) {
            return $variables;
          }

          $variables['zoning_project_title'] = $translated_zoning_information_node->getTitle();

          if ($translated_zoning_information_node->hasField('field_address') && !$translated_zoning_information_node->get('field_address')->isEmpty()) {
            $variables['zoning_project_address'] = $translated_zoning_information_node->get('field_address')->view(self::LIFTUP_VIEW_MODE);
          }

          $display_zoning_information_description = $this->helperFunctions->getFieldValueString($translated_card_paragraph, 'field_display_project_descr');
          if ($display_zoning_information_description == HelperFunctionsInterface::BOOLEAN_FIELD_TRUE) {
            if ($translated_zoning_information_node->hasField('field_display_period') && !$translated_zoning_information_node->get('field_display_period')->isEmpty()) {
              $display_label = $translated_zoning_information_node->field_display_period->getFieldDefinition()->getLabel();
              if (
                $translated_zoning_information_node->get('field_display_period')->first() instanceof DateRangeItem
                && $translated_zoning_information_node->get('field_display_period')->first()->get('start_date')->getValue() instanceof DrupalDateTime
              ) {
                $display_start_date = $translated_zoning_information_node->get('field_display_period')->first()->get('start_date')->getValue()->format(HelperFunctionsInterface::DATE_ONLY_FORMAT);
              }
              if (
                $translated_zoning_information_node->get('field_display_period')->first() instanceof DateRangeItem
                && $translated_zoning_information_node->get('field_display_period')->first()->get('end_date')->getValue() instanceof DrupalDateTime
              ) {
                $display_end_date = $translated_zoning_information_node->get('field_display_period')->first()->get('end_date')->getValue()->format(HelperFunctionsInterface::DATE_ONLY_FORMAT);
              }
              if (isset($display_start_date) && isset($display_end_date)) {
                $formatted_display_range = $display_label . ' ' . $display_start_date . ' - ' . $display_end_date;
                $variables['zoning_project_dates'][] = $formatted_display_range;
              }
            }

            $variables['zoning_project_description'] = $this->helperFunctions->getFieldValueString($translated_zoning_information_node, 'field_description');
          }

          $display_location_map = $this->helperFunctions->getFieldValueString($translated_card_paragraph, 'field_display_map');
          if ($display_location_map === HelperFunctionsInterface::BOOLEAN_FIELD_TRUE) {
            $zoning_information_location_coordinates = $this->helperFunctions->getNodeLiftupLocation($translated_zoning_information_node);

            if (!empty($zoning_information_location_coordinates)) {
              $variables['zoning_project_map'] = $this->helperFunctions->getNodeLiftupMap($translated_zoning_information_node, 'liftup-map');
              $variables['#attached']['drupalSettings']['tampere']['nodeLiftupLocations'][$zoning_information_node_id] = $zoning_information_location_coordinates;
              $variables['#attached']['drupalSettings']['tampere']['mmlMapIframeDomain'] = Settings::get('mml_map_iframe_domain');
            }
          }

          $display_as_link = $this->helperFunctions->getFieldValueString($paragraph, 'field_link_to_place_content');
          if ($display_as_link == HelperFunctionsInterface::BOOLEAN_FIELD_TRUE) {
            if ($parent_node_id != $zoning_information_node_id) {
              $variables['zoning_project_url'] = $translated_zoning_information_node->toUrl()->toString(TRUE)->getGeneratedUrl();
            }
          }
        }
      }
    }

    return $variables;
  }

}
