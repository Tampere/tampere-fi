<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\tre_preprocess\TrePreProcessPluginBase;

/**
 * Language switcher preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.links__language_block",
 *   hook = "links__language_block"
 * )
 */
class LanguageSwitcher extends TrePreProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $current_language = $this->languageManager->getCurrentLanguage();

    $languages = [
      'fi' => $this->t('Finnish', [], ['context' => 'Tampere.fi specific language name']),
      'en' => $this->t('English', [], ['context' => 'Tampere.fi specific language name']),
    ];

    $variables['current_language_code'] = $current_language->getId();
    $variables['current_language_name'] = $languages[$current_language->getId()]->render();

    $current_path = $this->currentPath->getPath();
    // Get group ID from current path for search view page.
    // Returns null otherwise.
    $group_id = $this->helperFunctions->getGroupIdFromSearchViewPath($current_path);

    $variables['use_minisite_language_switcher'] = $this->helperFunctions->isMinisite($group_id);

    return $variables;
  }

}
