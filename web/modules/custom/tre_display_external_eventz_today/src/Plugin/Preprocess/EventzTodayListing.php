<?php

namespace Drupal\tre_display_external_eventz_today\Plugin\Preprocess;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Site\Settings;
use Drupal\tre_preprocess\TrePreProcessPluginBase;
use Drupal\tre_display_external_eventz_today\Config;
use Drupal\tre_display_external_eventz_today\Plugin\EventzClientCustomized;

/**
 * List of attachments paragraph preprocessing.
 *
 * @Preprocess(
 *   id = "tre_display_external_eventz_today.preprocess.paragraph__eventz_today_listing",
 *   hook = "paragraph__eventz_today_listing"
 * )
 */
class EventzTodayListing extends TrePreProcessPluginBase {

  const DEFAULT_SORT_ORDER = 'default';

  /**
   * The default areas for listings that have no more specific place listed.
   *
   * List of areas that are related to the area/location 'Tampere':
   *
   * @var array
   */
  const DEFAULT_AREAS_FILTER_LIST = Config::TAMPERE_RELATED_AREAS_IDS;

  const EVENT_LIST_CACHE_TAG = 'tre_display_external_eventz_today:list';

  const EVENT_DEFAULT_IMAGE = "/themes/custom/tampere/images/placeholder-green-waves.svg";

  const SUMMARY_LENGTH = 200;

  const TYPES_EN_TO_FI_LOOKUP = [
    "virtual" => "virtuaali",
    "free" => "maksuton",
    "accessible" => "esteetön",
  ];

  /**
   * Eventz client service object.
   *
   * @var \Drupal\tre_display_external_eventz_today\Plugin\EventzClientCustomized
   */
  protected $eventzClient;

  /**
   * Desired language.
   *
   * @var string
   */
  protected $desiredLanguage;

