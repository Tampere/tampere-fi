<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\tre_preprocess\TrePreProcessPluginBase;
use Drupal\tre_preprocess\Traits\LastPageKeyForPagerTrait;

/**
 * Pager preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.pager__portfolio_content_listing",
 *   hook = "pager__portfolio_content_listing"
 * )
 */
class PagerPortfolioContentListing extends TrePreProcessPluginBase {

  use LastPageKeyForPagerTrait;

}
