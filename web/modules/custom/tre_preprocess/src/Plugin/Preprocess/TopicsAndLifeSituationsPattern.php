<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\tre_preprocess\TrePreProcessPluginBase;
use Drupal\tre_preprocess\Traits\EntityIdFromPatternContextTrait;

/**
 * Topics and life situations pattern preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.pattern_topics_and_life_situations",
 *   hook = "pattern_topics_and_life_situations"
 * )
 */
class TopicsAndLifeSituationsPattern extends TrePreProcessPluginBase {

  use EntityIdFromPatternContextTrait;

}
