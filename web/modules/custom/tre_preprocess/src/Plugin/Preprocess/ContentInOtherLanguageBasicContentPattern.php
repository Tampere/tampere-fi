<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\node\NodeInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;

/**
 * Basic content pattern preprocessing for content_in_other_lang nodes specifically.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.pattern_content_in_other_lang_basic_content",
 *   hook = "pattern_basic_content"
 * )
 */
class ContentInOtherLanguageBasicContentPattern extends TrePreProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $pattern_context = $variables['context'];
    $current_langcode = $this->languageManager->getCurrentLanguage()->getId();

    $node = $pattern_context->getProperty('entity');

    if (!($node instanceof NodeInterface) || $node->bundle() !== 'content_in_other_language') {
      return $variables;
    }

    $content_language = $node->get('field_language')->getString();
    $variables['content_language'] = $content_language;

    return $variables;
  }

}
