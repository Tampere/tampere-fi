<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\group\Entity\GroupInterface;
use Drupal\node\NodeInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;
use Drupal\views\Views;

/**
 * Preprocessing for 'portfolio_listing' nodes.
 *
 * Creates a portfolio list view (i.e. 'group_portfolios') render array for the
 * group that the node belongs in and adds it to the content to be rendered on
 * the node page.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.node__portfolio_listing",
 *   hook = "node__portfolio_listing"
 * )
 */
class NodePortfolioListing extends TrePreProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $node = $variables['node'];

    if (!($node instanceof NodeInterface)) {
      return $variables;
    }

    $portfolio_listing_view = $this->getRenderedView($node);

    if (empty($portfolio_listing_view)) {
      return $variables;
    }

    $variables['content']['portfolio_listing_view'] = $portfolio_listing_view;

    return $variables;
  }

  /**
   * Renders a portfolio listing view for a given node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity to base the view on.
   *
   * @return array|null
   *   The view as a renderable array if successful. Null if unsuccessful.
   */
  protected function getRenderedView(NodeInterface $node) {

    $view = Views::getView('group_portfolios');

    $current_language_id = $this->languageManager->getCurrentLanguage()->getId();

    $view->setDisplay(`portfolios_{$current_language_id}`);

    $node_group = $this->helperFunctions->getNodeGroup($node);

    if (!($node_group instanceof GroupInterface)) {
      return NULL;
    }

    $group_id = $node_group->id();

    $view->setArguments([$group_id]);

    $view->preExecute();
    $view->execute();
    $view->postExecute();

    $renderable = $view->buildRenderable();

    if ($renderable) {
      $this->renderer->addCacheableDependency($renderable, $view);
    }

    return $renderable;
  }

}
