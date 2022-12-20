<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\tre_preprocess\TrePreProcessPluginBase;

/**
 * Related blogs view preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.views_view__related_blogs",
 *   hook = "views_view__related_blogs"
 * )
 */
class RelatedBlogs extends TrePreProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $variables['current_language_id'] = $this->languageManager->getCurrentLanguage()->getId();
    return $variables;
  }

}
