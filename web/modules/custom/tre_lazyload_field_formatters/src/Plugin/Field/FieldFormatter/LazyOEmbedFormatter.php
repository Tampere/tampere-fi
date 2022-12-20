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
      $elements[$delta]['#attributes']['data-src'] = $elements[$delta]['#attributes']['src'];
      $elements[$delta]['#attributes']['class'][] = 'lazyload';
      $elements[$delta]['#attributes']['loading'] = 'lazy';
      unset($elements[$delta]['#attributes']['src']);
    }

    return $elements;
  }

}
