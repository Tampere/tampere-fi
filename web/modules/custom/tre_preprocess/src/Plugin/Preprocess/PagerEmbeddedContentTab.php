<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\tre_preprocess\TrePreProcessPluginBase;
use Drupal\tre_preprocess\Traits\LastPageKeyForPagerTrait;

/**
 * Pager preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.pager__embedded_content_tab",
 *   hook = "pager__embedded_content_tab"
 * )
 */
class PagerEmbeddedContentTab extends TrePreProcessPluginBase {

  use LastPageKeyForPagerTrait;

}
