<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\paragraphs\ParagraphInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;

/**
 * Logo wall paragraph preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.paragraph__logo_wall",
 *   hook = "paragraph__logo_wall"
 * )
 */
class LogoWall extends TrePreProcessPluginBase {

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

    if (!$translated_paragraph->hasField('field_logos') || $translated_paragraph->get('field_logos')->isEmpty()) {
      return $variables;
    }

    $logo_paragraphs = $translated_paragraph->get('field_logos')->referencedEntities();

    $logos = [];
    foreach ($logo_paragraphs as $logo_paragraph) {
      if (!($logo_paragraph instanceof ParagraphInterface)) {
        continue;
      }

      /** @var \Drupal\paragraphs\ParagraphInterface $translated_logo_paragraph */
      $translated_logo_paragraph = $this->entityRepository->getTranslationFromContext($logo_paragraph);

      if (!$translated_logo_paragraph->hasField('field_media') || $translated_logo_paragraph->get('field_media')->isEmpty()) {
        continue;
      }

      $logo_image = $translated_logo_paragraph->get('field_media')->view('default');

      [$link_url, $link_aria_label, $linked_node_id] = $this->getLogoLinkInformation($translated_logo_paragraph);

      if (isset($linked_node_id)) {
        $variables['#cache']['tags'][] = "node:{$linked_node_id}";
      }

      $logos[] = [
        'image' => $logo_image,
        'link_url' => $link_url,
        'link_aria_label' => $link_aria_label,
      ];
    }

    $variables['logos'] = $logos;

    return $variables;
  }

  /**
   * Extracts the URL, aria label, and possible node ID for a logo paragraph.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $logo_paragraph
   *   The logo paragraph whose contents are to be extracted.
   *
   * @return array|null
   *   The URL, aria label, and optionally a node ID for the internally linked
   *   node in an array. Null if unsuccessful.
   */
  private function getLogoLinkInformation(ParagraphInterface $logo_paragraph) {
    if (!$logo_paragraph->hasField('field_logo_link') || $logo_paragraph->get('field_logo_link')->isEmpty()) {
      return NULL;
    }

    $referenced_link_paragraph = $logo_paragraph->get('field_logo_link')->referencedEntities();
    $link_paragraph = reset($referenced_link_paragraph);

    if (!($link_paragraph instanceof ParagraphInterface)) {
      return NULL;
    }

    /** @var \Drupal\paragraphs\ParagraphInterface $translated_link_paragraph */
    $translated_link_paragraph = $this->entityRepository->getTranslationFromContext($link_paragraph);

    $is_internal_link_paragraph = $this->helperFunctions->isInternalLinkParagraph($translated_link_paragraph);
    if ($is_internal_link_paragraph) {
      $internal_link_details = $this->helperFunctions->getInternalLinkParagraphContents($translated_link_paragraph);

      if (isset($internal_link_details)) {
        [$link_url, $node_id] = $internal_link_details;
      }
    }
    else {
      $external_link_details = $this->helperFunctions->getExternalLinkParagraphContents($translated_link_paragraph);

      if (isset($external_link_details)) {
        [$link_url] = $external_link_details;
      }
    }

    if ($logo_paragraph->hasField('field_link_aria_label') && !$logo_paragraph->get('field_link_aria_label')->isEmpty()) {
      $link_aria_label = $logo_paragraph->get('field_link_aria_label')->getString();
    }

    return [$link_url ?? '', $link_aria_label ?? '', $node_id ?? NULL];
  }

}