  /**
   * {@inheritdoc}
   *
   * @see tre_display_external_eventz_today_cron()
   */
  public function preprocess(array $variables): array {
    $paragraph = $variables['paragraph'];
    /** @var \Drupal\paragraphs\ParagraphInterface $translated_paragraph */
    $translated_paragraph = $this->entityRepository->getTranslationFromContext($paragraph);
    $max_num_events = intval($this->helperFunctions->getFieldValueString($translated_paragraph, 'field_eventz_display_amount'));
    $current_date = new DrupalDateTime('now', 'Europe/Helsinki');

    // Artificially add a cache tag in the listing to make it time-sensitive.
    // The time-sensitivity is taken care of by automatically, via cron,
    // invalidating the cache by this tag every 30 minutes.
    $variables['#cache']['tags'][] = self::EVENT_LIST_CACHE_TAG;

    // The validator function is defined as a closure to be able to use it as a
    // callback for array_filter while simultaneously using the $current_date
    // variable in it.
    $event_start_time_validator = function ($item) use ($current_date) {
      if (!array_key_exists("dates", $item["event"]) || empty($item["event"]["dates"])) {
        $event_date = $item["event"];
      }
      else {
        $event_date = $item["event"]["dates"][0];
      }

      // If the event has no end date set, no need to filter it out.
      if (empty($event_date["end"])) {
        return TRUE;
      }

      $end_date = new DrupalDateTime($event_date["end"]);
      $event_end_timestamp = $end_date->getTimestamp();
      $current_timestamp = $current_date->getTimestamp();
      return $event_end_timestamp > $current_timestamp;
    };

    $base_url = Settings::get(Config::EVENTZ_TODAY_API_KEY_SETTINGS_URL);
    $api_key = Settings::get(Config::EVENTZ_TODAY_API_KEY_SETTINGS_KEY);
    $this->eventzClient = new EventzClientCustomized($base_url, $api_key);

    $ext_organizer = $translated_paragraph->get('field_eventz_external_organizers')->getValue();
    $organizer_list = $this->helperFunctions->getListFieldValues($ext_organizer);

    $ext_location = $translated_paragraph->get('field_eventz_external_places')->getValue();
    $location_list = $this->helperFunctions->getListFieldValues($ext_location);

    $ext_audience = $translated_paragraph->get('field_eventz_external_audience')->getValue();
    $audience_list = $this->helperFunctions->getListFieldValues($ext_audience);

    $ext_category = $translated_paragraph->get('field_eventz_external_category')->getValue();
    $category_list = $this->helperFunctions->getListFieldValues($ext_category);

    $excl_category = $translated_paragraph->get('field_eventz_excl_category')->getValue();
    $excl_category_list = $this->helperFunctions->getListFieldValues($excl_category);

    $ext_organization_class = $translated_paragraph->get('field_eventz_external_org_class')->getValue();
    $org_class_list = $this->helperFunctions->getListFieldValues($ext_organization_class);

    $excl_organization_class = $translated_paragraph->get('field_eventz_excl_org_class')->getValue();
    $excl_class_list = $this->helperFunctions->getListFieldValues($excl_organization_class);

    $ext_areas = $translated_paragraph->get('field_eventz_external_areas')->getValue();
    $area_list = $this->helperFunctions->getListFieldValues($ext_areas);

    $event_types = $translated_paragraph->get('field_eventz_event_types')->getValue();
    $type_list = $this->helperFunctions->getListFieldValues($event_types);

    $events_to_exclude = $translated_paragraph->get('field_eventz_excluded_events')->getValue();
    $events_to_exclude_list = $this->helperFunctions->getListFieldValues($events_to_exclude);

    $featured_start = new DrupalDateTime($translated_paragraph->get('field_eventz_featured_start')->getString(), 'UTC');
    $featured_end = new DrupalDateTime($translated_paragraph->get('field_eventz_featured_end')->getString(), 'UTC');

    $this->desiredLanguage = $translated_paragraph->get('langcode')->getString();

    if ($this->desiredLanguage == 'fi') {
      $type_list = array_map(function ($item) {
        return self::TYPES_EN_TO_FI_LOOKUP[$item];
      }, $type_list);
    }

    $variables['featured_liftup_exists'] = !$translated_paragraph->get('field_eventz_featured_event_id')->isEmpty();
    $desired_featured_event = NULL;
    if ($variables['featured_liftup_exists']) {
      try {
        $desired_featured_event = $this->eventzClient->get_item($translated_paragraph->get('field_eventz_featured_event_id')->getString(), $this->desiredLanguage);
        $variables['featured_liftup_exists'] = !empty($desired_featured_event);
        $desired_featured_event = json_decode(json_encode($desired_featured_event), TRUE);
      }
      catch (\Exception $e) {
        $variables['featured_liftup_exists'] = FALSE;
      }
    }

    // Prepare to remove the featured event from the list of events.
    if ($variables['featured_liftup_exists']) {
      $page_size = $max_num_events + 1;
    }
    else {
      $page_size = $max_num_events;
    }

    // If there is at least one exclusion filtering selected, we need
    // to fetch enough, e.g. 5x, events, so that after excluding undesired
    // ones, there is still enough events for listing.
    if (!empty($excl_category_list) || !empty($excl_class_list)) {
      $page_size *= 5;
    }

    $excl_topics_list = array_merge($excl_category_list, $excl_class_list);

    // In case there were events to exclude listed, increase the page size by
    // the amount of exclusions to be able to fill the list.
    $page_size += count($events_to_exclude_list);

    // Since we are planning to eventually drop events from the response based
    // on whether the end date has passed, we need to adjust the page size. The
    // agreed-upon amount of additional events to request is 6.
    $page_size += 6;

    $audiences = implode(",", $audience_list);
    $categories = implode(",", array_merge($category_list, $org_class_list));
    $host_ids = implode(",", array_merge($organizer_list, $location_list));
    $areas = implode(",", $area_list);

    // If the paragraph lists no locations, no organization class and
    // no area selected, limit the areas to the default list.
    if (empty($location_list)  && empty($org_class_list) && empty($area_list)) {
      $areas = implode(",", self::DEFAULT_AREAS_FILTER_LIST);
    }

    $api_parameters = [
      "targets" => $audiences,
      "category_id" => $categories,
      "size" => $page_size,
      "host_ids" => $host_ids,
      "areas" => $areas,
    ];

    $api_parameters += $this->getTimeFilters($translated_paragraph->get('field_eventz_event_start'), $translated_paragraph->get('field_eventz_event_end'), $current_date);
    $api_parameters += $this->getSort($translated_paragraph->get('field_eventz_events_sort'));

    $variables['featured_liftup_exists'] = FALSE;

    if (isset($desired_featured_event["_id"]) &&
        $desired_featured_event["language"] == $this->desiredLanguage &&
        !$this->isEventExpired($desired_featured_event, $current_date) &&
        $featured_start->getTimestamp() <= $current_date->getTimestamp() &&
        $featured_end->getTimestamp() >= $current_date->getTimestamp()
      ) {
      $events_to_exclude_list[] = $desired_featured_event["_id"];
      $variables['featured_liftup'] = $this->makeFeaturedLiftupFromEvent($desired_featured_event);
      $variables['featured_liftup_exists'] = TRUE;
    }

    $previous_total_events = 0;
    $results = [];

    // Continue fetching more events until the total number of
    // events left after the exclusion is enough.
    while (count($results) < $max_num_events) {
      $all_results = $this->fetchEvents($api_parameters);

      // If the total number of events from API equals the previous
      // number, it means there is no more events available and an early
      // exit is needed.
      if (count($all_results) == $previous_total_events) {
        break;
      }

      $previous_total_events = count($all_results);

      $results = $this->excludeEventsByCategory($all_results, $excl_topics_list);

      $results = $this->includeEventsByTypes($results, $type_list);

      // Filter out events which are in the exclusion list.
      $results = array_filter($results, function ($item) use ($events_to_exclude_list) {
        return !in_array($item["_id"], $events_to_exclude_list, TRUE);
      });

      // Filter out events which have an end date that has already passed.
      $results = array_filter($results, $event_start_time_validator);

      // Fetch more events. e.g. 2x, in the next iteration.
      $api_parameters["size"] *= 2;

    }

    // Respect the setting available on paragraph edit form.
    $at_most_max_num_event_results = array_slice($results, 0, $max_num_events);
    $variables["liftups"] = $this->makeLiftupsFromEvents($at_most_max_num_event_results);

    $variables["topical_listing__heading"] = $translated_paragraph->get('field_title')->getString();

    $link_list__items = [];

    if ($this->desiredLanguage == 'en') {
      $url = 'https://tapahtumat.tampere.fi/en-FI';
    }
    else {
      $url = 'https://tapahtumat.tampere.fi';
    }

    $topical_listing_link = [
      'text' => 'tapahtumat.tampere.fi',
      'url' => $url,
      'icon' => 'external',
    ];
    array_push($link_list__items, $topical_listing_link);
    $variables['link_list__items'] = $link_list__items;

    return $variables;
  }

