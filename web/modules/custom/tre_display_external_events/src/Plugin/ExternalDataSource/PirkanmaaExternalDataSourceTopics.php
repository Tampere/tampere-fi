<?php

namespace Drupal\tre_display_external_events\Plugin\ExternalDataSource;

use Drupal\tre_display_external_events\Config;

/**
 * Class for getting topics / subcategories.
 *
 * @ExternalDataSource(
 *  id = "pirkanmaa_external_data_source_topics",
 *  name = @Translation("Topics from Pirkanmaa external data source"),
 *  description = @Translation("Topics from Pirkanmaa external data source"),
 * )
 */
class PirkanmaaExternalDataSourceTopics extends PirkanmaaExternalDataSourcePluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPluginId() {
    return 'pirkanmaa_external_data_source_topics';
  }

  /**
   * Fetch subcategories/topics from an external system.
   *
   * @return array
   *   retrieved values and labels.
   */
  public function getResponse() {
    $base_url = $this->settings::get(Config::EVENTS_API_BASE_URL_SETTINGS_KEY, Config::EVENTS_API_BASE_URL);
    try {
      $subcategories = $this->getKeywords($base_url);
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
   *
   * @return array
   *   Options to be rendered.
   */
  private function getKeywords(string $base_url): array {

    $client = $this->httpClientFactory->fromOptions([
      'base_uri' => $base_url,
      'timeout'  => 5.0,
    ]);

    // Use "include" query parameter to get detailed data related to keyword
    // sets.
    $params = [
      "query" => [
        "data_source" => "system",
        "include" => "keywords",
      ],
    ];
    $response = $client->request('GET', 'keyword_set', $params);

    $result = json_decode($response->getBody(), TRUE);
    if (!$result) {
      throw new \Exception("tre_display_external_events: Unable to get any results");
    }
    $items = $result["data"];
    $choices = [];
    foreach ($items as $result) {
      if ($result['usage'] == "keyword") {
        // The result item contains subcategories that need to be collected.
        foreach ($result['keywords'] as $keyw) {
          $choices[] = ["value" => $keyw["id"], "label" => $keyw["name"]["fi"]];
        }
      }
    }
    return $choices;
  }

}
