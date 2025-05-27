<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\Core\Site\Settings;
use Drupal\tre_preprocess\TrePreProcessPluginBase;

/**
 * Cludo search preprocessing.
 *
 * @Preprocess(
 *   id = "tre_cludo_search.preprocess.page__cludo_search",
 *   hook = "cludo_search"
 * )
 */
class CludoSearch extends trePreProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $engine_id = !empty(Settings::get('cludo_engine_id_fi')) ? Settings::get('cludo_engine_id_fi') : '';
    $customer_id = !empty(Settings::get('cludo_customer_id')) ? Settings::get('cludo_customer_id') : '';
    $lang_code = \Drupal::languageManager()->getCurrentLanguage()->getId();

    // English search has its own engine id.
    if ($lang_code == 'en') {
      $engine_id = !empty(Settings::get('cludo_engine_id_en')) ? Settings::get('cludo_engine_id_en') : '';
    }

    $variables['#attached']['drupalSettings']['tre_cludo_search'] = [
      'customerId' => $customer_id,
      'engineId' => $engine_id,
      'currentLanguage' => $lang_code,
    ];

    $variables['cludo_customer_id'] = $customer_id;
    $variables['cludo_engine_id'] = $engine_id;

    return $variables;
  }

}
