<?php

namespace Drupal\tre_preprocess_script\Plugin\Preprocess;

use Drupal\Core\Render\Markup;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\tre_preprocess\TrePreProcessPluginBase;

/**
 * Preprocess plugin for paragraph--script.
 *
 * @Preprocess(
 *   id = "tre_preprocess_script.preprocess.paragraph__script",
 *   hook = "paragraph__script"
 * )
 */
class Script extends TrePreProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    /** @var \Drupal\paragraphs\Entity\Paragraph $paragraph */
    $paragraph = $variables['paragraph'];

    if ($paragraph->hasField('field_script_syntax') && !$paragraph->get('field_script_syntax')->isEmpty()) {
      $raw_script = $paragraph->get('field_script_syntax')->value;

      $variables['script_syntax'] = Markup::create($raw_script);
    }

    return $variables;
  }

}
