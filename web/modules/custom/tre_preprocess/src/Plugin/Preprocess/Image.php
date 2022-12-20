<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\paragraphs\ParagraphInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;
use Drupal\tre_preprocess_utility_functions\Utils\HelperFunctionsInterface;

/**
 * Image paragraph type preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.paragraph__image",
 *   hook = "paragraph__image"
 * )
 */
class Image extends TrePreProcessPluginBase {

  /**
   * The view mode to use for images by default.
   */
  const DEFAULT_VIEW_MODE = 'default';

  /**
   * The view mode to use for images when displayed as a thumbnail.
   */
  const THUMBNAIL_VIEW_MODE = 'thumbnail_view_mode';

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

    $has_no_media = $translated_paragraph->get('field_media')->isEmpty() && $translated_paragraph->get('field_media_file_in_other_lang')->isEmpty();

    if ($has_no_media) {
      return $variables;
    }

    $media_field_name = 'field_media';
    if (!$translated_paragraph->get('field_media_file_in_other_lang')->isEmpty()) {
      $media_field_name = 'field_media_file_in_other_lang';
    }

    $display_as_thumbnail = $this->helperFunctions->getFieldValueString($translated_paragraph, 'field_display_as_thumbnail');

    if ($display_as_thumbnail == HelperFunctionsInterface::BOOLEAN_FIELD_TRUE) {
      $variables['image'] = $translated_paragraph->get($media_field_name)->view(self::THUMBNAIL_VIEW_MODE);
      $variables['is_thumbnail'] = TRUE;
    }
    else {
      $variables['image'] = $translated_paragraph->get($media_field_name)->view(self::DEFAULT_VIEW_MODE);
      $variables['is_thumbnail'] = FALSE;
    }

    $image_information = $this->helperFunctions->getImageInformation($translated_paragraph, $media_field_name);

    if ($image_information) {
      [$image_name, $image_src_url, $image_id] = $image_information;
      $variables['#cache']['tags'][] = "media:{$image_id}";
    }

    $display_larger_image_link = $this->helperFunctions->getFieldValueString($translated_paragraph, 'field_display_larger_image_link');
    if ($display_larger_image_link == HelperFunctionsInterface::BOOLEAN_FIELD_TRUE) {
      if (isset($image_name)) {
        $variables['image_name'] = $image_name;
      }

      if (isset($image_src_url)) {
        $variables['original_image_src_url'] = $image_src_url;
      }

      // The image can be displayed as either a link to content or
      // a larger image, not both. When the larger image option has been
      // selected, any content link related selections will be ignored.
      return $variables;
    }

    if (!$translated_paragraph->hasField('field_content_link') || $translated_paragraph->get('field_content_link')->isEmpty()) {
      return $variables;
    }

    $referenced_link_paragraph = $translated_paragraph->get('field_content_link')->referencedEntities();
    $link_paragraph = reset($referenced_link_paragraph);

    if (!($link_paragraph instanceof ParagraphInterface)) {
      return $variables;
    }

    /** @var \Drupal\paragraphs\ParagraphInterface $translated_link_paragraph */
    $translated_link_paragraph = $this->entityRepository->getTranslationFromContext($link_paragraph);

    $is_internal_link_paragraph = $this->helperFunctions->isInternalLinkParagraph($translated_link_paragraph);
    if ($is_internal_link_paragraph) {
      $internal_link_details = $this->helperFunctions->getInternalLinkParagraphContents($translated_link_paragraph);

      if ($internal_link_details) {
        [$link_url, $node_id] = $internal_link_details;

        $variables['#cache']['tags'][] = "node:{$node_id}";
      }
    }
    else {
      [$link_url] = $this->helperFunctions->getExternalLinkParagraphContents($translated_link_paragraph);
    }

    if (isset($link_url) && !empty($link_url)) {
      if (!$translated_paragraph->hasField('field_link_aria_label') || $translated_paragraph->get('field_link_aria_label')->isEmpty()) {
        return $variables;
      }

      $variables['image_content_link_aria_label'] = $translated_paragraph->get('field_link_aria_label')->getString();
      $variables['image_content_link_url'] = $link_url;
    }

    return $variables;
  }

}
