<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\tre_preprocess\Traits\ListingRowPatternTrait;
use Drupal\tre_preprocess\TrePreProcessPluginBase;

/**
 * RSS list unformatted views view preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.views_view_unformatted__rss_list",
 *   hook = "views_view_unformatted__rss_list"
 * )
 */
class RssListViewsViewUnformatted extends TrePreProcessPluginBase {

  use ListingRowPatternTrait;

}
