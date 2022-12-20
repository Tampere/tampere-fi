<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\tre_preprocess\TrePreProcessPluginBase;
use Drupal\tre_preprocess\Traits\EntityIdFromPatternContextTrait;

/**
 * Card with links pattern preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.pattern_card_with_links",
 *   hook = "pattern_card_with_links"
 * )
 */
class CardWithLinksPattern extends TrePreProcessPluginBase {

  use EntityIdFromPatternContextTrait;

}
