<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\node\NodeInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;

/**
 * Basic content preprocessing for city_planning_and_constructions nodes.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.pattern_city_planning_and_constructions_basic_content",
 *   hook = "pattern_basic_content"
 * )
 */
class CityPlanningAndConstructionsPattern extends TrePreProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $pattern_context = $variables['context'];
    $node = $pattern_context->getProperty('entity');

    if ($node instanceof NodeInterface) {
      /** @var \Drupal\node\NodeInterface $translated_node */
      $translated_node = $this->entityRepository->getTranslationFromContext($node);
      if ($translated_node->hasField('field_phasing_not_in_use') && $translated_node->get('field_phasing_not_in_use')->value) {
        unset($variables['pre_title_content']['field_phase']);
      }
    }

    return $variables;
  }

}
