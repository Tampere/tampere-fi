<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\node\NodeInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;

/**
 * Involvement content pattern preprocessing for involvement_opportunity nodes.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.pattern_involvement_content",
 *   hook = "pattern_involvement_content"
 * )
 */
class InvolvementOpportunityInvolvementContentPattern extends TrePreProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $pattern_context = $variables['context'];

    $bundle = $pattern_context->getProperty('bundle');
    $node = $pattern_context->getProperty('entity');

    if ($node instanceof NodeInterface && $bundle == 'involvement_opportunity' && !$node->isNew()) {
      /** @var \Drupal\node\NodeInterface $node */
      $translated_node = $this->entityRepository->getTranslationFromContext($node);

      /** @var \Drupal\node\NodeInterface $translated_node */
      if ($translated_node->hasField('field_participate_type') && !$translated_node->get('field_participate_type')->isEmpty()) {
        $label = $translated_node->field_participate_type->getSetting('allowed_values')[$translated_node->field_participate_type->value];
        $variables['involvement_content__way'] = $label;
      }
      if ($translated_node->hasField('field_participate_phase') && !$translated_node->get('field_participate_phase')->isEmpty()) {
        $label = $translated_node->field_participate_phase->getSetting('allowed_values')[$translated_node->field_participate_phase->value];
        $variables['involvement_content__phase'] = $label;
      }

      $node_start_time_date = $this->helperFunctions->getNodeFieldDate($translated_node, 'field_start_time');
      $node_end_time_date = $this->helperFunctions->getNodeFieldDate($translated_node, 'field_end_time');

      if (empty($node_start_time_date)) {
        return $variables;
      }

      $formatted_date_string = $this->helperFunctions->getFormattedInvolvementOpportunityDate($node_start_time_date, $node_end_time_date);
      $variables['date_time'] = $formatted_date_string;
    }

    return $variables;
  }

}
