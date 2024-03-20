<?php

namespace Drupal\tre_mtm\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Implements the form controller.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'tre_mtm_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['tre_mtm.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('tre_mtm.settings');
    $form['general'] = [
      '#type' => 'details',
      '#title' => $this->t('General settings'),
      '#open' => TRUE,
    ];
    $form['general']['enable'] = [
      '#title' => $this->t('Enable Matomo Tag Manager'),
      '#description' => $this->t('Add MTM script on website pages'),
      '#type' => 'checkbox',
      '#maxlength' => 20,
      '#required' => FALSE,
      '#size' => 15,
      '#default_value' => $config->get('enable'),
    ];
    $form['general']['admin-pages'] = [
      '#title' => $this->t('Display Matomo Tag Manager on admin pages (if MTM enabled)'),
      '#description' => $this->t('Add MTM script on admin pages'),
      '#states' => [
        'visible' => [
          ':input[name="enable"]' => ['checked' => TRUE],
        ],
      ],
      '#type' => 'checkbox',
      '#maxlength' => 20,
      '#required' => FALSE,
      '#size' => 15,
      '#default_value' => $config->get('admin-pages'),
    ];
    $form['general']['container_urls'] = [
      '#title' => $this->t('Container URL(s)'),
      '#states' => [
        'visible' => [
          ':input[name="enable"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="enable"]' => ['checked' => TRUE],
        ],
      ],
      '#type' => 'textarea',
      '#description' => $this->t('The container URLs, one per line.'),
      '#default_value' => implode("\n", $config->get('container_urls')),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * Implements a form submit handler.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('tre_mtm.settings');
    $config
      ->set('enable', $form_state->getValue('enable'))
      ->set('admin-pages', $form_state->getValue('admin-pages'))
      ->set('container_urls', self::extractFormValue($form_state->getValue('container_urls')))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Extract the array from the textarea input.
   *
   * @param string $input
   *   The form input.
   *
   * @return string[]
   *   The cleaned up values.
   */
  private static function extractFormValue(string $input): array {
    return array_values(
      array_filter(
        array_map(fn($l) => trim($l),
          explode("\n", str_replace("\r", "\n", $input)))
      )
    );
  }

}
