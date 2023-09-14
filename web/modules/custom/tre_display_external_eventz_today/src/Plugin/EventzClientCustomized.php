<?php

namespace Drupal\tre_display_external_eventz_today\Plugin;

use Geniem\Eventz\EventzClient;

/**
 * Class for customizing the eventz client class.
 */
class EventzClientCustomized extends EventzClient {

  /**
   * The collator in Fi definition.
   *
   * @var object
   */
  protected $collatorFi;

  /**
   * Constructor.
   */
  final public function __construct(...$args) {
    $this->collatorFi = new \Collator('fi_FI');
    parent::__construct(...$args);
  }

  /**
   * Return all hosts from Eventz in a proper format.
   *
   * @return array
   *   List of all hosts.
   */
  public function getAllHosts() {
    $result = json_decode(json_encode($this->search_organizers()), TRUE);

    usort($result, function ($item1, $item2) {
      return $this->collatorFi->compare($item1['name'], $item2['name']);
    });
    return $result;
  }

  /**
   * Return all areas from Eventz in a proper format.
   *
   * @return array
   *   List of all areas.
   */
  public function getAllAreas() {
    return json_decode(json_encode($this->get_areas()), TRUE);
  }

  /**
   * Return all target groups from Eventz in a proper format.
   *
   * @return array
   *   List of all target groups.
   */
  public function getAllTargets() {
    return json_decode(json_encode($this->get_targets()), TRUE);
  }

  /**
   * Return all tags from Eventz in a proper format.
   *
   * @return array
   *   List of all tags.
   */
  public function getTags() {
    return json_decode(json_encode($this->get_tags()), TRUE);
  }

  /**
   * Return all Tampere places from Eventz in a proper format.
   *
   * @return array
   *   List of all places.
   */
  public function getAllPlaces() {
    $result = $this->getTags();
    $tampere_places = $result["tampere-fi-places"];

    usort($tampere_places, function ($item1, $item2) {
      return $this->collatorFi->compare($item1['name'], $item2['name']);
    });
    return $tampere_places;
  }

  /**
   * Return all organizers from Eventz in a proper format.
   *
   * @return array
   *   List of all organizers.
   */
  public function getAllOrganizers() {
    $all_organizers = $this->getAllHosts();
    $tampere_places = $this->getAllPlaces();

    $places_ids = [];
    foreach ($tampere_places as $place) {
      $places_ids[] = $place["_id"];
    }

    array_filter($all_organizers, function ($host) use ($places_ids) {
      return !in_array($host["_id"], $places_ids);
    });

    usort($all_organizers, function ($item1, $item2) {
      return $this->collatorFi->compare($item1['name'], $item2['name']);
    });

    return $all_organizers;
  }

  /**
   * Return IDs of all organizers from Eventz in a proper format.
   *
   * @return array
   *   List of all organizer IDs.
   */
  public function getAllOrganizersId() {
    $all_organizers = $this->getAllOrganizers();
    $all_ids = [];
    foreach ($all_organizers as $organizer) {
      $all_ids[] = $organizer["_id"];
    }

    return $all_ids;
  }

  /**
   * Return all categories from Eventz in a proper format.
   *
   * @param string $lang
   *   Category's language.
   *
   * @return array
   *   List of all categories.
   */
  public function getAllCategories($lang = 'fi') {
    $results = $this->get_categories($lang);
    $results = json_decode(json_encode($results), TRUE);

    usort($results, function ($item1, $item2) {
      return $this->collatorFi->compare($item1['name'], $item2['name']);
    });

    return $results;
  }

  /**
   * Return all categories in pairs of Name and ID from Eventz.
   *
   * @param string $lang
   *   Category's language.
   *
   * @return array
   *   List of all categories.
   */
  public function getAllCategoriesByNameIdPair($lang = 'fi') {
    $results = $this->getAllCategories($lang);
    $final_result = [];
    foreach ($results as $result) {
      $final_result[$result["name"]] = $result["_id"];
    }
    return $final_result;
  }

}
