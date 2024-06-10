<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\Core\Site\Settings;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;
use Drupal\tre_preprocess_utility_functions\Utils\HelperFunctionsInterface;

/**
 * Place liftup preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.paragraph__place_liftup",
 *   hook = "paragraph__place_liftup"
 * )
 */
class PlaceLiftup extends TrePreProcessPluginBase {

  /**
   * The view mode to use for Place liftups.
   */
  const LIFTUP_VIEW_MODE = 'contact_information_liftup_view_mode';

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $paragraph = $variables['paragraph'];

    $referenced_entities = $paragraph->get('field_place')->referencedEntities();
    $place_node = reset($referenced_entities);

    $parent = $paragraph->getParentEntity();
    if ($parent instanceof ParagraphInterface) {
      $grand_parent_entity = $parent->getParentEntity();

      if ($grand_parent_entity instanceof ParagraphInterface) {
        $bundle = $grand_parent_entity->bundle();
        $variables['inside_accordion'] = $bundle == 'accordion_item' || $bundle == 'process_accordion_item';
      }
    }

    if ($place_node instanceof NodeInterface) {
      /** @var \Drupal\node\NodeInterface $translated_place_node */
      $translated_place_node = $this->entityRepository->getTranslationFromContext($place_node);

      $place_node_id = $translated_place_node->id();
      $variables['#cache']['tags'][] = "node:{$place_node_id}";

      $node_is_published = $translated_place_node->isPublished();
      if (!$node_is_published) {
        return $variables;
      }

      $variables['place_title'] = $translated_place_node->getTitle();

      if (!$translated_place_node->get('field_address')->isEmpty()) {
        $variables['place_address'] = $translated_place_node->get('field_address')->view(self::LIFTUP_VIEW_MODE);
      }

      $display_place_description = $this->helperFunctions->getFieldValueString($paragraph, 'field_display_place_description');
      if ($display_place_description == HelperFunctionsInterface::BOOLEAN_FIELD_TRUE) {
        $variables['place_description'] = $this->helperFunctions->getFieldValueString($translated_place_node, 'field_description');
      }

      if (!$translated_place_node->get('field_additional_information')->isEmpty()) {
        $display_place_additional_information = $this->helperFunctions->getFieldValueString($paragraph, 'field_display_additional_info');
        if ($display_place_additional_information == HelperFunctionsInterface::BOOLEAN_FIELD_TRUE) {
          $variables['place_additional_information'] = $translated_place_node->get('field_additional_information')->view(self::LIFTUP_VIEW_MODE);
        }
      }

      $display_as_link = $this->helperFunctions->getFieldValueString($paragraph, 'field_link_to_place_content');
      if ($display_as_link == HelperFunctionsInterface::BOOLEAN_FIELD_TRUE) {
        $variables['place_url'] = $translated_place_node->toUrl()->toString(TRUE)->getGeneratedUrl();
      }

      $display_location_map = $this->helperFunctions->getFieldValueString($paragraph, 'field_display_map');
      if ($display_location_map === HelperFunctionsInterface::BOOLEAN_FIELD_TRUE) {
        $place_location_coordinates = $this->helperFunctions->getNodeLiftupLocation($translated_place_node);

        if (!empty($place_location_coordinates)) {
          $variables['place_map'] = $this->helperFunctions->getNodeLiftupMap($translated_place_node, 'liftup-map');
          $variables['#attached']['drupalSettings']['tampere']['nodeLiftupLocations'][$place_node_id] = $place_location_coordinates;
          $variables['#attached']['drupalSettings']['tampere']['mmlMapIframeDomain'] = Settings::get('mml_map_iframe_domain');
        }
      }
    }

    return $variables;
  }

}
