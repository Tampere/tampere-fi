<?php

namespace Drupal\tre_preprocess\Traits;

use Drupal\node\NodeInterface;
use Drupal\feeds\Entity\Feed;

/**
 * Trait for using a pattern for listing rows.
 */
trait ListingRowPatternTrait {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $view = $variables['view'];

    $feed = Feed::load($view->args[0]);
    if ($feed) {
      $feed_type = $feed->bundle();
      $variables['feed_type'] = $feed_type;
    }

    $rows = $variables['rows'];

    foreach ($rows as $key => $row) {
      $node = $row['content']['#node'];

      if (!($node instanceof NodeInterface)) {
        continue;
      }

      /** @var \Drupal\node\NodeInterface $translated_node */
      $translated_node = $this->entityRepository->getTranslationFromContext($node);
      $title = $translated_node->getTitle();

      [$link_url, $icon_name] = $this->getListingRowItemLinkDetails($translated_node);

      $variables['rows'][$key]['content'] = [
        '#type' => 'pattern',
        '#id' => 'rss_card',
        '#fields' => [
          'rss_card__link__url' => $link_url,
          'rss_card__icon_name' => $icon_name,
        ],
      ];

      if ($feed_type !== 'rss_feed_notices') {
        $variables['rows'][$key]['content']['#fields']['rss_card__heading'] = $title;
        if ($node->bundle() !== 'rss_item') {
          $variables['rows'][$key]['content']['#variant'] = 'colorful';
        }
        else {
          $date = $translated_node->getCreatedTime();
          $formatted_date = $this->dateFormatter->format($date, 'custom', 'j.n.Y');
          $variables['rows'][$key]['content']['#fields']['rss_card__date'] = $formatted_date;
        }
      }
      else {
        $license_plate = $translated_node->get('field_license_plate')->value;
        $variables['rows'][$key]['content']['#fields']['rss_card__license_plate'] = $license_plate;

        $construction_site = $translated_node->get('field_construction_site')->value;
        $variables['rows'][$key]['content']['#fields']['rss_card__construction_site'] = $construction_site;

        $description = $translated_node->get('body')->value;
        $variables['rows'][$key]['content']['#fields']['rss_card__description'] = $description;

        $date = $translated_node->get('field_publication_time')->date->getTimestamp();
        $formatted_date = $this->dateFormatter->format($date, 'custom', 'j.n.Y');
        $variables['rows'][$key]['content']['#fields']['rss_card__publication_date'] = $formatted_date;

        $end_date = $translated_node->get('field_publication_end_time')->date->getTimestamp();
        $formatted_date = $this->dateFormatter->format($end_date, 'custom', 'j.n.Y');
        $variables['rows'][$key]['content']['#fields']['rss_card__publication_end_date'] = $formatted_date;
      }
    }

    return $variables;
  }

  /**
   * Gets the URL and icon name for the listing row item based on the bundle.
   *
   * RSS item pages are not visible to the end users so they contain a separate
   * link field for the URL. All other items should use the listing row node
   * URL.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The listing row item node whose URL is to be determined.
   *
   * @return array|null
   *   The URL and icon name in an array if successful. Null if unsuccessful.
   */
  private function getListingRowItemLinkDetails(NodeInterface $node) {
    if (!($node instanceof NodeInterface)) {
      return NULL;
    }

    $icon_name = "arrow";

    if ($node->bundle() !== 'rss_item') {
      return [$node->toUrl()->toString(), $icon_name];
    }

    if (!$node->hasField('field_link_single') || $node->get('field_link_single')->isEmpty()) {
      return NULL;
    }

    $link_field = $node->get('field_link_single');

    if ($link_field->isEmpty()) {
      return NULL;
    }

    /** @var \Drupal\link\Plugin\Field\FieldType\LinkItem $link_item */
    $link_item = $link_field->first();

    if ($link_item->isEmpty()) {
      return NULL;
    }

    if ($link_item->isExternal()) {
      $icon_name = "external";
    }

    return [$link_item->getUrl()->toString(), $icon_name];
  }

}
