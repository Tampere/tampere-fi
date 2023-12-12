<?php

namespace Drupal\tre_search_api_solr_customization\Plugin\Block;

use Drupal\Core\Cache\Cache;
use Drupal\tre_search_api_solr_customization\Plugin\Derivative\SearchSwitcherBlocks;
use Drupal\views\Plugin\Block\ViewsBlockBase;
use Drupal\views\Views;

/**
 * Provides a block to display the a search switcher component.
 *
 * @Block(
 *   id = "tre_search_switcher_block",
 *   admin_label = @Translation("Search switcher"),
 *   forms = {
 *     "settings_tray" = FALSE,
 *   },
 *   deriver = "Drupal\tre_search_api_solr_customization\Plugin\Derivative\SearchSwitcherBlocks"
 * )
 */
final class SearchSwitcherBlock extends ViewsBlockBase {

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    // Identical to
    // \Drupal\views\Plugin\Block\ViewsExposedFilterBlock::getCacheContexts().
    $contexts = $this->view->display_handler->getCacheMetadata()->getCacheContexts();
    return Cache::mergeContexts(parent::getCacheContexts(), $contexts);
  }

  /**
   * {@inheritdoc}
   */
  public function build() {

    $view_id = $this->view->id();
    $search_views = SearchSwitcherBlocks::VIEWS;
    if (!in_array($view_id, array_keys($search_views), TRUE)) {
      return [];
    }

    unset($search_views[$view_id]);
    $other_view_info = end($search_views);
    $other_view = Views::getView($other_view_info['id']);
    $filters = $this->view->getExposedInput();
    // Since both views use 's' as the name of the search parameter, strip off
    // everything else from the query parameters so that
    // a) the other view behaves correctly and
    // b) the link to the other view only includes the 's' query parameter.
    $actual_filters['s'] = $filters['s'] ?? NULL;
    $other_view->setExposedInput($actual_filters);
    $other_view->execute($other_view_info['display_id']);
    $num_results_in_other_view = $other_view->total_rows;

    $url = $other_view->getUrl(NULL, $other_view_info['display_id']);
    $url->setOption('query', $actual_filters);

    $titles = [
      'search' => $this->t('Search results from Tampere.fi'),
      'search_ext' => $this->t('Search results from other sites of the city'),
    ];

    $is_search = $view_id === 'search';
    $is_external_search = $view_id === 'search_ext';

    return [
      '#theme' => 'tre_search_api_solr_customization_switcher_tabs',
      '#tabs' => [
        [
          'title' => $titles['search'],
          'url' => $is_search ? NULL : $url,
          'count' => $is_search ? NULL : $num_results_in_other_view,
        ],
        [
          'title' => $titles['search_ext'],
          'url' => $is_external_search ? NULL : $url,
          'count' => $is_external_search ? NULL : $num_results_in_other_view,
        ],
      ],
    ];
  }

}
