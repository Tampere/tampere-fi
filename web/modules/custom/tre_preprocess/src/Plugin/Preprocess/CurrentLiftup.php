<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\paragraphs\ParagraphInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;

/**
 * Current liftup paragraph preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.paragraph__current_liftup",
 *   hook = "paragraph__current_liftup"
 * )
 */
class CurrentLiftup extends TrePreProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $paragraph = $variables['paragraph'];

    if ($paragraph instanceof ParagraphInterface) {
      /** @var \Drupal\paragraphs\ParagraphInterface $translated_liftup_paragraph */
      $translated_liftup_paragraph = $this->entityRepository->getTranslationFromContext($paragraph);

      if (!$translated_liftup_paragraph->get('field_icon')->isEmpty()) {
        $variables['liftup_icon_name'] = $translated_liftup_paragraph->get('field_icon')->getString();
      }

      /** @var \Drupal\paragraphs\ParagraphInterface $link_paragraph */
      $link_paragraph = $translated_liftup_paragraph->get('field_content_link')->entity;
      $is_internal_link_paragraph = $this->helperFunctions->isInternalLinkParagraph($link_paragraph);

      $liftup_link_icon_name = NULL;
      $liftup_url = '';
      $liftup_link_text = '';
      if ($is_internal_link_paragraph) {
        $internal_link_details = $this->helperFunctions->getInternalLinkParagraphContents($link_paragraph);

        if ($internal_link_details) {
          [$liftup_url, $node_id, $liftup_link_text] = $internal_link_details;

          $variables['#cache']['tags'][] = "node:{$node_id}";
        }
      }
      else {
        [$liftup_url, $liftup_link_text] = $this->helperFunctions->getExternalLinkParagraphContents($link_paragraph);
        $liftup_link_icon_name = 'external';
      }

      $selected_color = $translated_liftup_paragraph->get('field_color')->getString();
      $liftup_modifiers = $this->helperFunctions->getColorfulContentLiftupModifiers($selected_color);

      $variables['liftup_link_icon_name'] = $liftup_link_icon_name;
      $variables['liftup_url'] = $liftup_url;
      $variables['liftup_link_text'] = $liftup_link_text;
      $variables['liftup_modifiers'] = $liftup_modifiers;
    }

    return $variables;
  }

}
