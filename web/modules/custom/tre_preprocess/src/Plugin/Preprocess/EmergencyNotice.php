<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\Core\Field\FieldItemList;
use Drupal\preprocess\PreprocessPluginBase;

/**
 * Emergency notice node preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.node__emergency_notice",
 *   hook = "node__emergency_notice"
 * )
 */
class EmergencyNotice extends PreprocessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $node = $variables['node'];
    $link_field = $node->get('field_more_information_link');
    if (!$link_field->isEmpty()) {
      $url = $this->getLinkFieldUrl($link_field);
      $variables['link_url'] = $url;
    }

    return $variables;
  }

  /**
   * Extracts the URL from a link field.
   *
   * @param \Drupal\Core\Field\FieldItemList $link_field
   *   The link field whose URL is to be extracted.
   *
   * @return string|null
   *   The URL as a string if successful. Null if unsuccessful.
   */
  protected function getLinkFieldUrl(FieldItemList $link_field) : ?string {
    $url = NULL;

    /** @var \Drupal\link\Plugin\Field\FieldType\LinkItem $link_item */
    $link_item = $link_field->first();

    if (!$link_item->isEmpty()) {
      $url = $link_item->getUrl()->toString();
    }

    return $url;
  }

}
