<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\tre_preprocess\Traits\EntityIdFromPatternContextTrait;
use Drupal\tre_preprocess\TrePreProcessPluginBase;

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
