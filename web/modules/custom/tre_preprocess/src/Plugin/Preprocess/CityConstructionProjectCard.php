<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\tre_preprocess\TrePreProcessPluginBase;
use Drupal\tre_preprocess_utility_functions\Utils\HelperFunctionsInterface;
use Drupal\Core\Site\Settings;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;

/**
 * City planning and construction project card preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.paragraph__city_construction_project_card",
 *   hook = "paragraph__city_construction_project_card"
 * )
 */
class CityConstructionProjectCard extends TrePreProcessPluginBase {

  /**
   * The view mode to use for City planning and construction project liftups.
   */
  const LIFTUP_VIEW_MODE = 'zoning_city_planning_construction_project_card_view_mode';

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

    $referenced_entities = $translated_paragraph->get('field_project')->referencedEntities();
    $project_node = reset($referenced_entities);

    if (!($project_node instanceof NodeInterface)) {
      return $variables;
    }

    /** @var \Drupal\node\NodeInterface $translated_project_node */
    $translated_project_node = $this->entityRepository->getTranslationFromContext($project_node);

    $project_node_id = $translated_project_node->id();
    $variables['#cache']['tags'][] = "node:{$project_node_id}";

    $node_is_published = $translated_project_node->isPublished();
    if (!$node_is_published) {
      return $variables;
    }

    $variables['project_title'] = $translated_project_node->getTitle();

    if (!$translated_project_node->get('field_address')->isEmpty()) {
      $variables['project_address'] = $translated_project_node->get('field_address')->view(self::LIFTUP_VIEW_MODE);
    }

    $display_project_description = $this->helperFunctions->getFieldValueString($translated_paragraph, 'field_display_project_descr');
    if ($display_project_description == HelperFunctionsInterface::BOOLEAN_FIELD_TRUE) {
      $variables['project_description'] = $this->helperFunctions->getFieldValueString($translated_project_node, 'field_description');
    }

    if (!$translated_project_node->get('field_additional_information')->isEmpty()) {
      $display_project_additional_information = $this->helperFunctions->getFieldValueString($translated_paragraph, 'field_display_additional_info');
      if ($display_project_additional_information == HelperFunctionsInterface::BOOLEAN_FIELD_TRUE) {
        $variables['project_additional_information'] = $translated_project_node->get('field_additional_information')->view(self::LIFTUP_VIEW_MODE);
      }
    }

    $display_location_map = $this->helperFunctions->getFieldValueString($translated_paragraph, 'field_display_map');
    if ($display_location_map === HelperFunctionsInterface::BOOLEAN_FIELD_TRUE) {
      $project_location_coordinates = $this->helperFunctions->getNodeLiftupLocation($translated_project_node);

      if (!empty($project_location_coordinates)) {
        $variables['project_map'] = $this->helperFunctions->getNodeLiftupMap($translated_project_node, 'liftup-map');
        $variables['#attached']['drupalSettings']['tampere']['nodeLiftupLocations'][$project_node_id] = $project_location_coordinates;
        $variables['#attached']['drupalSettings']['tampere']['mmlMapIframeDomain'] = Settings::get('mml_map_iframe_domain');
      }
    }

    $display_as_link = $this->helperFunctions->getFieldValueString($paragraph, 'field_link_to_place_content');
    if ($display_as_link == HelperFunctionsInterface::BOOLEAN_FIELD_TRUE) {
      $parent_paragraph = $paragraph->getParentEntity();

      if (!($parent_paragraph instanceof ParagraphInterface)) {
        return $variables;
      }

      $parent_node = $parent_paragraph->getParentEntity();

      if (!($parent_node instanceof NodeInterface)) {
        return $variables;
      }

      $parent_node_id = $parent_node->id();

      if ($parent_node_id !== $project_node_id) {
        $variables['project_url'] = $translated_project_node->toUrl()->toString(TRUE)->getGeneratedUrl();
      }
    }

    return $variables;
  }

}
