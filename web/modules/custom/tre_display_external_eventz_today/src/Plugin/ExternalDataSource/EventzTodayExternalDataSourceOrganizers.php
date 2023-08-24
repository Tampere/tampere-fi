<?php

namespace Drupal\tre_display_external_eventz_today\Plugin\ExternalDataSource;

use Drupal\tre_display_external_eventz_today\Config;
use Symfony\Component\HttpFoundation\Request;
use Geniem\Eventz\EventzClient;

/**
 * Class for getting organizers from Eventz.Today Events API.
 *
 * @ExternalDataSource(
 *  id = "eventz_today_external_data_source_organizers",
 *  name = @Translation("Organizers from Eventz.Today external data source"),
 *  description = @Translation("Organizers from Eventz.Today external data source"),
 * )
 */
class EventzTodayExternalDataSourceOrganizers extends EventzTodayExternalDataSourcePluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPluginId() {
    return 'eventz_today_external_data_source_organizers';
  }

  /**
   * {@inheritdoc}
   */
  public function setRequest(Request $request) {
    $this->request = $request;
  }

  /**
   * Fetch organizers from the api.
   *
   * @return array
   *   retrieved values and labels.
   */
  public function getResponse() {
    if (!is_null($this->request->get('q'))) {
      $this->q = $this->request->get('q');
    }

    $base_url = $this->settings::get(Config::EVENTZ_TODAY_API_KEY_SETTINGS_URL);
    $api_key = $this->settings::get(Config::EVENTZ_TODAY_API_KEY_SETTINGS_KEY);
    try {
      $organizers = $this->getKeywords($base_url, $api_key);
    }
    catch (\Exception $e) {
      $this->messenger()->addMessage($e->getMessage(), "error");
      $this->messenger()->addMessage($this->t('Using base url: :url', [":url" => $base_url]), 'error');
      $organizers = [];
    }

    if (!empty($this->q) && mb_strlen($this->q) > 1) {
      $organizers = array_values(array_filter($organizers, function ($organizer) {
        return str_contains(mb_strtolower($organizer["label"]), mb_strtolower($this->q));
      }));
    }

    return $organizers;
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

    $organizers = $client->search_organizers();

    $all_tags = $client->get_tags();
    $result = json_decode(json_encode($all_tags), TRUE);
    $tampere_places = $result["tampere-fi-places"];
    $places_ids = [];
    foreach ($tampere_places as $place) {
      $places_ids[] = $place["_id"];
    }

    $choices = [];
    foreach ($organizers as $result) {
      $result = json_decode(json_encode($result), TRUE);
      if (!in_array($result["_id"], $places_ids)) {
        $choices[] = ["value" => $result["_id"], "label" => $result["name"]];
      }
    }

    $collator = new \Collator('fi_FI');

    usort($choices, function ($item1, $item2) use ($collator) {
      return $collator->compare($item1['label'], $item2['label']);
    });

    return $choices;
  }

}