  /**
   * Deduce time filters for Eventz API query.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $start_date
   *   The field items for the start date field.
   * @param \Drupal\Core\Field\FieldItemListInterface $end_date
   *   The field items for the end date field.
   * @param \Drupal\Core\Datetime\DrupalDateTime $current_time
   *   The moment of time to use to denote 'current time'.
   *
   * @return array
   *   The appropriate time filters based on given values in start_date and
   *   end_date. Like documented on the content input side, the default is
   *   'the value for today' but if at least one of the date fields has a value,
   *   the value is transferred into the corresponding filter field.
   */
  private function getTimeFilters(FieldItemListInterface $start_date, FieldItemListInterface $end_date, DrupalDateTime $current_time) {
    $filters = [];
    if (($start_date->isEmpty() && $end_date->isEmpty()) || $start_date->getString() < $current_time) {
      $filters['start'] = $current_time->format(\DateTimeInterface::ATOM);
    }
    else {
      if (!$start_date->isEmpty()) {
        $filters['start'] = $start_date->getString();
      }
      if (!$end_date->isEmpty()) {
        $filters['end'] = $end_date->getString();
      }
    }

    return $filters;
  }

  /**
   * Checks if the event is ended based on the general end time.
   *
   * @param array $event
   *   Event object.
   * @param \Drupal\Core\Datetime\DrupalDateTime $current_date
   *   Current Date.
   *
   * @return bool
   *   Returns TRUE if the event is expired already.
   */
  private function isEventExpired(array $event, DrupalDateTime $current_date): bool {
    $end_date = new DrupalDateTime($event["event"]["end"]);
    $end_date_timestamp = $end_date->getTimestamp();
    $current_date_timestamp = $current_date->getTimestamp();

    return $end_date_timestamp < $current_date_timestamp;
  }

