<?php

namespace Drupal\tre_display_external_eventz_today\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Site\Settings;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\tre_display_external_eventz_today\Config;
use Drupal\tre_display_external_eventz_today\Plugin\EventzClientCustomized;
use Drupal\tre_display_external_eventz_today\Plugin\Preprocess\EventzTodayListing;
use Drupal\tre_preprocess_utility_functions\Utils\HelperFunctionsInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Entity\EntityRepositoryInterface;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an API endpoint to fetch and process external events.
 */
class EventzApiController extends ControllerBase {

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
   * The entity repository service.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The helper functions.
   *
   * @var \Drupal\tre_preprocess_utility_functions\Utils\HelperFunctionsInterface
   */
  private HelperFunctionsInterface $helperFunctions;

  /**
   * The constructor.
   */
  public function __construct(HelperFunctionsInterface $helper_functions, EntityRepositoryInterface $entity_repository) {
    $this->helperFunctions = $helper_functions;
    $this->entityRepository = $entity_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('tre_preprocess_utility_functions.helper_functions'),
      $container->get('entity.repository'),
    );
  }

  /**
   * Fetches and returns events for a given paragraph.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph entity.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response containing the events.
   */
  public function getEvents(ParagraphInterface $paragraph): JsonResponse {
    try {
      /** @var \Drupal\paragraphs\ParagraphInterface $translated_paragraph */
      $translated_paragraph = $this->entityRepository->getTranslationFromContext($paragraph);
      $this->desiredLanguage = $translated_paragraph->get('langcode')->getString();

      $max_num_events = intval($this->helperFunctions->getFieldValueString($translated_paragraph, 'field_eventz_display_amount'));
      $current_date = new DrupalDateTime('now', 'Europe/Helsinki');

      $base_url = Settings::get(Config::EVENTZ_TODAY_API_KEY_SETTINGS_URL);
      $api_key = Settings::get(Config::EVENTZ_TODAY_API_KEY_SETTINGS_KEY);
      $this->eventzClient = new EventzClientCustomized($base_url, $api_key);

      $event_start_time_validator = function ($item) use ($current_date) {
        $event_date = $item["event"]["dates"][0] ?? $item["event"];
        if (empty($event_date["end"])) {
          return TRUE;
        }
        $end_date = new DrupalDateTime($event_date["end"]);
        return $end_date->getTimestamp() > $current_date->getTimestamp();
      };

      // Gather all filters from paragraph fields.
      $organizer_list = $this->helperFunctions->getListFieldValues($translated_paragraph->get('field_eventz_external_organizers')->getValue());
      $excl_organizer_list = $this->helperFunctions->getListFieldValues($translated_paragraph->get('field_eventz_excl_organizers')->getValue());
      $location_list = $this->helperFunctions->getListFieldValues($translated_paragraph->get('field_eventz_external_places')->getValue());
      $excl_location_list = $this->helperFunctions->getListFieldValues($translated_paragraph->get('field_eventz_excl_places')->getValue());
      $audience_list = $this->helperFunctions->getListFieldValues($translated_paragraph->get('field_eventz_external_audience')->getValue());
      $category_list = $this->helperFunctions->getListFieldValues($translated_paragraph->get('field_eventz_external_category')->getValue());
      $excl_category_list = $this->helperFunctions->getListFieldValues($translated_paragraph->get('field_eventz_excl_category')->getValue());
      $org_class_list = $this->helperFunctions->getListFieldValues($translated_paragraph->get('field_eventz_external_org_class')->getValue());
      $excl_class_list = $this->helperFunctions->getListFieldValues($translated_paragraph->get('field_eventz_excl_org_class')->getValue());
      $area_list = $this->helperFunctions->getListFieldValues($translated_paragraph->get('field_eventz_external_areas')->getValue());
      $type_list_raw = $this->helperFunctions->getListFieldValues($translated_paragraph->get('field_eventz_event_types')->getValue());
      $events_to_exclude_list = $this->helperFunctions->getListFieldValues($translated_paragraph->get('field_eventz_excluded_events')->getValue());

      $type_list = array_map(function ($item) {
        return EventzTodayListing::TYPES_LOOKUP[$this->desiredLanguage][$item] ?? NULL;
      }, $type_list_raw);
      $type_list = array_filter($type_list);

      $featured_liftup_exists = !$translated_paragraph->get('field_eventz_featured_event_id')->isEmpty();
      $desired_featured_event = NULL;
      if ($featured_liftup_exists) {
        try {
          $desired_featured_event = $this->eventzClient->get_item($translated_paragraph->get('field_eventz_featured_event_id')->getString(), $this->desiredLanguage);
          $featured_liftup_exists = !empty($desired_featured_event);
          $desired_featured_event = json_decode(json_encode($desired_featured_event), TRUE);
        }
        catch (\Exception $e) {
          $featured_liftup_exists = FALSE;
        }
      }

      $page_size = $featured_liftup_exists ? $max_num_events + 1 : $max_num_events;
      if (!empty($excl_category_list) || !empty($excl_class_list)) {
        $page_size *= 5;
      }
      // In case there were events to exclude listed, increase the page size by
      // the amount of exclusions to be able to fill the list.
      $page_size += count($events_to_exclude_list);

      // Since we are planning to eventually drop events from the response based
      // on whether the end date has passed, we need to adjust the page size.
      // The agreed-upon amount of additional events to request is 6.
      $page_size += 6;

      $excl_topics_list = array_merge($excl_category_list, $excl_class_list);

      $audiences = implode(",", $audience_list);
      $categories = implode(",", array_merge($category_list, $org_class_list));
      $host_ids = implode(",", array_merge($organizer_list, $location_list));
      $areas = implode(",", $area_list);

      // If the paragraph lists no locations, no organization class and
      // no area selected, limit the areas to the default list.
      if (empty($location_list)  && empty($org_class_list) && empty($area_list)) {
        $areas = implode(",", EventzTodayListing::DEFAULT_AREAS_FILTER_LIST);
      }

      // Build API parameters.
      $api_parameters = [
        "targets" => $audiences,
        "category_id" => $categories,
        "size" => $page_size,
        "host_ids" => $host_ids,
        "areas" => $areas,
      ];
      $api_parameters += $this->getTimeFilters($translated_paragraph->get('field_eventz_event_start'), $translated_paragraph->get('field_eventz_event_end'), $current_date);
      $api_parameters += $this->getSort($translated_paragraph->get('field_eventz_events_sort'));

      $featured_liftup_data = [];
      $featured_start = $translated_paragraph->get('field_eventz_featured_start')->first()->date ?? NULL;
      $featured_end = $translated_paragraph->get('field_eventz_featured_end')->first()->date ?? NULL;

      if (isset($desired_featured_event["_id"]) &&
          $desired_featured_event["language"] == $this->desiredLanguage &&
          !$this->isEventExpired($desired_featured_event, $current_date) &&
          !empty($featured_start) && !empty($featured_end) &&
          $featured_start->getTimestamp() <= $current_date->getTimestamp() &&
          $featured_end->getTimestamp() >= $current_date->getTimestamp()
        ) {
        $events_to_exclude_list[] = $desired_featured_event["_id"];
        $desired_featured_event = $this->removeExpiredEventDates($desired_featured_event, $featured_start);
        $featured_liftup_data = $this->makeFeaturedLiftupFromEvent($desired_featured_event);
      }

      // Main fetching loop.
      $all_results = [];
      $number_of_fetched_events = 0;
      while (count($all_results) < $max_num_events) {
        $api_parameters["skip"] = $number_of_fetched_events;
        $response = $this->fetchEvents($api_parameters);

        if ($response["error"]) {
          // If the first call fails, error out.
          // Otherwise, proceed with what we have.
          if (empty($all_results)) {
            throw new \Exception('API error during event fetch.');
          }
          break;
        }

        $results = $response["result"];
        $number_of_fetched_events += count($results);
        if (!count($results)) {
          break;
        }

        $results = $this->excludeEventsBy($results, $excl_topics_list, $excl_organizer_list, $excl_location_list);
        $results = $this->includeEventsBy($results, $type_list, $organizer_list, $location_list);
        $results = array_filter($results, fn($item) => !in_array($item["_id"], $events_to_exclude_list, TRUE));
        $results = array_filter($results, $event_start_time_validator);

        $all_results = array_merge($all_results, $results);
        $api_parameters["size"] *= 2;
      }

      $final_results = array_slice($all_results, 0, $max_num_events);
      $liftups = $this->makeSimpleLiftupsFromEvents($final_results);

      $heading = $translated_paragraph->get('field_title')->getString();

      $topical_listing_link = [];
      $external_link_field = $translated_paragraph->get('field_eventz_external_link');
      $external_link_url = $this->helperFunctions->getExternalLinkFieldUrl($external_link_field);
      $external_link_title = $this->helperFunctions->getExternalLinkFieldTitle($external_link_field);

      if (empty($external_link_url)) {
        $external_link_url = ($this->desiredLanguage == 'en') ? 'https://tapahtumat.tampere.fi/en-FI' : 'https://tapahtumat.tampere.fi';
      }
      if (empty($external_link_title)) {
        $external_link_title = 'tapahtumat.tampere.fi';
      }

      $topical_listing_link[] = [
        'text' => $external_link_title,
        'url' => $external_link_url,
        'icon' => 'external',
      ];

      return new JsonResponse([
        'success' => TRUE,
        'heading' => $heading,
        'liftups' => $liftups,
        'featured_liftup_exists' => !empty($featured_liftup_data),
        'featured_liftup' => $featured_liftup_data,
        'topical_listing_link' => $topical_listing_link,
      ]);
    }
    catch (\Exception $e) {
      $error_message = EventzTodayListing::CUSTOM_ERROR_MESSAGE[$this->desiredLanguage] ?? EventzTodayListing::CUSTOM_ERROR_MESSAGE['fi'];
      return new JsonResponse([
        'success' => FALSE,
        'message' => $error_message,
        'message_list' => [
          'error' => [$error_message],
        ],
      ], 500);
    }
  }

  /**
   * Fetches events based on parameters from Event APIs.
   *
   * @param array $api_parameters
   *   List of parameters needed for fetching.
   *
   * @return array
   *   Result object containing the events and error flag.
   */
  private function fetchEvents(array $api_parameters): array {
    $api_parameters = array_filter($api_parameters);

    try {
      $results = $this->eventzClient->search_events($api_parameters, $this->desiredLanguage);

      return [
        'result' => json_decode(json_encode($results), TRUE),
        'error' => FALSE,
      ];
    }
    catch (\Exception $e) {
      return [
        'result' => [],
        'error' => TRUE,
      ];
    }
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
  private function getTimeFilters(FieldItemListInterface $start_date, FieldItemListInterface $end_date, DrupalDateTime $current_time): array {
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
        $sort_order_field_items->getString() !== EventzTodayListing::DEFAULT_SORT_ORDER
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
   * Remove expired event dates based on the input start date.
   *
   * @param array $item
   *   The event object from Eventz Today API.
   * @param \Drupal\Core\Datetime\DrupalDateTime $start_date
   *   The start date based which old event dates get removed.
   *
   * @return array
   *   Updated event object with the old event dates removed.
   */
  private function removeExpiredEventDates(array $item, DrupalDateTime $start_date): array {
    if (array_key_exists("dates", $item["event"]) && !empty($item["event"]["dates"])) {
      $new_dates = [];
      foreach ($item["event"]["dates"] as $event_date) {
        $start_date_formatted = new DrupalDateTime($event_date["start"]);
        if ($start_date_formatted->getTimestamp() > $start_date->getTimestamp()) {
          $new_dates[] = $event_date;
        }
      }
      $item["event"]["dates"] = $new_dates;
    }
    return $item;
  }

  /**
   * Returns events that belong to all Non-empty lists.
   *
   * @param array $events
   *   List of events.
   * @param array $type_list
   *   List of included types.
   * @param array $organizer_list
   *   List of IDs of included organizers.
   * @param array $location_list
   *   List of IDs of included locations.
   *
   * @return array
   *   Final list of events after the inclusion.
   */
  private function includeEventsBy(array $events, array $type_list, array $organizer_list, array $location_list): array {
    if (empty($type_list) && empty($organizer_list) && empty($location_list)) {
      return $events;
    }

    return array_filter($events, function ($item) use ($type_list, $organizer_list, $location_list) {
      // By default, the item is in all the inclusion lists.
      $is_in_types = TRUE;
      $is_in_organizers = TRUE;
      $is_in_locations = TRUE;

      if (!empty($type_list)) {
        $matches = array_intersect(array_map('mb_strtolower', $item['tags']), $type_list);
        $is_in_types = !empty($matches);
      }

      if (!empty($organizer_list)) {
        $is_in_organizers = FALSE;
        if (array_key_exists("owner_id", $item) && in_array($item["owner_id"], $organizer_list, TRUE)) {
          $is_in_organizers = TRUE;
        }
      }

      if (!empty($location_list)) {
        $is_in_locations = FALSE;
        if (array_key_exists("locations", $item)) {
          foreach ($item["locations"] as $location) {
            // If the event includes at least one of
            // the included locations, it must be kept.
            if (array_key_exists("page_id", $location) && in_array($location["page_id"], $location_list, TRUE)) {
              $is_in_locations = TRUE;
              break;
            }
          }
        }
      }

      // Since this is an AND filtering between different parameters,
      // We keep the item only if it is in all lists.
      return $is_in_types && $is_in_organizers && $is_in_locations;
    });

  }

  /**
   * Returns events that do not belong to any of the Non-empty lists.
   *
   * @param array $events
   *   List of events.
   * @param array $excl_categories
   *   List of excluded categories.
   * @param array $excl_organizer_list
   *   List of excluded organizers.
   * @param array $excl_location_list
   *   List of excluded locations.
   *
   * @return array
   *   Final list of events after the exclusion.
   */
  private function excludeEventsBy(array $events, array $excl_categories, array $excl_organizer_list, array $excl_location_list): array {
    if (empty($excl_categories) && empty($excl_organizer_list) && empty($excl_location_list)) {
      return $events;
    }

    $topics_name_id_pairs = $this->eventzClient->getAllCategoriesByNameIdPair($this->desiredLanguage);

    return array_filter($events, function ($item) use ($topics_name_id_pairs, $excl_categories, $excl_organizer_list, $excl_location_list) {
      // By default, the item is not in any of the exclusion lists.
      $is_in_topics = FALSE;
      $is_in_organizers = FALSE;
      $is_in_locations = FALSE;

      if (!empty($excl_categories)) {
        foreach ($item["topics"] as $topic) {
          // If the event includes at least one of
          // the excluded topics, it must be removed.
          if (array_key_exists($topic, $topics_name_id_pairs) &&
            in_array($topics_name_id_pairs[$topic], $excl_categories, TRUE)) {
              $is_in_topics = TRUE;
          }
        }
      }

      if (!empty($excl_organizer_list)) {
        if (array_key_exists("owner_id", $item) && in_array($item["owner_id"], $excl_organizer_list, TRUE)) {
          $is_in_organizers = TRUE;
        }
      }

      if (!empty($excl_location_list)) {
        if (array_key_exists("locations", $item)) {
          foreach ($item["locations"] as $location) {
            // If the event includes at least one of
            // the excluded locations, it must be removed.
            if (array_key_exists("page_id", $location) && in_array($location["page_id"], $excl_location_list, TRUE)) {
              $is_in_locations = TRUE;
              break;
            }
          }
        }
      }

      // Since this is an OR filtering between different parameters,
      // We keep the item only if it is not in any list.
      return !$is_in_topics && !$is_in_organizers && !$is_in_locations;

    });
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

  /**
   * Builds the featured liftup array from an event.
   */
  private function makeFeaturedLiftupFromEvent(array $event): array {
    $summary = strip_tags($event["descriptionShort"]);
    if (mb_strlen($summary) > EventzTodayListing::SUMMARY_LENGTH) {
      $summary = $this->extractFullSentences(mb_substr($summary, 0, EventzTodayListing::SUMMARY_LENGTH));
    }
    return array_filter([
      'image_src' => $event["images"]["imageDesktop"]["url"] ?? EventzTodayListing::EVENT_DEFAULT_IMAGE,
      'date' => $this->getEventTimeString($event),
      'location' => $event["locations"][0]["address"] ?? '',
      'name' => $event["name"],
      'summary' => $summary ?? '',
      'url' => $event["url"],
    ]);
  }

  /**
   * Builds simple liftup arrays from a list of events.
   */
  private function makeSimpleLiftupsFromEvents(array $events): array {
    $results = [];
    foreach ($events as $item) {
      $location = '';
      if (!empty($item["locations"])) {
        $location = $item["locations"][0]["address"];
        if (count($item["locations"]) > 1) {
          $suffix = ($this->desiredLanguage === 'fi') ? 'ja muissa paikoissa' : 'and other venues';
          $location .= ' ' . $suffix;
        }
      }
      $results[] = array_filter([
        "heading" => $item["name"],
        "date" => $this->getEventTimeString($item),
        "tag" => $location,
        "image_src" => $item["images"]["imageDesktop"]["url"] ?? EventzTodayListing::EVENT_DEFAULT_IMAGE,
        "link_url" => $item["url"],
      ]);
    }
    return $results;
  }

}
