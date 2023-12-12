<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\twig_tweak\TwigTweakExtension;

/**
 * Preprocessing for the search page.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.views_views__search_ext",
 *   hook = "views_view__search_ext"
 * )
 */
class ExternalSearch extends SearchPreProcessBase {
  protected const FACET_BLOCK_IDS = [
    'site_domain' => 'site_domain_facet',
  ];

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $variables = parent::preprocess($variables);
    $variables['extra_facets'] = TwigTweakExtension::drupalEntity('block', 'searchswitcherblocksearch_extpage_1', 'full', NULL, FALSE);
    return $variables;
  }

}
