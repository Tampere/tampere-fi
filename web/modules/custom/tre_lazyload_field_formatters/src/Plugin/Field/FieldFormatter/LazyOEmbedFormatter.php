<?php

namespace Drupal\tre_lazyload_field_formatters\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\media\Plugin\Field\FieldFormatter\OEmbedFormatter;

/**
 * Plugin implementation of the 'lazy_oembed' formatter.
 *
 * @FieldFormatter(
 *   id = "lazy_oembed",
 *   label = @Translation("OEmbed (Lazy-load)"),
 *   field_types = {
 *     "link",
 *     "string",
 *     "string_long",
 *   }
 * )
 */
class LazyOEmbedFormatter extends OEmbedFormatter {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = parent::viewElements($items, $langcode);

    foreach ($elements as $delta => $element) {
      // Only modify iframe oembeds.
      if ($element['#tag'] !== 'iframe') {
        continue;
      }
      // Despite the addition of the loading attribute configuration in
      // Drupal 10, we opt to force all oembed iframes to lazy loading.
      $elements[$delta]['#attributes']['loading'] = 'lazy';
      $elements[$delta]['#attributes']['class'][] = 'lazyload';

      // Removing the 'src' attribute from oembed iframes helps with cookie
      // consent. The data-src attribute is used by the cookieinformation
      // module.
      $elements[$delta]['#attributes']['data-src'] = $elements[$delta]['#attributes']['src'];
      unset($elements[$delta]['#attributes']['src']);
    }

    return $elements;
  }

}
