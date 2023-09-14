<?php

namespace Drupal\tre_display_external_eventz_today\Plugin\ExternalDataSource;

use Drupal\tre_display_external_eventz_today\Config;
use Drupal\tre_display_external_eventz_today\Plugin\EventzClientCustomized;

/**
 * Class for getting topics / subcategories from Eventz.Today Events API.
 *
 * @ExternalDataSource(
 *  id = "eventz_today_external_data_source_topics",
 *  name = @Translation("Topics from Eventz.Today external data source"),
 *  description = @Translation("Topics from Eventz.Today external data source"),
 * )
 */
class EventzTodayExternalDataSourceTopics extends EventzTodayExternalDataSourcePluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPluginId() {
    return 'eventz_today_external_data_source_topics';
  }

  /**
   * Fetch subcategories/topics from the api.
   *
   * @return array
   *   retrieved values and labels.
   */
  public function getResponse() {
    $base_url = $this->settings::get(Config::EVENTZ_TODAY_API_KEY_SETTINGS_URL);
    $api_key = $this->settings::get(Config::EVENTZ_TODAY_API_KEY_SETTINGS_KEY);
    try {
      $subcategories = $this->getKeywords($base_url, $api_key);
    }
    catch (\Exception $e) {
      $this->messenger()->addMessage($e->getMessage(), "error");
      $this->messenger()->addMessage($this->t('Using base url: :url', [":url" => $base_url]), 'error');
      $subcategories = [];
    }
    return $subcategories;
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

    $categories = $client->getAllCategories();

    $choices = [];
    foreach ($categories as $result) {
      if ($result["type"] == "child") {
        $choices[] = ["value" => $result["_id"], "label" => $result["name"]];
      }
    }
    return $choices;
  }

}
