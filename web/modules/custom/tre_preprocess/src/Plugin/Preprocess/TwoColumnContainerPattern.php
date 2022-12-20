<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\tre_preprocess\TrePreProcessPluginBase;
use Drupal\tre_preprocess\Traits\EntityIdFromPatternContextTrait;

/**
 * Two-column container pattern preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.pattern_two_column_container",
 *   hook = "pattern_two_column_container"
 * )
 */
class TwoColumnContainerPattern extends TrePreProcessPluginBase {

  use EntityIdFromPatternContextTrait;

}
