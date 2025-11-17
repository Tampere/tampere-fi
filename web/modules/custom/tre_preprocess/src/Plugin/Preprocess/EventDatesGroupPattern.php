<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\node\NodeInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;
use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Dates Field Group Event pattern preprocessing for Notice nodes specifically.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.pattern_dates_field_group_event",
 *   hook = "pattern_dates_field_group_event"
 * )
 */
class EventDatesGroupPattern extends TrePreProcessPluginBase {

  /**
   * Customize the display of the event time including the start and end dates.
   */
  public function preprocess(array $variables): array {
    $node = $this->routeMatch->getParameter('node');

    if (!$node instanceof NodeInterface) {
      return $variables;
    }

    if ($node->bundle() !== 'notice') {
      return $variables;
    }

    $current_lang = $this->languageManager->getCurrentLanguage()->getId();
    if ($node->hasTranslation($current_lang)) {
      $node = $node->getTranslation($current_lang);
    }

    $date_value = $node->get('field_event_time')->value;
    if (empty($date_value)) {
      return $variables;
    }

    $datetime = new \DateTime($date_value, new \DateTimeZone('UTC'));
    $datetime->setTimezone(new \DateTimeZone('Europe/Helsinki'));

    $custom_start_date = $datetime->format('j.n.Y \a\t G.i');
    if ($current_lang == 'fi') {
      $custom_start_date = $datetime->format('j.n.Y \k\e\l\l\o G.i');
    }

    if ($node->hasField('field_do_not_show_the_start_time') && !$node->get('field_do_not_show_the_start_time')->isEmpty()) {
      $do_not_show_the_end_time = $node->get('field_do_not_show_the_start_time')->value;
      if ($do_not_show_the_end_time) {
        $custom_start_date = $datetime->format('j.n.Y');
      }
    }

    $date_value = $node->get('field_event_end_time')->value;
    if (empty($date_value)) {
      $variables['custom_event_dates'] = $this->t('Event time') . ' ' . $custom_start_date;
      return $variables;
    }

    $datetime = new \DateTime($date_value, new \DateTimeZone('UTC'));
    $datetime->setTimezone(new \DateTimeZone('Europe/Helsinki'));

    $custom_end_date = $datetime->format('j.n.Y \a\t G.i');
    if ($current_lang == 'fi') {
      $custom_end_date = $datetime->format('j.n.Y \k\e\l\l\o G.i');
    }

    if ($node->hasField('field_do_not_show_the_end_time') && !$node->get('field_do_not_show_the_end_time')->isEmpty()) {
      $do_not_show_the_end_time = $node->get('field_do_not_show_the_end_time')->value;
      if ($do_not_show_the_end_time) {
        $custom_end_date = $datetime->format('j.n.Y');
      }
    }

    $variables['custom_event_dates'] = $this->t('Event time') . ' ' . $custom_start_date . ' - ' . $custom_end_date;
    return $variables;
  }

}
