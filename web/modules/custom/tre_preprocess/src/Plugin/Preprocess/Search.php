<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\twig_tweak\TwigTweakExtension;

/**
 * Preprocessing for the search page.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.views_views__search",
 *   hook = "views_view__search"
 * )
 */
class Search extends SearchPreProcessBase {

  protected const FACET_BLOCK_IDS = [
    'life_situation' => 'life_situation_facet',
    'location' => 'location_facet',
    'mapped_content_type' => 'content_type_facet',
    'topic' => 'topic_facet',
  ];

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $variables = parent::preprocess($variables);
    if (isset($variables['view_array']['#arguments'][0]) && $variables['view_array']['#arguments'][0] === 'all') {
      $variables['extra_facets'] = TwigTweakExtension::drupalEntity('block', 'searchswitcherblocksearchpage_1', 'full', NULL, FALSE);
    }

    return $variables;
  }

}
