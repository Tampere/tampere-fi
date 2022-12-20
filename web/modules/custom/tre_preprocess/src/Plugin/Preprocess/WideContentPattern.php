<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\node\NodeInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;

/**
 * Wide content pattern preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.pattern_wide_content",
 *   hook = "pattern_wide_content"
 * )
 */
class WideContentPattern extends TrePreProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $pattern_context = $variables['context'];

    $node = $pattern_context->getProperty('entity');

    if ($node instanceof NodeInterface) {
      /** @var \Drupal\node\NodeInterface $translated_node */
      $translated_node = $this->entityRepository->getTranslationFromContext($node);

      if ($translated_node->hasField('field_main_image_link') && !$translated_node->get('field_main_image_link')->isEmpty()) {
        /** @var \Drupal\paragraphs\ParagraphInterface $link_paragraph */
        $link_paragraph = $translated_node->get('field_main_image_link')->entity;

        $is_internal_link_paragraph = $this->helperFunctions->isInternalLinkParagraph($link_paragraph);

        $link_url = '';
        $link_text = '';
        if ($is_internal_link_paragraph) {
          $variables['cta_link_is_internal'] = TRUE;

          $internal_link_details = $this->helperFunctions->getInternalLinkParagraphContents($link_paragraph);

          if ($internal_link_details) {
            [$link_url, $node_id, $link_text] = $internal_link_details;

            $variables['#cache']['tags'][] = "node:{$node_id}";
          }
        }
        else {
          [$link_url, $link_text] = $this->helperFunctions->getExternalLinkParagraphContents($link_paragraph);

          $link_paragraph_bundle = $link_paragraph->bundle();
          if ($link_paragraph_bundle == 'login_link_with_text') {
            $variables['cta_link_requires_login'] = TRUE;
          }
        }

        if (empty($link_url)) {
          return $variables;
        }

        $variables['cta_link_url'] = $link_url;
        $variables['cta_link_text'] = $link_text;
      }
    }

    return $variables;
  }

}
