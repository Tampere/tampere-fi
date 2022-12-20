<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\Core\Entity\EntityInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;

/**
 * Exception hours field preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.field__field_exception_hours",
 *   hook = "field__field_exception_hours"
 * )
 */
class ExceptionHoursField extends TrePreProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    if (!isset($variables['element']['#object'])) {
      return $variables;
    }

    $containing_entity = $variables['element']['#object'];

    if (!($containing_entity instanceof EntityInterface)) {
      return $variables;
    }

    $variables['containing_entity_id'] = $containing_entity->id();

    return $variables;
  }

}
