<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\paragraphs\ParagraphInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;

/**
 * Icon and text link paragraph preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.paragraph__icon_and_text_link",
 *   hook = "paragraph__icon_and_text_link"
 * )
 */
class IconAndTextLink extends TrePreProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $paragraph = $variables['paragraph'];

    if ($paragraph instanceof ParagraphInterface) {
      /** @var \Drupal\paragraphs\ParagraphInterface $translated_paragraph */
      $translated_paragraph = $this->entityRepository->getTranslationFromContext($paragraph);

      /** @var \Drupal\paragraphs\ParagraphInterface $link_paragraph */
      $link_paragraph = $translated_paragraph->get('field_content_link')->entity;
      $is_internal_link_paragraph = $this->helperFunctions->isInternalLinkParagraph($link_paragraph);

      if (!$translated_paragraph->get('field_icon')->isEmpty()) {
        $variables['icon_name'] = $translated_paragraph->get('field_icon')->getString();
      }

      $link_url = '';
      $link_text = '';
      if ($is_internal_link_paragraph) {
        $internal_link_details = $this->helperFunctions->getInternalLinkParagraphContents($link_paragraph);

        if ($internal_link_details) {
          [$link_url, $node_id, $link_text] = $internal_link_details;

          $variables['#cache']['tags'][] = "node:{$node_id}";
        }
      }
      else {
        [$link_url, $link_text] = $this->helperFunctions->getExternalLinkParagraphContents($link_paragraph);
      }

      if (empty($link_url)) {
        return $variables;
      }

      $variables['link_url'] = $link_url;
      $variables['link_text'] = $link_text;
    }

    return $variables;
  }

}
