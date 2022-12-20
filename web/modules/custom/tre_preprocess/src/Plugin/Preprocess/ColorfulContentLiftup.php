<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\paragraphs\ParagraphInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;

/**
 * Colorful content liftup paragraph preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.paragraph__colorful_content_liftup",
 *   hook = "paragraph__colorful_content_liftup"
 * )
 */
class ColorfulContentLiftup extends TrePreProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $paragraph = $variables['paragraph'];

    if (!($paragraph instanceof ParagraphInterface)) {
      return $variables;
    }

    $liftup_paragraphs = $paragraph->get('field_content_liftups')->referencedEntities();

    $liftups = [];
    foreach ($liftup_paragraphs as $liftup_paragraph) {
      if ($liftup_paragraph instanceof ParagraphInterface) {
        /** @var \Drupal\paragraphs\ParagraphInterface $translated_liftup_paragraph */
        $translated_liftup_paragraph = $this->entityRepository->getTranslationFromContext($liftup_paragraph);

        $liftup_title = $translated_liftup_paragraph->get('field_liftup_title')->getString();
        $liftup_summary = $translated_liftup_paragraph->get('field_summary')->getString();

        $selected_color = $translated_liftup_paragraph->get('field_color')->getString();
        $liftup_modifiers = $this->helperFunctions->getColorfulContentLiftupModifiers($selected_color);

        /** @var \Drupal\paragraphs\ParagraphInterface $link_paragraph */
        $link_paragraph = $translated_liftup_paragraph->get('field_content_link')->entity;
        $is_internal_link_paragraph = $this->helperFunctions->isInternalLinkParagraph($link_paragraph);

        $liftup_icon_name = NULL;
        $liftup_url = '';
        if ($is_internal_link_paragraph) {
          $internal_link_details = $this->helperFunctions->getInternalLinkParagraphContents($link_paragraph);

          if ($internal_link_details) {
            [$liftup_url, $node_id] = $internal_link_details;

            $variables['#cache']['tags'][] = "node:{$node_id}";
          }
        }
        else {
          [$liftup_url] = $this->helperFunctions->getExternalLinkParagraphContents($link_paragraph);
          $liftup_icon_name = 'external';
        }

        if (empty($liftup_url)) {
          continue;
        }

        $liftups[] = [
          'card__heading' => $liftup_title,
          'card__body' => $liftup_summary,
          'card__modifiers' => $liftup_modifiers,
          'card__link__url' => $liftup_url,
          'card__icon__name' => $liftup_icon_name,
        ];
      }
    }

    $variables['liftups'] = $liftups;

    return $variables;
  }

}
