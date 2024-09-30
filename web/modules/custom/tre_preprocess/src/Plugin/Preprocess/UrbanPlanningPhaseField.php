<?php

declare(strict_types=1);

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;

/**
 * Phase field preprocessing for urban planning content.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.field.node.field_phase",
 *   hook = "field__node__field_phase"
 * )
 */
class UrbanPlanningPhaseField extends TrePreProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $node = $variables['element']['#object'];
    if (!($node instanceof NodeInterface)) {
      return $variables;
    }

    $translated_node = $this->entityRepository->getTranslationFromContext($node);

    $phase_value = $variables['element']['#items']->getString();

    $field_data = AutomaticPhaseAccordionParagraph::FIXED_PHASE_FIELDS;
    $corresponding_field = array_filter($field_data, function ($item) use ($phase_value) {
      return $item['corresponding_phase'] == $phase_value;
    });
    $field_name = key($corresponding_field);

    if (!($translated_node instanceof NodeInterface)
      || !$translated_node->hasField($field_name)
      || !($translated_node->get($field_name) instanceof EntityReferenceFieldItemListInterface)
      || $translated_node->get($field_name)->isEmpty()) {
      return $variables;
    }

    $corresponding_paragraphs = $translated_node->get($field_name)->referencedEntities();
    if (count($corresponding_paragraphs) > 1) {
      return $variables;
    }

    $corresponding_paragraph = reset($corresponding_paragraphs);

    if (!($corresponding_paragraph instanceof ParagraphInterface)) {
      return $variables;
    }

    $translated_paragraph = $this->entityRepository->getTranslationFromContext($corresponding_paragraph);

    if (!($translated_paragraph instanceof ParagraphInterface)) {
      return $variables;
    }

    // Rather confusingly, the 'name' field for overriding the phase name has
    // also been named field_phase. The name is identical to the field we are
    // preprocessing here. The difference is that the one we are preprocessing
    // exists in nodes whereas this one exists in paragraphs.
    if (!$translated_paragraph->hasField('field_phase')
      || $translated_paragraph->get('field_phase')->isEmpty()) {
      return $variables;
    }

    $variables['items'][0]['content']['#markup'] = $translated_paragraph->get('field_phase')->getString();

    return $variables;
  }

}
