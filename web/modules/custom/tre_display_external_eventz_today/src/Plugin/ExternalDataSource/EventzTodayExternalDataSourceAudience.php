<?php

namespace Drupal\tre_display_external_eventz_today\Plugin\ExternalDataSource;

use Drupal\tre_display_external_eventz_today\Config;
use Drupal\tre_display_external_eventz_today\Plugin\EventzClientCustomized;

/**
 * Class for getting audience data from Eventz.Today Events API.
 *
 * @ExternalDataSource(
 *  id = "eventz_today_external_data_source_audience",
 *  name = @Translation("Audience from Eventz.Today external data source"),
 *  description = @Translation("Audience from Eventz.Today external data source"),
 * )
 */
class EventzTodayExternalDataSourceAudience extends EventzTodayExternalDataSourcePluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPluginId() {
    return 'eventz_today_external_data_source_audience';
  }

  /**
   * Fetch audience from the api.
   *
   * @return array
   *   retrieved values and labels.
   */
  public function getResponse() {
    $base_url = $this->settings::get(Config::EVENTZ_TODAY_API_KEY_SETTINGS_URL);
    $api_key = $this->settings::get(Config::EVENTZ_TODAY_API_KEY_SETTINGS_KEY);
    try {
      $items = $this->getKeywords($base_url, $api_key);
    }
    catch (\Exception $e) {
      $this->messenger()->addMessage($e->getMessage(), "error");
      $this->messenger()->addMessage($this->t('Using base url: :url', [":url" => $base_url]), 'error');
      $items = [];
    }
    return $items;
  }

  /**
   * Fetch keywords from an external system.
   *
   * @param string $base_url
   *   API base url.
   * @param string $api_key
   *   API key.
   *
   * @return array
   *   Options to be rendered.
   */
  private function getKeywords(string $base_url, string $api_key): array {
    $client = new EventzClientCustomized($base_url, $api_key);

    $results = $client->getAllTargets();

    $choices = [];
    foreach ($results as $result) {
      $choices[] = ["value" => $result["_id"], "label" => $result["name"]];
    }
    return $choices;
  }

}
