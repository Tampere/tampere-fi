<?php

namespace Drupal\tre_display_external_eventz_today\Plugin\ExternalDataSource;

use Drupal\tre_display_external_eventz_today\Config;
use Geniem\Eventz\EventzClient;

/**
 * Class for getting places data from Eventz.Today Events API.
 *
 * @ExternalDataSource(
 *  id = "eventz_today_external_data_source_places",
 *  name = @Translation("Places from Eventz.Today external data source"),
 *  description = @Translation("Places from Eventz.Today external data source"),
 * )
 */
class EventzTodayExternalDataSourcePlaces extends EventzTodayExternalDataSourcePluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPluginId() {
    return 'eventz_today_external_data_source_places';
  }

  /**
   * Fetch places data from the api.
   *
   * @return array
   *   retrieved values and labels.
   */
  public function getResponse() {
    $base_url = $this->settings::get(Config::EVENTZ_TODAY_API_KEY_SETTINGS_URL);
    $api_key = $this->settings::get(Config::EVENTZ_TODAY_API_KEY_SETTINGS_KEY);
    try {
      $places = $this->getKeywords($base_url, $api_key);
    }
    catch (\Exception $e) {
      $this->messenger()->addMessage($e->getMessage(), "error");
      $this->messenger()->addMessage($this->t('Using base url: :url', [":url" => $base_url]), 'error');
      $places = [];
    }
    return $places;
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
    $client = new EventzClient($base_url, $api_key);

    $all_tags = $client->get_tags();
    $result = json_decode(json_encode($all_tags), TRUE);
    $tampere_places = $result["tampere-fi-places"];

    $choices = [];
    foreach ($tampere_places as $tampere_place) {
      $choices[] = [
        "value" => $tampere_place["_id"],
        "label" => $tampere_place["name"],
      ];
    }
    return $choices;
  }

}
