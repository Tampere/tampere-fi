<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\paragraphs\ParagraphInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;

/**
 * Balanced content liftup paragraph preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.paragraph__balanced_content_liftup",
 *   hook = "paragraph__balanced_content_liftup"
 * )
 */
class BalancedContentLiftup extends TrePreProcessPluginBase {

  /**
   * The modifiers to use for the liftups.
   */
  const LIFTUP_MODIFIERS = ['slim'];

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

        // The 'field_content_link' is being phased out of the 'content_liftup'
        // paragraph type that the 'balanced_content_liftup' uses in its
        // 'field_content_liftups' field. At this point, both fields are
        // visible on the form and can contain values. The new field
        // (field_internal_content_link) should be used instead of the
        // old field if it has a value. The 'field_content_link' handling
        // can be removed once the field is hidden on the form.
        // See TRE-1099.
        $link_field_names = [
          'field_content_link',
          'field_internal_content_link',
        ];

        foreach ($link_field_names as $link_field_name) {
          if (!$translated_liftup_paragraph->hasField($link_field_name) || $translated_liftup_paragraph->get($link_field_name)->isEmpty()) {
            continue;
          }

          $link_field_with_content_name = $link_field_name;
        }

        if (!isset($link_field_with_content_name)) {
          continue;
        }

        /** @var \Drupal\paragraphs\ParagraphInterface $link_paragraph */
        $link_paragraph = $translated_liftup_paragraph->get($link_field_with_content_name)->entity;
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
        }

        if (empty($liftup_url)) {
          continue;
        }

        $liftups[] = [
          'card__heading' => $liftup_title,
          'card__body' => $liftup_summary,
          'card__modifiers' => self::LIFTUP_MODIFIERS,
          'card__link__url' => $liftup_url,
          'card__link__is_external' => !$is_internal_link_paragraph,
        ];
      }
    }

    $variables['liftups'] = $liftups;

    return $variables;
  }

}
