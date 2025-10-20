<?php

namespace Drupal\tre_display_external_eventz_today\Plugin\Preprocess;

use Drupal\tre_display_external_eventz_today\Config;
use Drupal\tre_preprocess\TrePreProcessPluginBase;

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

  const TYPES_LOOKUP = [
    'fi' => [
      "virtual" => "virtuaali",
      "free" => "maksuton",
      "accessible" => "esteetön",
    ],
    'en' => [
      "virtual" => "virtual",
      "free" => "free of charge",
      "accessible" => "accessible",
    ],
  ];

  const CUSTOM_ERROR_MESSAGE = [
    'en' => 'Problems in fetching events.',
    'fi' => 'Virhetilanne: Tapahtumia ei voida hakea.',
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
   * Attaches the JS library that is used to hydrate the paragraph.
   */
  public function preprocess(array $variables): array {
    $paragraph = $variables['paragraph'];

    // Attach the JS library and pass in the pargraph id.
    $variables['#attached']['library'][] = 'tre_display_external_eventz_today/eventz-loader';
    $variables['#attached']['drupalSettings']['eventzToday']['paragraphs'][$paragraph->id()] = TRUE;
    $variables['paragraph_id'] = $paragraph->id();

    return $variables;
  }

}