  /**
   * Deduces the sort order parameter to send to the API.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $sort_order_field_items
   *   The field items for the sort order. Should contain exactly one value.
   *
   * @return array
   *   The sort parameter, if set to other than the default value. For the
   *   default sort to happen, the API expects that no sort parameter is used
   *   so on default an empty array is returned.
   */
  private function getSort(FieldItemListInterface $sort_order_field_items): array {
    $sort_param = [];
    if (!$sort_order_field_items->isEmpty() &&
        $sort_order_field_items->getString() !== self::DEFAULT_SORT_ORDER
       ) {
      $sort_param = [
        'sort' => $sort_order_field_items->getString(),
      ];
    }
    return $sort_param;
  }

  /**
   * Helper to get a formatted time string for an event.
   *
   * If the event spans multiple days, the time string will be shown as a date
   * interval. Otherwise the starting time will be shown as date + time.
   *
   * @param array $item
   *   The event object from Eventz Today API.
   *
   * @return string
   *   The formatted string for the time of the event.
   */
  private function getEventTimeString(array $item): string {

    if (!array_key_exists("dates", $item["event"]) || empty($item["event"]["dates"])) {
      $event_date = $item["event"];
    }
    else {
      $event_date = $item["event"]["dates"][0];
    }

    $start_timestamp_from_start_date = (new DrupalDateTime((new DrupalDateTime($event_date["start"]))->format('Y-m-d', ['timezone' => 'Europe/Helsinki'])))->getTimestamp();
    $end_timestamp_from_end_date = (new DrupalDateTime((new DrupalDateTime($event_date["end"]))->format('Y-m-d', ['timezone' => 'Europe/Helsinki'])))->getTimestamp();

    if ($end_timestamp_from_end_date > $start_timestamp_from_start_date) {
      $start_date_formatted = (new DrupalDateTime($event_date["start"]))->format('j.n.Y', ['timezone' => 'Europe/Helsinki']);
      $end_date_formatted = (new DrupalDateTime($event_date["end"]))->format('j.n.Y', ['timezone' => 'Europe/Helsinki']);
      $event_time = sprintf('%s–%s', $start_date_formatted, $end_date_formatted);
    }
    else {
      $event_time = empty($event_date["start"]) ? '' : (new DrupalDateTime($event_date["start"]))->format('j.n.Y H.i', ['timezone' => 'Europe/Helsinki']);
    }
    return $event_time;
  }

  /**
   * Fetches events based on parameters from Event APIs.
   *
   * @param array $api_parameters
   *   List of parameters needed for fetching.
   *
   * @return array
   *   List of events.
   */
  private function fetchEvents(array $api_parameters): array {
    $api_parameters = array_filter($api_parameters);
    try {
      // API for getting events based on organizer/host ids is different
      // from the API for getting events based on other filters.
      if (!array_key_exists("host_ids", $api_parameters)) {
        $results = $this->eventzClient->search_events($api_parameters, $this->desiredLanguage);
      }
      else {
        $results = $this->eventzClient->search_events_by_host($api_parameters, $this->desiredLanguage);
      }
      return json_decode(json_encode($results), TRUE);
    }
    catch (\Exception $e) {
      $this->messenger()->addMessage($e->getMessage(), "error");
      $this->messenger()->addMessage($this->t('Fetching events failed.'), 'error');
      return [];
    }
  }

  /**
   * Returns events that don't belong to the list of categories.
   *
   * @param array $events
   *   List of events.
   * @param array $excl_categories
   *   List of excluded categories.
   *
   * @return array
   *   Final list of events after the exclusion.
   */
  private function excludeEventsByCategory(array $events, array $excl_categories): array {
    if (empty($excl_categories)) {
      return $events;
    }

    $topics_name_id_pairs = $this->eventzClient->getAllCategoriesByNameIdPair($this->desiredLanguage);

    return array_filter($events, function ($item) use ($topics_name_id_pairs, $excl_categories) {
      foreach ($item["topics"] as $topic) {
        // If the event includes at least one of
        // the excluded topics, it must be removed.
        if (array_key_exists($topic, $topics_name_id_pairs) &&
          in_array($topics_name_id_pairs[$topic], $excl_categories)) {
          return FALSE;
        }
      }
      return TRUE;
    });

  }

