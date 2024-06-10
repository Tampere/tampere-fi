<?php

namespace Drupal\tre_display_external_events\Plugin\ExternalDataSource;

use Drupal\tre_display_external_events\Config;
use Geniem\LinkedEvents\LinkedEventsClient;

/**
 * Plugin class for fetching audience data from Pirkanmaa Events API.
 *
 * @ExternalDataSource(
 *  id = "pirkanmaa_external_data_source_audience",
 *  name = @Translation("Pirkanmaa external data source audience"),
 *  description = @Translation("Audiences from Pirkanmaa external data source"),
 * )
 */
class PirkanmaaExternalDataSourceAudience extends PirkanmaaExternalDataSourcePluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPluginId() {
    return 'pirkanmaa_external_data_source_audience';
  }

  /**
   * Fetch audiences from the api.
   *
   * Provide the response in a structure that contrib module
   * External Data Source expects them.
   *
   * @return array
   *   Array of associative arrays
   */
  public function getResponse() {
    $base_url = $this->settings::get(Config::EVENTS_API_BASE_URL_SETTINGS_KEY, Config::EVENTS_API_BASE_URL);
    $client = new LinkedEventsClient($base_url);
    try {
      $items = $client->get_all('keyword');
    }
    catch (\Exception $e) {
      $this->messenger()->addMessage($e->getMessage(), "error");
      $this->messenger()->addMessage($this->t('Using base url: :url', [":url" => $base_url]), 'error');
      $items = [];
    }
    $data = $this->extractAudiences($items);

    return $this->formatResponse($data);
  }

  /**
   * Restructure results so that they can be presented through Drupal form api.
   *
   * @param array $data
   *   Formatting data retrieved from ws to match [{"value":"","label":""},
   *   {"value":"", "label":""}].
   *
   * @return array
   *   retrieved values and labels.
   */
  public function formatResponse(array $data): array {
    $choices = [];
    foreach ($data as $entry) {
      $choices[] = [
        'value' => (string) $entry[1],
        'label' => $entry[0],
      ];
    }
    return $choices;
  }

  /**
   * Extract audiences from the api query results.
   *
   * @param array $items
   *   Objects obtained from external system.
   *
   * @return array[]
   *   with audience name and id for each audience.
   */
  private function extractAudiences(array $items): array {
    $audiences = [];
    foreach ($items as $item) {
      $arr = explode(":", $item->id);
      if ($arr[1] == "audience") {
        $audiences[] = [$item->name->fi, $item->id];
      }

    }
    return $audiences;
  }

}
