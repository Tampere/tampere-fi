<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\tre_preprocess\TrePreProcessPluginBase;

/**
 * Link single field preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.field.link_single",
 *   hook = "field__field_link_single"
 * )
 */
class LinkSingleField extends TrePreProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $field = $variables['items'][0]['content'];
    $variables['link_is_external'] = $field['#url']->isExternal();
    return $variables;
  }

}
