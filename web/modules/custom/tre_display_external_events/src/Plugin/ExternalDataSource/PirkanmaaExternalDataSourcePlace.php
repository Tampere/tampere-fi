<?php

namespace Drupal\tre_display_external_events\Plugin\ExternalDataSource;

use Drupal\tre_display_external_events\Config;
use Geniem\LinkedEvents\LinkedEventsClient;

/**
 * Plugin class for fetching place data from Pirkanmaa Events API.
 *
 * @ExternalDataSource(
 *  id = "pirkanmaa_external_data_source_place",
 *  name = @Translation("Pirkanmaa external data source place"),
 *  description = @Translation("Places from Pirkanmaa external data source"),
 * )
 */
class PirkanmaaExternalDataSourcePlace extends PirkanmaaExternalDataSourcePluginBase {

  const ID_BLOCK_LIST = [
    'Akaa' => 'system:1',
    'Orivesi' => 'system:10',
    'Parkano' => 'system:11',
    'Pirkkala' => 'system:12',
    'Punkalaidun' => 'system:13',
    'Pälkäne' => 'system:14',
    'Ruovesi' => 'system:15',
    'Sastamala' => 'system:16',
    'Urjala' => 'system:18',
    'Valkeakoski' => 'system:19',
    'Hämeenkyrö' => 'system:2',
    'Vesilahti' => 'system:20',
    'Virrat' => 'system:21',
    'Ylöjärvi' => 'system:22',
    'Ikaalinen' => 'system:3',
    'Juupajoki' => 'system:4',
    'Kangasala' => 'system:5',
    'Kihniö' => 'system:6',
    'Lempäälä' => 'system:7',
    'Mänttä-Vilppula' => 'system:8',
    'Nokia' => 'system:9',
    'Löytötex' => 'system:VFG8t3qrkhXWrDfLezZWzprtwgBXsoL1USftBLgfpXV',
  ];

  /**
   * {@inheritdoc}
   */
  public function getPluginId() {
    return 'pirkanmaa_external_data_source_place';
  }

  /**
   * Fetch places from the api.
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
    $query_params = [
      'is_address' => 'false',
      'no_parent' => 'false',
      'page_size' => 100,
      'sort' => 'name',
    ];
    try {
      $items = $client->get_all('place', $query_params);
    }
    catch (\Exception $e) {
      $this->messenger()->addMessage($e->getMessage(), "error");
      $this->messenger()->addMessage($this->t('Using base url: :url', [":url" => $base_url]), 'error');
      $items = [];
    }

    return $this->formatResponse($items);
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
      if (!in_array($entry->id, self::ID_BLOCK_LIST, TRUE)) {
        $choices[] = [
          'value' => (string) $entry->id,
          // @todo The language remains an open question as most entries only
          // have the 'fi' language available.
          'label' => $entry->name->fi,
        ];
      }
    }
    return $choices;
  }

}
