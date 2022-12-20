<?php

namespace Drupal\tre_preprocess\Traits;

/**
 * Trait for including entity ID from pattern context in pattern variables.
 */
trait EntityIdFromPatternContextTrait {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $pattern_context = $variables['context'];

    $variables['entity_id'] = $pattern_context->getProperty('entity_id');

    return $variables;
  }

}