  /**
   * Returns events that belong to the list of types.
   *
   * @param array $events
   *   List of events.
   * @param array $type_list
   *   List of included types.
   *
   * @return array
   *   Final list of events after the exclusion.
   */
  private function includeEventsByTypes(array $events, array $type_list): array {
    if (empty($type_list)) {
      return $events;
    }

    return array_filter($events, function ($item) use ($type_list) {
      foreach ($item["tags"] as $tag) {
        // If the event includes at least one of
        // the included types, it must be kept.
        if (in_array(mb_strtolower($tag), $type_list)) {
          return TRUE;
        }
      }
      return FALSE;
    });

  }

  /**
   * Makes a featured liftup object from an event.
   *
   * @param array $event
   *   Event object.
   *
   * @return array
   *   Liftup object with fields needed for rendering the featured liftup.
   */
  private function makeFeaturedLiftupFromEvent(array $event): array {
    // Handle image logic: use image style render array for external
    // images, but in case there is no image in the API response, use the
    // default SVG image.
    $featured_image_src = empty($event["images"]["imageDesktop"]["url"]) ? '' : $event["images"]["imageDesktop"]["url"];
    $featured_default_image_src = empty($featured_image_src) ? self::EVENT_DEFAULT_IMAGE : '';
    $featured_image = [];
    if (!empty($featured_image_src)) {
      $featured_image = [
        '#theme' => 'imagecache_external_responsive',
        '#uri' => $featured_image_src,
        '#responsive_image_style_id' => 'large_listing_liftup_responsive_image_style',
      ];
    }

    // If the summary is longer than the threshold,
    // extract and use the full sentences shorter than the threshold.
    $summary = $event["description"];
    if (mb_strlen($summary) > self::SUMMARY_LENGTH) {
      $summary = $this->extractFullSentences(mb_substr($summary, 0, self::SUMMARY_LENGTH));
    }

    // Filter out empty elements from the variables going to the template.
    return array_filter([
      'image' => $featured_image,
      'image_src' => $featured_default_image_src,
      'date' => $this->getEventTimeString($event),
      'location' => $event["locations"][0]["address"] ?? '',
      'name' => $event["name"],
      'summary' => $summary ?? '',
      'url' => $event["url"],
    ]);
  }

  /**
   * Makes a list of liftup objects from events.
   *
   * @param array $events
   *   List of event objects.
   *
   * @return array
   *   List of liftup objects with fields needed for rendering the cards.
   */
  private function makeLiftupsFromEvents(array $events): array {
    $results = [];
    foreach ($events as $item) {
      // Handle image logic: use image style render array for external images,
      // but in case there is no image in the API response, use the default
      // SVG image.
      $image_src = empty($item["images"]["imageDesktop"]) ? '' : $item["images"]["imageDesktop"]["url"];
      $default_image_src = empty($image_src) ? self::EVENT_DEFAULT_IMAGE : '';
      $image = [];
      if (!empty($image_src)) {
        $image = [
          '#theme' => 'imagecache_external_responsive',
          '#uri' => $image_src,
          '#responsive_image_style_id' => 'listing_liftup_responsive_image_style',
        ];
      }

      $location = '';
      if (!empty($item["locations"])) {
        $location = $item["locations"][0]["address"];
      }

      $info_url = $item["url"];
      $event_time = $this->getEventTimeString($item);

      // Filter out empty elements from the variables going to the template.
      $results[] = array_filter([
        "card__display_as_list_item" => TRUE,
        "card__heading" => $item["name"],
        "card__date" => $event_time,
        "card__tag" => $location,
        "card__image__src" => $default_image_src,
        "card__image" => $image,
        "card__link__url" => $info_url,
        "card__icon__name" => 'external',
      ]);
    }
    return $results;
  }

  /**
   * Extracts the full sentences from the input text.
   *
   * @param string $text
   *   Input text.
   *
   * @return string
   *   Final text containing the full sentences.
   */
  private function extractFullSentences(string $text): string {
    $pattern = "/[.!?]+/";
    $result = preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE);
    if ($result > 0) {
      $last_match = end($matches[0]);
      $last_match_position = $last_match[1];
      return substr($text, 0, $last_match_position + 1);
    }
    return '';
  }

}
