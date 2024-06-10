<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\tre_preprocess\Traits\LastPageKeyForPagerTrait;
use Drupal\tre_preprocess\TrePreProcessPluginBase;

/**
 * Pager preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.pager__generic_listing",
 *   hook = "pager__generic_listing"
 * )
 */
class PagerGenericListing extends TrePreProcessPluginBase {

  use LastPageKeyForPagerTrait;

}
