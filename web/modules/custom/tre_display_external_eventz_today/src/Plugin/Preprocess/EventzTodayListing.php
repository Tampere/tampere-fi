<?php

namespace Drupal\tre_display_external_eventz_today\Plugin\Preprocess;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Site\Settings;
use Drupal\tre_preprocess\TrePreProcessPluginBase;
use Drupal\tre_display_external_eventz_today\Config;
use Geniem\Eventz\EventzClient;

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
      // If the event has no end date set, no need to filter it out.
      if (empty($item["event"]["end"])) {
        return TRUE;
      }

      $end_date = new DrupalDateTime($item["event"]["end"]);
      $event_end_timestamp = $end_date->getTimestamp();
      $current_timestamp = $current_date->getTimestamp();
      return $event_end_timestamp > $current_timestamp;
    };

    $ext_organizer = $translated_paragraph->get('field_eventz_external_organizers')->getValue();
    $organizer_list = $this->helperFunctions->getListFieldValues($ext_organizer);

    $ext_location = $translated_paragraph->get('field_eventz_external_places')->getValue();
    $location_list = $this->helperFunctions->getListFieldValues($ext_location);

    $ext_audience = $translated_paragraph->get('field_eventz_external_audience')->getValue();
    $audience_list = $this->helperFunctions->getListFieldValues($ext_audience);

    $ext_category = $translated_paragraph->get('field_eventz_external_category')->getValue();
    $category_list = $this->helperFunctions->getListFieldValues($ext_category);

    $ext_organization_class = $translated_paragraph->get('field_eventz_external_org_class')->getValue();
    $org_class_list = $this->helperFunctions->getListFieldValues($ext_organization_class);

    $ext_areas = $translated_paragraph->get('field_eventz_external_areas')->getValue();
    $area_list = $this->helperFunctions->getListFieldValues($ext_areas);

    $events_to_exclude = $translated_paragraph->get('field_eventz_excluded_events')->getValue();
    $events_to_exclude_list = $this->helperFunctions->getListFieldValues($events_to_exclude);

    $base_url = Settings::get(Config::EVENTZ_TODAY_API_KEY_SETTINGS_URL);
    $api_key = Settings::get(Config::EVENTZ_TODAY_API_KEY_SETTINGS_KEY);
    $eventz_client = new EventzClient($base_url, $api_key);

    $desired_language = $translated_paragraph->get('langcode')->getString();

    $variables['featured_liftup_exists'] = !$translated_paragraph->get('field_eventz_featured_event_id')->isEmpty();
    $desired_featured_event = NULL;
    if ($variables['featured_liftup_exists']) {
      try {
        $desired_featured_event = $eventz_client->get_item($translated_paragraph->get('field_eventz_featured_event_id')->getString(), $desired_language);
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
    $api_parameters = array_filter($api_parameters);

    try {
      // API for getting events based on organizer/host ids is different
      // from the API for getting events based on other filters.
      if (empty($host_ids)) {
        $results = $eventz_client->search_events($api_parameters, $desired_language);
      }
      else {
        $results = $eventz_client->search_events_by_host($api_parameters, $desired_language);
      }
      $results = json_decode(json_encode($results), TRUE);
    }
    catch (\Exception $e) {
      $this->messenger()->addMessage($e->getMessage(), "error");
      $this->messenger()->addMessage($this->t('Fetching events failed.'), 'error');
      $results = [];
    }
    $default_image = "/themes/custom/tampere/images/placeholder-green-waves.svg";

    if (isset($desired_featured_event["_id"])) {
      $desired_featured_event_list = [$desired_featured_event];
      // Check that the featured event has not ended yet. If it has, tell the
      // template not to display a featured event at all.
      $desired_featured_event_list = array_filter($desired_featured_event_list, $event_start_time_validator);

      if (!empty($desired_featured_event_list)) {
        $events_to_exclude_list[] = $desired_featured_event["_id"];

        // If the featured event has no name in the desired language, don't
        // render it.
        if ($desired_featured_event["language"] == $desired_language) {
          // Handle image logic: use image style render array for external
          // images, but in case there is no image in the API response, use the
          // default SVG image.
          $featured_image_src = empty($desired_featured_event["images"]["imageDesktop"]["url"]) ? '' : $desired_featured_event["images"]["imageDesktop"]["url"];
          $featured_default_image_src = empty($featured_image_src) ? $default_image : '';
          $featured_image = [];
          if (!empty($featured_image_src)) {
            $featured_image = [
              '#theme' => 'imagecache_external_responsive',
              '#uri' => $featured_image_src,
              '#responsive_image_style_id' => 'large_listing_liftup_responsive_image_style',
            ];
          }

          $summary = $desired_featured_event["description"];
          if (strlen($summary) > 100) {
            $summary = substr($summary, 0, 100) . '...';
          }
          // Filter out empty elements from the variables going to the template.
          $variables['featured_liftup'] = array_filter([
            'image' => $featured_image,
            'image_src' => $featured_default_image_src,
            'date' => $this->getEventTimeString($desired_featured_event),
            'location' => $desired_featured_event["locations"][0]["address"] ?? '',
            'name' => $desired_featured_event["name"],
            'summary' => $summary ?? '',
            'url' => $desired_featured_event["url"],
          ]);
        }
      }
      else {
        $variables['featured_liftup_exists'] = FALSE;
      }
    }

    // Filter out events which are in the exclusion list.
    $results = array_filter($results, function ($item) use ($events_to_exclude_list) {
      return !in_array($item["_id"], $events_to_exclude_list, TRUE);
    });
    // Filter out events which have an end date that has already passed.
    $results = array_filter($results, $event_start_time_validator);
    // Respect the setting available on paragraph edit form.
    $at_most_max_num_event_results = array_slice($results, 0, $max_num_events);
    $events = [];
    foreach ($at_most_max_num_event_results as $item) {
      // Handle image logic: use image style render array for external images,
      // but in case there is no image in the API response, use the default
      // SVG image.
      $image_src = empty($item["images"]["imageDesktop"]) ? '' : $item["images"]["imageDesktop"]["url"];
      $default_image_src = empty($image_src) ? $default_image : '';
      $image = [];
      if (!empty($image_src)) {
        $image = [
          '#theme' => 'imagecache_external_responsive',
          '#uri' => $image_src,
          '#responsive_image_style_id' => 'listing_liftup_responsive_image_style',
        ];
      }
      $location = $item["locations"][0]["address"];
      $info_url = $item["url"];
      $event_time = $this->getEventTimeString($item);

      // Filter out empty elements from the variables going to the template.
      $events[] = array_filter([
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
    $variables["liftups"] = $events;
    $variables["topical_listing__heading"] = $translated_paragraph->get('field_title')->getString();

    $link_list__items = [];

    if ($desired_language == 'en') {
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
   * Deduce time filters for PirkanmaaEvents API query.
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
  private function getSort(FieldItemListInterface $sort_order_field_items) {
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
   * @param object $item
   *   The event object from PirkanmaaEvents API.
   *
   * @return string
   *   The formatted string for the time of the event.
   */
  private function getEventTimeString($item): string {
    $start_timestamp_from_start_date = (new DrupalDateTime((new DrupalDateTime($item["event"]["start"]))->format('Y-m-d', ['timezone' => 'Europe/Helsinki'])))->getTimestamp();
    $end_timestamp_from_end_date = (new DrupalDateTime((new DrupalDateTime($item["event"]["end"]))->format('Y-m-d', ['timezone' => 'Europe/Helsinki'])))->getTimestamp();

    if ($end_timestamp_from_end_date > $start_timestamp_from_start_date) {
      $start_date_formatted = (new DrupalDateTime($item["event"]["start"]))->format('j.n.Y', ['timezone' => 'Europe/Helsinki']);
      $end_date_formatted = (new DrupalDateTime($item["event"]["end"]))->format('j.n.Y', ['timezone' => 'Europe/Helsinki']);
      $event_time = sprintf('%s–%s', $start_date_formatted, $end_date_formatted);
    }
    else {
      $event_time = empty($item["event"]["start"]) ? '' : (new DrupalDateTime($item["event"]["start"]))->format('j.n.Y H.i', ['timezone' => 'Europe/Helsinki']);
    }
    return $event_time;
  }

}
