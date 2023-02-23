<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\paragraphs\ParagraphInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;

/**
 * Feedback section paragraph preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.paragraph__feedback_section",
 *   hook = "paragraph__feedback_section"
 * )
 */
class FeedbackSection extends TrePreProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $paragraph = $variables['paragraph'];

    if ($paragraph instanceof ParagraphInterface) {
      /** @var \Drupal\paragraphs\ParagraphInterface $translated_feedback_section */
      $translated_feedback_section = $this->entityRepository->getTranslationFromContext($paragraph);

      $link_paragraphs = $translated_feedback_section->get('field_feedback_links')->referencedEntities();
      $links = [];
      foreach ($link_paragraphs as $link_paragraph) {
        $is_internal_link_paragraph = $this->helperFunctions->isInternalLinkParagraph($link_paragraph);

        $link_text = '';
        $link_url = '';
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
          continue;
        }

        $links[] = [
          'text' => $link_text,
          'url' => $link_url,
          'is_external_link' => !$is_internal_link_paragraph,
        ];
      }

      $variables['feedback_section_links'] = $links;
    }

    return $variables;
  }

}
