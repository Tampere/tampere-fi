<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\tre_preprocess\TrePreProcessPluginBase;
use Drupal\tre_preprocess\Traits\LastPageKeyForPagerTrait;

/**
 * Pager element preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.pager",
 *   hook = "pager"
 * )
 */
class Pager extends TrePreProcessPluginBase {

  use LastPageKeyForPagerTrait;

}
