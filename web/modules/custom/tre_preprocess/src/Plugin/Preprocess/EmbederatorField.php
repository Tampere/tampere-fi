<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\tre_preprocess\TrePreProcessPluginBase;

/**
 * Embederator field preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.field.embederator",
 *   hook = "field__embederator__embed_id"
 * )
 */
class EmbederatorField extends TrePreProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    if ($variables['element']['#entity_type'] == 'embederator') {
      // The Embederator module uses the 'full_html' format by default and
      // the filter by that ID has been disabled on this site. We instead use
      // the filter created for the sole purpose of enabling and configuring
      // embeds through Embederator.
      $variables['items'][0]['content']['#format'] = 'embederator_html';
    }

    return $variables;
  }

}
