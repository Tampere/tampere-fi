<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\preprocess\PreprocessPluginBase;

/**
 * Map embed paragraph type preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.paragraph__map_embed",
 *   hook = "paragraph__map_embed"
 * )
 */
class MapEmbed extends PreprocessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $paragraph = $variables['paragraph'];

    $embed_code_field_value = $paragraph->get('field_map_embed_code')->getString();

    $matched = preg_match('/\\ssrc="([^"]+)"/', $embed_code_field_value, $matches);
    // preg_match() returns 1 if the pattern matches, 0 if it does not,
    // or false if an error occurred.
    if ($matched === 1) {
      $embed_code_src = $matches[1];
      $variables['map_embed_iframe_src'] = filter_var($embed_code_src, FILTER_SANITIZE_URL);
    }

    return $variables;
  }

}
