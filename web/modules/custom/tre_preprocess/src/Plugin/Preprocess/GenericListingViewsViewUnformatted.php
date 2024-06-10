<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\tre_preprocess\Traits\ListingRowPatternTrait;
use Drupal\tre_preprocess\TrePreProcessPluginBase;

/**
 * Generic listing unformatted views view preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.views_view_unformatted__generic_listing",
 *   hook = "views_view_unformatted__generic_listing"
 * )
 */
class GenericListingViewsViewUnformatted extends TrePreProcessPluginBase {

  use ListingRowPatternTrait;

}
