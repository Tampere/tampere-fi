<?php

namespace Drupal\tre_preprocess\Traits;

/**
 * Trait for including pager's last page key in template variables.
 */
trait LastPageKeyForPagerTrait {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    if (isset($variables['items']['last']['href'])) {
      // The 'pager' element in $variables contains metadata about the pager we
      // need to load the actual Pager object.
      if ($this->pagerManager->getPager($variables['pager']['#element'])) {
        $pager = $this->pagerManager->getPager($variables['pager']['#element']);
      }
      // Returns null when user is on last page.
      if (!empty($pager)) {
        $last_page_key = $pager->getTotalPages();
        $variables['last_page_key'] = $last_page_key;
      }
    }

    return $variables;
  }

}
