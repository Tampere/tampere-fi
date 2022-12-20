<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\group\Entity\GroupInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;

/**
 * Search form block preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.form__views__search",
 *   hook = "form__views__search"
 * )
 */
class FormViewsSearch extends TrePreProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $current_language_id = $this->languageManager->getCurrentLanguage()->getId();

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

    if ($current_language_id == 'fi') {
      $variables['attributes']['action'] = '/search/' . $group_id;
    }
    else {
      $variables['attributes']['action'] = '/' . $current_language_id . '/search/' . $group_id;
    }

    return $variables;
  }

}
