<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\tre_preprocess\TrePreProcessPluginBase;

/**
 * Cta pattern preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.pattern_cta",
 *   hook = "pattern_cta"
 * )
 */
class CtaPattern extends TrePreProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $modifiers = [];
    $pattern_context = $variables['context'];

    $view_mode = $pattern_context->getProperty('view_mode');
    if ($view_mode) {
      $modifiers[] = str_replace('_', '-', $view_mode);
    }

    $variables['cta__modifiers'] = $modifiers;

    $variables['entity_id'] = $pattern_context->getProperty('entity_id');

    $variables['bundle'] = str_replace('_', '-', $pattern_context->getProperty('bundle'));

    return $variables;
  }

}
