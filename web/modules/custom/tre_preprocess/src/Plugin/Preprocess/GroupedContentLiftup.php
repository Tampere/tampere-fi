<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\paragraphs\ParagraphInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;

/**
 * Grouped content liftup paragraph preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.paragraph__grouped_content_liftup",
 *   hook = "paragraph__grouped_content_liftup"
 * )
 */
class GroupedContentLiftup extends TrePreProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $paragraph = $variables['paragraph'];

    if (!($paragraph instanceof ParagraphInterface)) {
      return $variables;
    }

    /** @var \Drupal\paragraphs\ParagraphInterface $translated_paragraph */
    $translated_paragraph = $this->entityRepository->getTranslationFromContext($paragraph);
    $liftup_paragraphs = $translated_paragraph->get('field_grouped_content_liftups')->referencedEntities();

    $liftups = [];
    foreach ($liftup_paragraphs as $liftup_paragraph) {
      if (!($liftup_paragraph instanceof ParagraphInterface)) {
        continue;
      }

      /** @var \Drupal\paragraphs\ParagraphInterface $translated_liftup_paragraph */
      $translated_liftup_paragraph = $this->entityRepository->getTranslationFromContext($liftup_paragraph);

      $liftup_title = $translated_liftup_paragraph->get('field_liftup_title')->getString();
      $liftup_summary = $translated_liftup_paragraph->get('field_summary')->getString();

      if (!$translated_liftup_paragraph->hasField('field_content_link') ||
           $translated_liftup_paragraph->get('field_content_link')->isEmpty()) {
        continue;
      }

      $link_paragraph = $translated_liftup_paragraph->get('field_content_link')->entity;

      if (!($link_paragraph instanceof ParagraphInterface)) {
        return $variables;
      }

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

        $link_paragraph_bundle = $link_paragraph->bundle();
        if ($link_paragraph_bundle == 'login_link') {
          $liftup_icon_name = 'service-arrow';
        }
        else {
          $liftup_icon_name = 'external';
        }
      }

      if (empty($liftup_url)) {
        continue;
      }

      $liftups[] = [
        'card__heading' => $liftup_title,
        'card__body' => $liftup_summary,
        'card__modifiers' => ['grouped'],
        'card__link__url' => $liftup_url,
        'card__link__is_external' => !$is_internal_link_paragraph,
        'card__icon__name' => $liftup_icon_name,
      ];
    }

    $variables['liftups'] = $liftups;

    return $variables;
  }

}
