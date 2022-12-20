<?php

namespace Drupal\tre_link_field_formatter\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\link\Plugin\Field\FieldFormatter\LinkFormatter;

/**
 * Plugin implementation of the 'link' formatter.
 *
 * @FieldFormatter(
 *   id = "tre_absolute_url_text",
 *   label = @Translation("Link with absolute URL as text"),
 *   field_types = {
 *     "link"
 *   }
 * )
 */
class AbsoluteUrlLinkText extends LinkFormatter {

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $summary[] = $this->t('Uses the absolute URL as the link text.');

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $options = ['absolute' => TRUE];

    /** @var \Drupal\link\Plugin\Field\FieldType\LinkItem $item */
    foreach ($items as $item) {
      $url = $this->buildUrl($item);
      $url->setOptions($options);
      $url_string = $url->toString();
      $link_title = preg_replace('(^(?:https?:\/\/)?(?:www\.)?)', '', $url_string);

      $item->set('title', $link_title);
    }

    $elements = parent::viewElements($items, $langcode);

    foreach ($elements as &$element) {
      $element['#options']['attributes']['class'][] = 'link';
    }

    return $elements;
  }

}
