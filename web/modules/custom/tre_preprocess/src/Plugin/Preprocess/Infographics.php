<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\paragraphs\ParagraphInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;

/**
 * Infographics paragraph preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.paragraph__infographs",
 *   hook = "paragraph__infographs"
 * )
 */
class Infographics extends TrePreProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $paragraph = $variables['paragraph'];

    if (!($paragraph instanceof ParagraphInterface)) {
      return $variables;
    }

    /** @var \Drupal\paragraphs\ParagraphInterface $translated_infographics_paragraph */
    $translated_infographics_paragraph = $this->entityRepository->getTranslationFromContext($paragraph);

    $has_heading_field = $translated_infographics_paragraph->hasField('field_infograph_heading');
    if ($has_heading_field && !$translated_infographics_paragraph->get('field_infograph_heading')->isEmpty()) {
      $heading_field_value = $translated_infographics_paragraph->get('field_infograph_heading')->getValue();
      // The heading field only supports one heading so getting first item
      // only.
      $heading_field_value = reset($heading_field_value);

      // The heading size is a string in the form of 'h' + level number
      // (e.g. h2), but the templates specifically require only the level.
      $heading_level = str_replace('h', '', $heading_field_value['size']);

      $variables['infographics_heading_text'] = $heading_field_value['text'];
      $variables['infographics_heading_level'] = $heading_level;
    }

    $infographic_paragraphs = $translated_infographics_paragraph->get('field_infograph')->referencedEntities();

    $infographics = [];
    foreach ($infographic_paragraphs as $infographic_paragraph) {
      if (!($infographic_paragraph instanceof ParagraphInterface)) {
        continue;
      }

      /** @var \Drupal\paragraphs\ParagraphInterface $translated_infographic_paragraph */
      $translated_infographic_paragraph = $this->entityRepository->getTranslationFromContext($infographic_paragraph);

      $infographic_number = $translated_infographic_paragraph->get('field_number_liftup')->getString();
      $infographic_description = $translated_infographic_paragraph->get('field_short_description')->getString();

      $available_colors = $this->helperFunctions->getColorFieldAllowedValues('paragraph', 'infograph');

      $infographic_modifiers = NULL;
      if ($available_colors) {
        $selected_color = $translated_infographic_paragraph->get('field_color')->getString();
        $selected_color_index = array_search($selected_color, $available_colors);

        if ($selected_color_index > 0) {
          $infographic_modifiers = ['alt'];
        }
      }

      $infographics[] = [
        'infograph__modifiers' => $infographic_modifiers,
        'infograph__heading' => $infographic_number,
        'infograph__content' => $infographic_description,
      ];
    }

    $variables['infographics'] = $infographics;

    return $variables;
  }

}
