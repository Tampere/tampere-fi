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

    $infographic_paragraphs = $paragraph->get('field_infograph')->referencedEntities();

    $infographics = [];
    foreach ($infographic_paragraphs as $infographic_paragraph) {
      if ($infographic_paragraph instanceof ParagraphInterface) {
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
    }

    $variables['infographics'] = $infographics;

    return $variables;
  }

}
