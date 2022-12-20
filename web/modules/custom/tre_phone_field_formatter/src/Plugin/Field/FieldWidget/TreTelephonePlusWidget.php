<?php

namespace Drupal\tre_phone_field_formatter\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Locale\CountryManager;
use Drupal\telephone_plus\Plugin\Field\FieldWidget\TelephonePlusWidget;

/**
 * Plugin implementation of the 'telephone_plus_widget' widget with options.
 *
 * @FieldWidget (
 *   id = "tre_telephone_plus_widget",
 *   label = @Translation("Telephone Plus widget with hideable subfields"),
 *   field_types = {
 *     "telephone_plus_field"
 *   }
 * )
 */
class TreTelephonePlusWidget extends TelephonePlusWidget {

  /**
   * PhoneNumber Util definition.
   *
   * @var \libphonenumber\PhoneNumberUtil
   */
  protected $phoneNumberUtil;

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $hideable_subfields = $this->getHideableSubfields();
    return [
      'hide_subelements' => [
        '#type' => 'checkboxes',
        '#options' => $hideable_subfields,
        '#default_value' => $this->getSetting('hide_subelements'),
      ],
    ] + parent::settingsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $hidden_subfields = array_keys(array_filter($this->getSetting('hide_subelements')));
    if (empty($hidden_subfields)) {
      $summary = $this->t('No subfields hidden');
    }
    else {
      $hidden_subfield_strings = [];
      $hideable_subfield_strings = $this->getHideableSubfields();
      foreach ($hidden_subfields as $subfield) {
        if (in_array($subfield, $hidden_subfields, TRUE)) {
          $hidden_subfield_strings[] = $hideable_subfield_strings[$subfield];
        }
      }

      $summary = $this->t('Hidden subfields: @subfields', ['@subfields' => implode(', ', $hidden_subfield_strings)]);
    }

    return [$summary];
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'hide_subelements' => [],
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $settings = $this->fieldDefinition->getSettings();
    $hidden_subelements = $this->getHiddenSubfieldKeys();

    if ($settings['title_enabled']) {
      $element['telephone_title'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Title', [], ['context' => 'TelephonePlus field']),
        '#default_value' => $items[$delta]->telephone_title ?? NULL,
      ];
    }

    $element['telephone_container'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['container-inline']],
    ];

    if (!$settings['country_code_enabled'] || $this->isDefaultValueWidget($form_state)) {
      $element['telephone_container']['country_code'] = [
        '#type' => 'hidden',
        '#size' => 2,
        '#default_value' => $items[$delta]->country_code ?? $settings['default_country_code'],
      ];
    }
    else {
      $element['telephone_container']['country_code'] = [
        '#type' => 'select',
        '#title' => $this->t('Country', [], ['context' => 'TelephonePlus field']),
        '#label_attributes' => ['style' => ['display: block']],
        '#options' => CountryManager::getStandardList(),
        '#default_value' => $items[$delta]->country_code ?? $settings['default_country_code'],
      ];
    }

    $element['telephone_container']['telephone_number'] = [
      '#type' => 'tel',
      '#title' => $this->t('Phone number', [], ['context' => 'TelephonePlus field']),
      '#label_attributes' => ['style' => ['display: block']],
      '#default_value' => $items[$delta]->telephone_number ?? NULL,
    ];

    if (!in_array('telephone_extension', $hidden_subelements, TRUE)) {
      $element['telephone_container']['telephone_extension'] = [
        '#type' => 'textfield',
        '#size' => 10,
        '#title' => $this->t('Extension', [], ['context' => 'TelephonePlus field']),
        '#wrapper_attributes' => ['class' => ['container-inline']],
        '#default_value' => $items[$delta]->telephone_extension ?? NULL,
      ];
    }
    else {
      $element['telephone_container']['telephone_extension'] = [
        '#type' => 'hidden',
        '#size' => 10,
        '#default_value' => $items[$delta]->telephone_extension ?? NULL,
      ];
    }

    if ($settings['supplementary_enabled'] && !in_array('telephone_supplementary', $hidden_subelements, TRUE)) {
      $element['telephone_supplementary'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Supplementary', [], ['context' => 'TelephonePlus field']),
        '#default_value' => $items[$delta]->telephone_supplementary ?? NULL,
        '#description' => $this->t('Additional information such as operating hours.', [], ['context' => 'TelephonePlus field']),
      ];
    }

    $element['display_international_number'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display as international dialing number format', [], ['context' => 'TelephonePlus field']),
      '#default_value' => $items[$delta]->display_international_number ?? NULL,
      '#access' => !in_array('display_international_number', $hidden_subelements, TRUE),
    ];

    // If cardinality is 1, ensure a label is output for the field by wrapping
    // it in a details element.
    if ($this->fieldDefinition->getFieldStorageDefinition()->getCardinality() == 1) {
      $element += [
        '#type' => 'fieldset',
        '#attributes' => ['class' => ['container-inline']],
      ];
    }
    $element['#element_validate'][] = [static::class, 'validateTelephoneNumber'];

    return $element;
  }

  /**
   * Contains the options for the hideable subfields option in the widget.
   *
   * @return array
   *   The hideable subfield descriptions keyed by their names in the
   *   formElement method.
   */
  protected function getHideableSubfields() {
    return [
      'telephone_title' => $this->t('Title', [], ['context' => 'TelephonePlus field']),
      'telephone_extension' => $this->t('Extension', [], ['context' => 'TelephonePlus field']),
      'telephone_supplementary' => $this->t('Supplementary', [], ['context' => 'TelephonePlus field']),
      'display_international_number' => $this->t('Display as international dialing number format', [], ['context' => 'TelephonePlus field']),
    ];
  }

  /**
   * Returns the hidden subfield keys from the settings.
   *
   * The settings are saved in an array format with the subfield names as the
   * keys and with either 0 or 1 as the value.
   *
   * @return string[]
   *   The keys of the subfields selected to be hidden in the settings of the
   *   widget.
   */
  protected function getHiddenSubfieldKeys() {
    return array_keys(array_filter($this->getSetting('hide_subelements')));
  }

}
