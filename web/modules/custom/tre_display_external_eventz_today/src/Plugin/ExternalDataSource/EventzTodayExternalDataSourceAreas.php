<?php

namespace Drupal\tre_display_external_eventz_today\Plugin\ExternalDataSource;

use Drupal\tre_display_external_eventz_today\Config;
use Drupal\tre_display_external_eventz_today\Plugin\EventzClientCustomized;

/**
 * Class for getting areas from Eventz.Today Events API.
 *
 * @ExternalDataSource(
 *  id = "eventz_today_external_data_source_areas",
 *  name = @Translation("Areas from Eventz.Today external data source"),
 *  description = @Translation("Areas from Eventz.Today external data source"),
 * )
 */
class EventzTodayExternalDataSourceAreas extends EventzTodayExternalDataSourcePluginBase {

  /**
   * The custom areas for listing.
   *
   * List of areas that are related to the area/location 'Tampere':
   *
   * @var array
   */
  const CUSTOM_AREAS_FILTER_LIST = Config::TAMPERE_RELATED_AREAS_IDS;

  /**
   * {@inheritdoc}
   */
  public function getPluginId() {
    return 'eventz_today_external_data_source_areas';
  }

  /**
   * Fetch areas from the api.
   *
   * @return array
   *   retrieved values and labels.
   */
  public function getResponse() {
    $base_url = $this->settings::get(Config::EVENTZ_TODAY_API_KEY_SETTINGS_URL);
    $api_key = $this->settings::get(Config::EVENTZ_TODAY_API_KEY_SETTINGS_KEY);
    try {
      $areas = $this->getKeywords($base_url, $api_key);
    }
    catch (\Exception $e) {
      $this->messenger()->addMessage($e->getMessage(), "error");
      $this->messenger()->addMessage($this->t('Using base url: :url', [":url" => $base_url]), 'error');
      $areas = [];
    }
    return $areas;
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

    $areas = $client->getAllAreas();

    $choices = [];
    foreach ($areas as $result) {
      if (in_array($result["_id"], self::CUSTOM_AREAS_FILTER_LIST)) {
        $choices[] = ["value" => $result["_id"], "label" => $result["name"]];
      }
    }
    return $choices;
  }

}
