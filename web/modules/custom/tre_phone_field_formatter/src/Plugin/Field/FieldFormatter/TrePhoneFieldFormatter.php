<?php

namespace Drupal\tre_phone_field_formatter\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\telephone_plus\Plugin\Field\FieldFormatter\TelephonePlusPlainFormatter;
use Drupal\telephone_plus\TelephonePlusFormatter;

/**
 * Customized plugin implementation of the 'tre_phone_field_plain' formatter.
 * Based on original 'telephone_plus_plain' formatter.
 *
 * @FieldFormatter(
 *   id = "tre_phone_field_plain",
 *   label = @Translation("Reordered plain text (TRE)"),
 *   field_types = {
 *     "telephone_plus_field"
 *   }
 * )
 */
class TrePhoneFieldFormatter extends TelephonePlusPlainFormatter {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'force_format' => 'default',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $format_options = [
      'default' => $this->t('Let TelephonePlus format the number'),
      'as_is' => $this->t('Do not format the number at all'),
    ];
    return [
      'force_format' => [
        '#type' => 'select',
        '#title' => $this->t('Phone number formatting'),
        '#description' => $this->t('The default is that TelephonePlus module formats the number respecting the field setting for international presentation.'),
        '#options' => $format_options,
        '#default_value' => $this->getSetting('force_format'),
      ],
    ] + parent::settingsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $format = $this->getSetting('force_format');
    switch ($format) {
      case 'as_is':
        $format_text = $this->t('Number output as is from field input');
        break;

      default:
        $format_text = $this->t('Number formatted by TelephonePlus');
    }

    return [$format_text];
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($items as $delta => $item) {
      $title = NULL;
      $supplementary = NULL;

      switch ($this->getSetting('force_format')) {
        case 'as_is':
          $telephone_text = $item->telephone_number;
          break;

        default:
          // TelephonePlus display text.
          $telephone = new TelephonePlusFormatter($item->telephone_number, $item->telephone_extension, $item->country_code);
          $telephone_text = $telephone->text($item->display_international_number);
          break;
      }

      if (!empty($item->telephone_title)) {
        $title = [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#attributes' => ['class' => ['title']],
          '#value' => $item->telephone_title,
        ];
      }

      if ($item->telephone_extension) {
        $telephone_text .= ' ' . t('extension  :extension', [':extension' => $item->telephone_extension]);
      }

      $phone = [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#attributes' => ['class' => ['number']],
        '#value' => $telephone_text,
      ];

      if (!empty($item->telephone_supplementary)) {
        $supplementary = [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#attributes' => ['class' => ['supplementary']],
          '#value' => $item->telephone_supplementary,
        ];
        // Add extra class to phone.
        array_push($phone['#attributes']['class'], 'number--supplementary');
      }

      if ($title) {
        $elements[$delta]['title'] = $title;
      }

      $elements[$delta]['number'] = $phone;

      if ($supplementary) {
        $elements[$delta]['supplementary'] = $supplementary;
      }

    }

    return $elements;
  }

}
