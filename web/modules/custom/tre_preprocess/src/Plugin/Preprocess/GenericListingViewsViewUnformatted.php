<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\node\NodeInterface;
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

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {

    $rows = $variables['rows'];

    foreach ($rows as $key => $row) {
      $node = $row['content']['#node'];

      if (!($node instanceof NodeInterface)) {
        continue;
      }

      /** @var \Drupal\node\NodeInterface $translated_node */
      $translated_node = $this->entityRepository->getTranslationFromContext($node);
      $title = $translated_node->getTitle();
      $url = $translated_node->toUrl()->toString();

      $variables['rows'][$key]['content'] = [
        '#type' => 'pattern',
        '#id' => 'rss_card',
        '#variant' => 'colorful',
        '#fields' => [
          'rss_card__link__url' => $url,
          'rss_card__heading' => $title,
        ],
      ];
    }

    return $variables;
  }

}
