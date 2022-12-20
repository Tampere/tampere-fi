<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;

/**
 * Topical content pattern preprocessing for blog_article nodes specifically.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.pattern_blog_topical_content",
 *   hook = "pattern_topical_content"
 * )
 */
class BlogArticleTopicalContentPattern extends TrePreProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $pattern_context = $variables['context'];
    $current_langcode = $this->languageManager->getCurrentLanguage()->getId();

    $node = $pattern_context->getProperty('entity');

    if (!($node instanceof NodeInterface) || $node->bundle() !== 'blog_article') {
      return $variables;
    }

    $term_id = $node->get('field_blog')->getString();

    if (empty($term_id)) {
      return $variables;
    }

    $variables['#cache']['tags'][] = "taxonomy_term:{$term_id}";

    $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($term_id);

    if (!($term instanceof TermInterface)) {
      return $variables;
    }

    /** @var \Drupal\taxonomy\TermInterface $translated_term */
    $translated_term = $this->entityRepository->getTranslationFromContext($term, $current_langcode);

    if ($translated_term->hasField('field_title') && !$translated_term->get('field_title')->isEmpty()) {
      $variables['blog_header__heading'] = $translated_term->get('field_title')->getString();
    }

    if ($translated_term->hasField('field_image') && !$translated_term->get('field_image')->isEmpty()) {
      $variables['blog_header__image'] = $translated_term->get('field_image')->view('default');
    }

    if ($translated_term->hasField('field_description') && !$translated_term->get('field_description')->isEmpty()) {
      $variables['blog_header__description'] = $translated_term->get('field_description')->getString();
    }

    if ($translated_term->hasField('field_author') && !$translated_term->get('field_author')->isEmpty()) {
      $variables['blog_header__author'] = $translated_term->get('field_author')->getString();
      $variables['blog_header__field_author__label'] = $translated_term->field_author->getFieldDefinition()->getLabel();
    }

    return $variables;
  }

}
