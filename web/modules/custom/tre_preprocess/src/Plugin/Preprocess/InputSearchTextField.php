<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\group\Entity\GroupInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;

/**
 * Search form input textfield preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.input__textfield__views__search",
 *   hook = "input__textfield__views__search"
 * )
 */
class InputSearchTextField extends TrePreProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $current_path = $this->currentPath->getPath();
    // Get group_id from current path for search view page.
    // Returns null otherwise.
    $group_id = $this->helperFunctions->getGroupIdFromSearchViewPath($current_path);

    $is_mini_site = $this->helperFunctions->isMinisite($group_id);
    if ($is_mini_site && is_null($group_id)) {
      $current_group = $this->helperFunctions->getCurrentGroup();

      if (isset($current_group) && $current_group instanceof GroupInterface) {
        // Use current group id as contextual filter argument, if exists.
        $group_id = $current_group->id();
      }
    }

    // If no group_id set so far, set default as 'all'.
    $group_id = $group_id ?? 'all';

    // Add group id as an argument to search-api-autocomplete path.
    $autocomplete_path = $variables['attributes']['data-autocomplete-path'];
    $autocomplete_path .= "&arguments[]=$group_id";

    $variables['attributes']['data-autocomplete-path'] = $autocomplete_path;

    return $variables;
  }

}
