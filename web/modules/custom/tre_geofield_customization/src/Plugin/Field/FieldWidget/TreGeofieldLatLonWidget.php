<?php

namespace Drupal\tre_geofield_customization\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

use Drupal\geofield\Plugin\Field\FieldWidget\GeofieldLatLonWidget;

/**
 * Plugin implementation of the 'tre_geofield_latlon' widget.
 *
 * @FieldWidget(
 *   id = "tre_geofield_latlon",
 *   label = @Translation("TreCustomized Latitude/Longitude"),
 *   field_types = {
 *     "geofield"
 *   }
 * )
 */
class TreGeofieldLatLonWidget extends GeofieldLatLonWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $geofield_item = $items->getValue()[$delta];
    if (empty($geofield_item) || $geofield_item['geo_type'] == 'Point') {
      $element['value']['#type'] = 'tre_geofield_latlon';
    }

    return $element;
  }

}
