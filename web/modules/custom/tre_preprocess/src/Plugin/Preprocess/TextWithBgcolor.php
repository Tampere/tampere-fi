<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\media\MediaInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;

/**
 * Text with background color paragraph preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.paragraph__text_with_bgcolor",
 *   hook = "paragraph__text_with_bgcolor"
 * )
 */
class TextWithBgcolor extends TrePreProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $paragraph = $variables['paragraph'];

    if ($paragraph instanceof ParagraphInterface) {
      /** @var \Drupal\paragraphs\ParagraphInterface $translated_paragraph */
      $translated_paragraph = $this->entityRepository->getTranslationFromContext($paragraph);
      $color_text_box_alignment = $translated_paragraph->get('field_color_textbox_alignment')->getValue();
      $color_text_box_column_width = $translated_paragraph->get('field_columns')->getValue();

      $width_is_half = FALSE;
      $width_is_third = FALSE;

      if (isset($color_text_box_column_width)) {
        $width_is_half = in_array('half', $color_text_box_column_width[0], TRUE);
        $width_is_third = in_array('third', $color_text_box_column_width[0], TRUE);
      }

      $color_textbox_textcontent = $translated_paragraph->get('field_text_bgcolor')->view('default');
      $column_with_background = [
        "content" => $color_textbox_textcontent,
        "type" => "text",
      ];

      $textbox_modifiers = [];

      if ($width_is_third) {
        array_push($textbox_modifiers, 'third');
      }

      $is_reverse = FALSE;
      $plain_textbox_textcontent = [];

      if ($width_is_half || $width_is_third) {
        $plain_textbox_textcontent = $translated_paragraph->get('field_text')->view('default');

        if (isset($color_text_box_alignment) && in_array('right', $color_text_box_alignment[0], TRUE)) {
          array_push($textbox_modifiers, 'reverse');
          $is_reverse = TRUE;
        }
      }

      if (!empty($textbox_modifiers)) {
        $variables['textbox_modifiers'] = $textbox_modifiers;
      }

      $text_or_image = $translated_paragraph->get('field_text_or_image')->getValue();
      $column_without_background = [];

      if ($width_is_half || $width_is_third) {
        if (!empty($text_or_image) && $text_or_image[0]['value'] === 'image') {
          /** @var \Drupal\media\MediaInterface $media_entity */
          $media_entity = $translated_paragraph->get('field_image_without_background')->entity;
          $image = [];
          if ($media_entity instanceof MediaInterface) {
            $view_builder = $this->entityTypeManager->getViewBuilder('media');
            $image = $view_builder->view($media_entity, 'article_main_image_view_mode');
          }

          $column_without_background = [
            "content" => $image,
            "type" => "image",
          ];
        }
        else {
          $column_without_background = [
            "content" => $plain_textbox_textcontent,
            "type" => "text",
          ];
        }
      }

      $textbox_contents = [];

      if ($is_reverse) {
        if (!empty($column_without_background)) {
          array_push($textbox_contents, $column_without_background);
        }
        array_push($textbox_contents, $column_with_background);
      }
      else {
        array_push($textbox_contents, $column_with_background);
        if (!empty($column_without_background)) {
          array_push($textbox_contents, $column_without_background);
        }
      }
      $variables['textbox_contents'] = $textbox_contents;

    }

    return $variables;
  }

}
