<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\paragraphs\ParagraphInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;

/**
 * Picture mosaic paragraph preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.paragraph__picture_mosaic",
 *   hook = "paragraph__picture_mosaic"
 * )
 */
class PictureMosaic extends TrePreProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $paragraph = $variables['paragraph'];

    $liftup_paragraphs = $paragraph->get('field_mosaic_liftups')->referencedEntities();

    $liftups = [];
    foreach ($liftup_paragraphs as $liftup_paragraph) {
      if ($liftup_paragraph instanceof ParagraphInterface) {
        /** @var \Drupal\paragraphs\ParagraphInterface $translated_liftup_paragraph */
        $translated_liftup_paragraph = $this->entityRepository->getTranslationFromContext($liftup_paragraph);

        $liftup_image = NULL;
        if (!$translated_liftup_paragraph->get('field_media')->isEmpty()) {
          $liftup_image = $translated_liftup_paragraph->get('field_media')->view('default');
        }

        $liftup_title = NULL;
        if (!$translated_liftup_paragraph->get('field_liftup_title')->isEmpty()) {
          $liftup_title = $translated_liftup_paragraph->get('field_liftup_title')->getString();
        }

        $liftup_summary = NULL;
        if (!$translated_liftup_paragraph->get('field_summary')->isEmpty()) {
          $liftup_summary = $translated_liftup_paragraph->get('field_summary')->getString();
        }

        $liftup_icon_name = NULL;
        $liftup_url = NULL;
        if (!$translated_liftup_paragraph->get('field_content_link')->isEmpty()) {
          /** @var \Drupal\paragraphs\ParagraphInterface $link_paragraph */
          $link_paragraph = $translated_liftup_paragraph->get('field_content_link')->entity;
          $is_internal_link_paragraph = $this->helperFunctions->isInternalLinkParagraph($link_paragraph);

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
        }

        // All the fields are optional, but each rendered liftup must at the
        // very least contain either an image or a title.
        if ($liftup_title || $liftup_image) {
          $liftups[] = [
            'card__image' => $liftup_image,
            'card__heading' => $liftup_title,
            'card__body' => $liftup_summary,
            'card__link__url' => $liftup_url,
            'card__icon__name' => $liftup_icon_name,
          ];
        }
      }

    }

    $variables['liftups'] = $liftups;

    return $variables;
  }

}
