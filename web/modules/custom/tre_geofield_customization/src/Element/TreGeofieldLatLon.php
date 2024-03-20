<?php

namespace Drupal\tre_geofield_customization\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\geofield\Element\GeofieldLatLon;

/**
 * Provides a custom Geofield Lat Lon form element.
 *
 * This custom class also accepts commas as a decimal
 * separator in lat and lon subfields.
 *
 * @FormElement("tre_geofield_latlon")
 */
class TreGeofieldLatLon extends GeofieldLatLon {

  /**
   * {@inheritdoc}
   */
  public static function elementValidate(array &$element, FormStateInterface $form_state, array &$complete_form) {

    if (array_key_exists('lat', $element)) {
      $element['lat']['#value'] = str_replace(',', '.', $element['lat']['#value']);
    }

    if (array_key_exists('lon', $element)) {
      $element['lon']['#value'] = str_replace(',', '.', $element['lon']['#value']);
    }

    $field_location = $form_state->getValue('field_location');
    $field_location[0]['value']['lat'] = str_replace(',', '.', $field_location[0]['value']['lat']);
    $field_location[0]['value']['lon'] = str_replace(',', '.', $field_location[0]['value']['lon']);
    $form_state->setValue('field_location', $field_location);

    parent::elementValidate($element, $form_state, $complete_form);

  }

}
