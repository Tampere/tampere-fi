<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

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
      $textbox_modifiers = [];

      $color_textbox_textcontent = $translated_paragraph->get('field_text_bgcolor')->view('default');
      $plain_textbox_textcontent = NULL;
      $plain_textfield = $translated_paragraph->get('field_text');

      if (!$plain_textfield->isEmpty() && (in_array('half', $color_text_box_column_width[0], TRUE) || in_array('third', $color_text_box_column_width[0], TRUE))) {
        $plain_textbox_textcontent = [$plain_textfield->view('default')];
      }

      if (isset($color_text_box_column_width)) {
        if (in_array('third', $color_text_box_column_width[0], TRUE)) {
          if (!$translated_paragraph->get('field_text')->isEmpty()) {
            array_push($textbox_modifiers, 'third');
          }
        }
      }

      $is_reverse = FALSE;

      if (isset($color_text_box_alignment) && !is_null($plain_textbox_textcontent)) {
        if (in_array('right', $color_text_box_alignment[0], TRUE)) {
          array_push($textbox_modifiers, 'reverse');
          $is_reverse = TRUE;
        }
      }

      if (!empty($textbox_modifiers)) {
        $variables['textbox_modifiers'] = $textbox_modifiers;
      }

      $textbox_contents = [];

      if ($is_reverse) {
        if (!is_null($plain_textbox_textcontent)) {
          array_push($textbox_contents, [$plain_textbox_textcontent]);
        }
        array_push($textbox_contents, [$color_textbox_textcontent]);
        $variables['textbox_contents'] = $textbox_contents;
      }
      else {
        array_push($textbox_contents, [$color_textbox_textcontent]);
        if (!is_null($plain_textbox_textcontent)) {
          array_push($textbox_contents, [$plain_textbox_textcontent]);
        }
        $variables['textbox_contents'] = $textbox_contents;
      }
    }
    return $variables;
  }

}
