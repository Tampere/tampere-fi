<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\paragraphs\ParagraphInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;

// phpcs:disable Drupal.Files.LineLength.TooLong
/**
 * Process accordions extended paragraph pattern preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.paragraph__process_accordions_extended",
 *   hook = "pattern_accordion_item__variant_process"
 * )
 */
class ProcessAccordionsExtendedParagraphPattern extends TrePreProcessPluginBase {
// phpcs:enable

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    /** @var \Drupal\ui_patterns\Element\PatternContext $context */
    $context = $variables['context'];
    $paragraph = $context->getProperty('entity');

    if (!($paragraph instanceof ParagraphInterface)) {
      return $variables;
    }

    if (
      !$paragraph->hasField('field_accordion_heading')
      || !$paragraph->hasField('field_process_phase_status')
    ) {
      return $variables;
    }
    /** @var \Drupal\paragraphs\ParagraphInterface $translated_paragraph */
    $translated_paragraph = $this->entityRepository->getTranslationFromContext($paragraph);
    $this->renderer->addCacheableDependency($variables, $translated_paragraph);

    $phase_status_value = 'future';
    if (!$translated_paragraph->get('field_process_phase_status')->isEmpty()) {
      $phase_status_value = $translated_paragraph->get('field_process_phase_status')->getValue()[0]['value'];
    }
    $variables['ac__item__modifiers'] = $phase_status_value;
    $variables['process_accordion'] = TRUE;
    $accordion_heading = $translated_paragraph->get('field_accordion_heading')->getString();
    $variables['accordion_heading'] = $accordion_heading;
    $variables['ac__base_class'] = 'process-accordion';
    $variables['id'] = $translated_paragraph->id();

    $phase_status_label = $translated_paragraph->get('field_process_phase_status')->getFieldDefinition()->getLabel();
    $phase_label = $translated_paragraph->field_process_phase_status->getSetting('allowed_values')[$translated_paragraph->field_process_phase_status->value];
    $variables['accordion_item_aria_label'] = "$accordion_heading, $phase_status_label: $phase_label";

    return $variables;
  }

}
