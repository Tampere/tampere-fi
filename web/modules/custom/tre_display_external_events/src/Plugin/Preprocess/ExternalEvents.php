<?php

namespace Drupal\tre_display_external_events\Plugin\Preprocess;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Site\Settings;
use Drupal\tre_display_external_events\Config;
use Drupal\tre_preprocess\TrePreProcessPluginBase;
use Drupal\tre_preprocess_utility_functions\Utils\HelperFunctionsInterface;
use Geniem\LinkedEvents\LinkedEventsClient;

/**
 * List of attachments paragraph preprocessing.
 *
 * @Preprocess(
 *   id = "tre_display_external_events.preprocess.paragraph__event_and_hobby_listing",
 *   hook = "paragraph__event_and_hobby_listing"
 * )
 */
class ExternalEvents extends TrePreProcessPluginBase {

  const DEFAULT_SORT_ORDER = 'default';
  const SORT_ORDERS = [
    'end_asc' => 'end_time',
  ];

  /**
   * The default location for listings that have no more specific place listed.
   *
   * This value corresponds to the area/location 'Tampere':
   * https://pirkanmaaevents.fi/api/v2/place/system:17/
   *
   * @var string
   */
  const DEFAULT_LOCATION_FILTER_VALUE = 'system:17';

  const EVENT_LIST_CACHE_TAG = 'tre_display_external_events:list';

  /**
   * {@inheritdoc}
   *
   * @see tre_display_external_events_cron()
   */
  public function preprocess(array $variables): array {
    $paragraph = $variables['paragraph'];
    /** @var \Drupal\paragraphs\ParagraphInterface $translated_paragraph */
    $translated_paragraph = $this->entityRepository->getTranslationFromContext($paragraph);
    $max_num_events = intval($this->helperFunctions->getFieldValueString($translated_paragraph, 'field_event_display_amount'));

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
      if (empty($item->end_time)) {
        return TRUE;
      }

      $end_date = new DrupalDateTime($item->end_time);
      $event_end_timestamp = $end_date->getTimestamp();
      $current_timestamp = $current_date->getTimestamp();
      return $event_end_timestamp > $current_timestamp;
    };

    $ext_location = $translated_paragraph->get('field_event_external_places')->getValue();
    $location_list = $this->helperFunctions->getListFieldValues($ext_location);

    $ext_audience = $translated_paragraph->get('field_event_external_audience')->getValue();
    $audience_list = $this->helperFunctions->getListFieldValues($ext_audience);

    $ext_subcategory = $translated_paragraph->get('field_event_external_subcategory')->getValue();
    $subcategory_list = $this->helperFunctions->getListFieldValues($ext_subcategory);

    $ext_organization_class = $translated_paragraph->get('field_event_external_org_class')->getValue();
    $org_class_list = $this->helperFunctions->getListFieldValues($ext_organization_class);

    $events_to_exclude = $translated_paragraph->get('field_excluded_events')->getValue();
    $events_to_exclude_list = $this->helperFunctions->getListFieldValues($events_to_exclude);

    $base_url = Settings::get(Config::EVENTS_API_BASE_URL_SETTINGS_KEY, Config::EVENTS_API_BASE_URL);
    $desired_language = $translated_paragraph->get('langcode')->getString();

    $client = new LinkedEventsClient($base_url);

