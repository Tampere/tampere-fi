<?php

declare(strict_types=1);

namespace Drupal\tre_preprocess\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldFilteredMarkup;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\OptGroup;

/**
 * Plugin implementation of the 'list_selected_options' formatter.
 *
 * @FieldFormatter(
 *   id = "list_selected_options_values",
 *   label = @Translation("Selected options from list as values"),
 *   field_types = {
 *     "list_string",
 *     "list_integer",
 *     "list_float"
 *   }
 * )
 */
class SelectedOptionsValues extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $settings = [];

    // Fall back to field settings by default.
    $settings['options_selected'] = [];

    return $settings + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm(form: $form, form_state: $form_state);

    $options_selected = $this->getSetting(key: 'options_selected');
    $form['options_selected'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Options to display'),
    ];

    $allowed_values = $this->getAllowedValues();

    foreach ($allowed_values as $value => $label) {
      $form['options_selected']['#options'][$value] = $label;
      if (in_array(needle: $value, haystack: $options_selected, strict: TRUE)) {
        $form['options_selected']['#default_value'][] = $value;
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $values = array_filter($this->getSetting(key: 'options_selected'));
    $allowed_values = $this->getAllowedValues();

    if (
      empty($values)
      || count($allowed_values) === count($values)
    ) {
      return ['Options to display: all'];
    }
    $summary = ['Options to display:'];
    foreach ($values as $value) {
      $value_to_display = $allowed_values[$value] ?? $value;
      $summary[] = "- $value_to_display";
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    // Only collect allowed options if there are actually items to display.
    if ($items->count()) {
      $provider = $items->getFieldDefinition()
        ->getFieldStorageDefinition()
        ->getOptionsProvider('value', $items->getEntity());
      // Flatten the possible options, to support opt groups.
      $options = OptGroup::flattenOptions($provider->getPossibleOptions());
      $options_to_display = $this->getSetting(key: 'options_selected');

      foreach ($items as $delta => $item) {
        if (!($item instanceof FieldItemInterface)) {
          continue;
        }
        $value = $item->get('value')->getValue();
        // Only display the items defined in the formatter settings.
        if (!in_array(needle: $value, haystack: $options_to_display, strict: TRUE)) {
          continue;
        }
        // If the stored value is in the current set of allowed values, display
        // the associated label, otherwise just display the raw value.
        $output = $options[$value] ?? $value;
        $elements[$delta] = [
          '#markup' => $output,
          '#allowed_tags' => FieldFilteredMarkup::allowedTags(),
        ];
      }
    }

    return $elements;
  }

  /**
   * Gets the allowed values for the list field from the definition.
   *
   * @return array
   *   The allowed values list for the field. If the list is not defined, an
   *   empty array is still returned.
   */
  protected function getAllowedValues(): ?array {
    $settings = $this->fieldDefinition->getItemDefinition()->getSettings();
    return $settings['allowed_values'] ?? [];
  }

}
