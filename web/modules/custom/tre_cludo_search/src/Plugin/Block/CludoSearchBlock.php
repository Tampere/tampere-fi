<?php

namespace Drupal\tre_cludo_search\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a 'CludoSearchBlock' to enable search in page header.
 */
#[Block(
  id: "cludo_search_block",
  admin_label: new TranslatableMarkup("Cludo search block"),
)]

class CludoSearchBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#theme' => 'cludo_search',
    ];
  }
}