    $variables['featured_liftup_exists'] = !$translated_paragraph->get('field_featured_event_id')->isEmpty();
    $desired_featured_event = NULL;
    if ($variables['featured_liftup_exists']) {
      try {
        $desired_featured_event = $client->get('event/' . $translated_paragraph->get('field_featured_event_id')->getString());
        $variables['featured_liftup_exists'] = !empty($desired_featured_event);
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

    // @todo Preferrably utilise the api to get all or any of events matching
    // the combined filter of audiences, keywords and organizational classes.
    // The problem for now is the combination logic: is the query an OR or an
    // AND query?
    // If we need support for both, then it's probably best to refactor the
    // querying into a service class with its own cache for results per query.
    $locations = implode(",", $location_list);
    // If the paragraph lists no locations and no organization class, limit the
    // location to the area 'Tampere'.
    if (empty($locations) && empty($org_class_list)) {
      $locations = self::DEFAULT_LOCATION_FILTER_VALUE;
    }
    $audiences = implode(",", $audience_list);
    $keywords = implode(",", $subcategory_list);
    $org_classes = implode(",", $org_class_list);
    $api_parameters = [
      // Since both org_classes and keywords derive from the same base, they
      // need to be combined into one filter towards the API.
      "keyword" => implode(",", array_filter([$keywords, $org_classes])),
      "audience" => $audiences,
      "location" => $locations,
      "language" => $desired_language,
      "include" => "location,keywords",
      "page_size" => $page_size,
    ];
    $api_parameters += $this->getTimeFilters($translated_paragraph->get('field_pirkanmaa_events_start'), $translated_paragraph->get('field_pirkanmaa_events_end'), $current_date);
    $api_parameters += $this->getPublisherFilter($translated_paragraph->get('field_show_events_by_tre_only'));
    $api_parameters += $this->getSort($translated_paragraph->get('field_pirkanmaa_events_sort'));
    $api_parameters = array_filter($api_parameters);

    try {
      $results = $client->get('event', $api_parameters);
    }
    catch (\Exception $e) {
      $this->messenger()->addMessage($e->getMessage(), "error");
      $this->messenger()->addMessage($this->t('Fetching events failed.'), 'error');
      $results = [];
    }

    $default_image = "/themes/custom/tampere/images/placeholder-green-waves.svg";

    if (is_object($desired_featured_event) && isset($desired_featured_event->id)) {
      $desired_featured_event_list = [$desired_featured_event];
      // Check that the featured event has not ended yet. If it has, tell the
      // template not to display a featured event at all.
      $desired_featured_event_list = array_filter($desired_featured_event_list, $event_start_time_validator);

      if (!empty($desired_featured_event_list)) {
        $events_to_exclude_list[] = $desired_featured_event->id;

        // If the featured event has no name in the desired language, don't
        // render it.
        if (!empty($desired_featured_event->name->{$desired_language})) {

          // Handle image logic: use image style render array for external
          // images, but in case there is no image in the API response, use the
          // default SVG image.
          $featured_image_src = empty($desired_featured_event->images[0]->url) ? '' : $desired_featured_event->images[0]->url;
          $featured_default_image_src = empty($featured_image_src) ? $default_image : '';
          $featured_image = [];
          if (!empty($featured_image_src)) {
            $featured_image = [
              '#theme' => 'imagecache_external_responsive',
              '#uri' => $featured_image_src,
              '#responsive_image_style_id' => 'large_listing_liftup_responsive_image_style',
            ];
          }

          // Filter out empty elements from the variables going to the template.
          $variables['featured_liftup'] = array_filter([
            'image' => $featured_image,
            'image_src' => $featured_default_image_src,
            'date' => $this->getEventTimeString($desired_featured_event),
            'location' => $desired_featured_event->location_extra_info->{$desired_language} ?? $desired_featured_event->location->name->{$desired_language} ?? '',
            'name' => $desired_featured_event->name->{$desired_language},
            'summary' => $desired_featured_event->short_description->{$desired_language} ?? '',
            'url' => "https://pirkanmaaevents.fi/event/" . $desired_featured_event->id,
          ]);
        }
      }
      else {
        $variables['featured_liftup_exists'] = FALSE;
      }
    }

    // Filter out events which are in the exclusion list.
    $results = array_filter($results, function ($item) use ($events_to_exclude_list) {
      return !in_array($item->id, $events_to_exclude_list, TRUE);
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
      $image_src = empty($item->images) ? '' : $item->images[0]->url;
      $default_image_src = empty($image_src) ? $default_image : '';
      $image = [];
      if (!empty($image_src)) {
        $image = [
          '#theme' => 'imagecache_external_responsive',
          '#uri' => $image_src,
          '#responsive_image_style_id' => 'listing_liftup_responsive_image_style',
        ];
      }
      $location = $item->location_extra_info->{$desired_language} ?? $item->location->name->{$desired_language} ?? '';
      $info_url = "https://pirkanmaaevents.fi/event/" . $item->id . '?lang=' . $desired_language;
      $event_time = $this->getEventTimeString($item);

      // Filter out empty elements from the variables going to the template.
      $events[] = array_filter([
        "card__display_as_list_item" => TRUE,
        "card__heading" => $item->name->{$desired_language},
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
    $topical_listing_link = [
      'text' => 'tapahtumat.tampere.fi',
      'url' => 'https://tapahtumat.tampere.fi',
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

    if ($start_date->isEmpty() && $end_date->isEmpty()) {
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
   * Deduce publisher filter for PirkanmaaEvents API query.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $publisher_value_list
   *   The field items for the 'list only events by Tampere' field.
   *
   * @return array
   *   The appropriate publisher filter, i.e. nothing if the checkbox field has
   *   not been checked and filtering by Tampere if it has been.
   */
  private function getPublisherFilter(FieldItemListInterface $publisher_value_list) {
    $filters = [];
    if (!$publisher_value_list->isEmpty() && $publisher_value_list->getString() == HelperFunctionsInterface::BOOLEAN_FIELD_TRUE) {
      $filters['publisher'] = 'city:tampere';
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
        $sort_order_field_items->getString() !== self::DEFAULT_SORT_ORDER &&
        array_key_exists($sort_order_field_items->getString(), self::SORT_ORDERS)) {
      $sort_param = [
        'sort' => self::SORT_ORDERS[$sort_order_field_items->getString()],
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
    $start_timestamp_from_start_date = (new DrupalDateTime((new DrupalDateTime($item->start_time))->format('Y-m-d', ['timezone' => 'Europe/Helsinki'])))->getTimestamp();
    $end_timestamp_from_end_date = (new DrupalDateTime((new DrupalDateTime($item->end_time))->format('Y-m-d', ['timezone' => 'Europe/Helsinki'])))->getTimestamp();

    if ($end_timestamp_from_end_date > $start_timestamp_from_start_date) {
      $start_date_formatted = (new DrupalDateTime($item->start_time))->format('j.n.Y', ['timezone' => 'Europe/Helsinki']);
      $end_date_formatted = (new DrupalDateTime($item->end_time))->format('j.n.Y', ['timezone' => 'Europe/Helsinki']);
      $event_time = sprintf('%s–%s', $start_date_formatted, $end_date_formatted);
    }
    else {
      $event_time = empty($item->start_time) ? '' : (new DrupalDateTime($item->start_time))->format('j.n.Y H.i', ['timezone' => 'Europe/Helsinki']);
    }
    return $event_time;
  }

}
