<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\paragraphs\ParagraphInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;

/**
 * Process accordion item paragraph preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.paragraph__process_accordion_item",
 *   hook = "paragraph__process_accordion_item"
 * )
 */
class ProcessAccordionItem extends TrePreProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $paragraph = $variables['paragraph'];

    if ($paragraph instanceof ParagraphInterface) {
      /** @var \Drupal\paragraphs\ParagraphInterface $translated_paragraph */
      $translated_paragraph = $this->entityRepository->getTranslationFromContext($paragraph);

      $label = $translated_paragraph->field_process_phase_status->getSetting('allowed_values')[$translated_paragraph->field_process_phase_status->value];

      $variables['phase_label'] = $label;
    }

    return $variables;
  }

}
