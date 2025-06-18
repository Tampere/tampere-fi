<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\node\NodeInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;

/**
 * Basic Content pattern preprocessing for Place nodes specifically.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.pattern_basic_content",
 *   hook = "pattern_basic_content"
 * )
 */
class BasicContentPattern extends TrePreProcessPluginBase {

  /**
   * Show the correct description field based on the toggle field.
   */
  public function preprocess(array $variables): array {
    $pattern_context = $variables['context'];

    $node = $pattern_context->getProperty('entity');
    $view_mode = $pattern_context->getProperty('view_mode') ?? NULL;

    if ($node instanceof NodeInterface
        && $node->bundle() === 'place'
        && $view_mode === 'full') {
        
      $translated_node = $this->entityRepository->getTranslationFromContext($node);
      $toggle = $translated_node->get('field_description_toggle')->value;

      if ($toggle && isset($variables['main_content']['field_description_formatted'])) {
        unset($variables['main_content']['field_description']);
      }
      else {
        unset($variables['main_content']['field_description_formatted']);
      }
    }

    return $variables;
  }

}
