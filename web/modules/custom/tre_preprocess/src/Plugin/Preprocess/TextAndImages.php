<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\paragraphs\ParagraphInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;

/**
 * Text and images paragraph preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.paragraph__text_and_images",
 *   hook = "paragraph__text_and_images"
 * )
 */
class TextAndImages extends TrePreProcessPluginBase {

  /**
   * The image view modes key'd by the image size value.
   */
  const AVAILABLE_IMAGE_DISPLAY_TYPES = [
    'large' => 'positioned_image_large_view_mode',
    'medium' => 'positioned_image_medium_view_mode',
    'small' => 'positioned_image_small_view_mode',
  ];

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

    $text_and_image_paragraphs = $translated_paragraph->get('field_text_and_images')->referencedEntities();

    $text_and_image_paragraphs_count = count($text_and_image_paragraphs);
    $text_and_image_last_index = $text_and_image_paragraphs_count - 1;
    $text_and_image_second_to_last_index = $text_and_image_last_index - 1;

    $text_and_images = [];
    foreach ($text_and_image_paragraphs as $key => $text_or_image_paragraph) {
      if (!($text_or_image_paragraph instanceof ParagraphInterface)) {
        continue;
      }

      /** @var \Drupal\paragraphs\ParagraphInterface $translated_text_or_image_paragraph */
      $translated_text_or_image_paragraph = $this->entityRepository->getTranslationFromContext($text_or_image_paragraph);
      $paragraph_bundle = $translated_text_or_image_paragraph->bundle();

      if ($paragraph_bundle !== 'text' && $paragraph_bundle !== 'positioned_image') {
        continue;
      }

      $paragraph_has_text_field = $translated_text_or_image_paragraph->hasField('field_text');

      if ($paragraph_bundle === 'text') {
        if (!$paragraph_has_text_field) {
          continue;
        }

        $text_content = $translated_text_or_image_paragraph->get('field_text')->view('default');
        $text_and_images[] = $text_content;
        continue;
      }

      $paragraph_has_image_size = $translated_text_or_image_paragraph->hasField('field_image_size') && !$translated_text_or_image_paragraph->get('field_image_size')->isEmpty();
      $paragraph_has_alignment = $translated_text_or_image_paragraph->hasField('field_align') && !$translated_text_or_image_paragraph->get('field_align')->isEmpty();

      if (!$paragraph_has_image_size || !$paragraph_has_alignment) {
        continue;
      }

      $image_size_field_value = $translated_text_or_image_paragraph->get('field_image_size')->getString();
      $image_view_mode = $this->getImageViewMode($image_size_field_value);
      $image_content = $this->entityTypeManager->getViewBuilder('paragraph')->view($translated_text_or_image_paragraph, $image_view_mode);

      $image_alignment = $translated_text_or_image_paragraph->get('field_align')->getString();
      $image_content['#attributes']['class'][] = 'figure--align-' . $image_alignment;

      $text_and_images[] = $image_content;

      // Don't modify text and images content order if the second to last index
      // would be a negative value.
      if ($text_and_image_second_to_last_index < 0) {
        continue;
      }

      // Move image to second to last item of the array if it is the last
      // to make sure that the float works as intended except if the
      // image is large.
      if ($key !== $text_and_image_last_index || $image_size_field_value === "large") {
        continue;
      }

      $second_to_last_item = $text_and_images[$text_and_image_second_to_last_index];

      // Don't move items around if the second to last item is also an image.
      // The items are text field render arrays for text and paragraphs for
      // images.
      if (isset($second_to_last_item["#paragraph"])) {
        continue;
      }

      unset($text_and_images[$text_and_image_second_to_last_index]);
      $text_and_images[] = $second_to_last_item;
    }

    $variables['text_and_images'] = $text_and_images;

    return $variables;
  }

  /**
   * Returns the view mode that matches the selected image size value.
   *
   * @param string $image_size_value
   *   The value from the image size field.
   *
   * @return string
   *   The correct view mode for the given image size.
   */
  protected function getImageViewMode($image_size_value): string {
    if (!array_key_exists($image_size_value, self::AVAILABLE_IMAGE_DISPLAY_TYPES)) {
      return self::AVAILABLE_IMAGE_DISPLAY_TYPES['large'];
    }

    return self::AVAILABLE_IMAGE_DISPLAY_TYPES[$image_size_value];
  }

}
