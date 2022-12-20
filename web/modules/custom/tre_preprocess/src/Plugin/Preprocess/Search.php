<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\facets\FacetInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;
use Drupal\twig_tweak\TwigTweakExtension;

/**
 * Preprocessing for the search page.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.views_views__search",
 *   hook = "views_view__search"
 * )
 */
class Search extends TrePreProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {

    $facets = self::getFacetsForViewDisplay($variables['id'], $variables['display_id']);

    $facets_with_results = array_filter($facets, function (FacetInterface $facet) {
      return !empty($facet->getResults());
    });
    $variables['content_facets'] = self::getFacetBlocksForFacets($facets_with_results);

    $active_facets = array_filter($facets, function (FacetInterface $facet) {
      return !empty($facet->getActiveItems());
    });

    if (!empty($active_facets)) {
      $language = $this->languageManager->getCurrentLanguage()->getId();

      $items = $variables['pager']['#parameters']['s'];

      // Use current path to preserve search path arguments.
      $current_path = $this->currentPath->getPath();

      if ($language == 'fi') {
        $url = $current_path . '?s=' . $items;
      }
      else {
        $url = '/' . $language . $current_path . '?s=' . $items;
      }

      $variables['remove_facets__link'] = $url;
    }

    return $variables;
  }

  /**
   * Gets facets with results per a view and its display.
   *
   * @param string $view_id
   *   The view id.
   * @param string $display_id
   *   The display id.
   *
   * @return array
   *   The facets for the view display.
   */
  private static function getFacetsForViewDisplay(string $view_id, string $display_id): array {
    /** @var \Drupal\facets\FacetManager\DefaultFacetManager $facet_manager */
    $facet_manager = \Drupal::service('facets.manager');
    $facet_source_id_parts = [
      'search_api:views_page',
      $view_id,
      $display_id,
    ];
    $facet_source_id = implode('__', $facet_source_id_parts);
    $facets = $facet_manager->getFacetsByFacetSourceId($facet_source_id);
    $facet_manager->updateResults($facet_source_id);

    return $facets;
  }

  /**
   * Returns block render arrays for given facets.
   *
   * @param \Drupal\facets\FacetInterface[] $facets
   *   The facets to fetch the render arrays for.
   *
   * @return array
   *   The renderable arrays for each facet passed in.
   */
  private static function getFacetBlocksForFacets(array $facets): array {
    $content_facet_block_ids = [
      'life_situation' => 'life_situation_facet',
      'location' => 'location_facet',
      'mapped_content_type' => 'content_type_facet',
      'topic' => 'topic_facet',
    ];
    $content_facet_blocks = [];

    foreach ($facets as $facet) {
      $content_facet_blocks[$facet->getWeight()] = TwigTweakExtension::drupalEntity('block', $content_facet_block_ids[$facet->id()], 'full', NULL, FALSE);
    }

    // The keys in $content_facet_blocks now match the order on
    // /admin/config/search/facets, so let's sort the array accordingly to
    // display the facet blocks in the correct order..
    ksort($content_facet_blocks);

    return $content_facet_blocks;
  }

}
