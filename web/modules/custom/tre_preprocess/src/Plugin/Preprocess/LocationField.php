<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\node\NodeInterface;
use Drupal\Core\Site\Settings;
use Drupal\tre_preprocess\TrePreProcessPluginBase;
use Drupal\tre_preprocess_utility_functions\Utils\HelperFunctionsInterface;

/**
 * Location field preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.field__field_location",
 *   hook = "field__field_location"
 * )
 */
class LocationField extends TrePreProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    if (!isset($variables['element']['#object'])) {
      return $variables;
    }

    $containing_entity = $variables['element']['#object'];

    if (!($containing_entity instanceof NodeInterface)) {
      return $variables;
    }

    /** @var \Drupal\node\NodeInterface $translated_node */
    $translated_node = $this->entityRepository->getTranslationFromContext($containing_entity);

    $node_location_coordinates = $this->helperFunctions->getNodeLiftupLocation($translated_node);

    if (empty($node_location_coordinates)) {
      return $variables;
    }

    if ($translated_node->hasField('field_display_location_on_map')) {
      $display_location_on_map = $this->helperFunctions->getFieldValueString($translated_node, 'field_display_location_on_map');
      if ($display_location_on_map !== HelperFunctionsInterface::BOOLEAN_FIELD_TRUE) {
        return $variables;
      }
    }

    $node_id = $containing_entity->id();
    $variables['location_map'] = $this->helperFunctions->getNodeLiftupMap($translated_node, 'content-map', TRUE);
    $variables['#attached']['drupalSettings']['tampere']['nodeLiftupLocations'][$node_id] = $node_location_coordinates;
    $variables['#attached']['drupalSettings']['tampere']['mmlMapIframeDomain'] = Settings::get('mml_map_iframe_domain');

    return $variables;
  }

}
