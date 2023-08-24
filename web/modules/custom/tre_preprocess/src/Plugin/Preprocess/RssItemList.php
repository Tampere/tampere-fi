<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\paragraphs\ParagraphInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;
use Drupal\views\Views;

/**
 * RSS Feeds paragraph preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.paragraph__rss_feed",
 *   hook = "paragraph__rss_feed"
 * )
 */
class RssItemList extends TrePreProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {

    $variables['rss_feed'] = $this->getRenderedView($variables['paragraph']);

    $this->renderer->addCacheableDependency($variables['rss_feed'], $variables['paragraph']);

    return $variables;
  }

  /**
   * Renders a certain view based on paragraph values.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph entity to base the view on.
   *
   * @return array|null
   *   The view as a renderable array if successful. Null if unsuccessful.
   */
  protected function getRenderedView(ParagraphInterface $paragraph) {

    $view = Views::getView('rss_list');

    $sort_order_field = $paragraph->get('field_sort_order');
    if (!empty($sort_order_field)) {
      $sort_order_field_value = $sort_order_field->value;
      if ($sort_order_field_value == 'title') {
        $block_machine_name = 'rss_listing_block_title';
      }
      else {
        $block_machine_name = 'rss_listing_block_date';
      }
    }
    else {
      // Use date_block as a default if sort_order hasn't been chosen yet.
      $block_machine_name = 'rss_listing_block_date';
    }

    if (empty($view) || !$view->access($block_machine_name)) {
      return NULL;
    }

    $view->setDisplay($block_machine_name);

    // Feeds to show -> contextual filter value.
    $selected_feeds = $paragraph->get('field_feed_block')->getString();
    if (empty($selected_feeds)) {
      return NULL;
    }

    $view->setArguments([$selected_feeds]);

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
