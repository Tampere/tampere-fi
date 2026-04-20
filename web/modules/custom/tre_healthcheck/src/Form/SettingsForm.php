<?php

namespace Drupal\tre_healthcheck\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure healthcheck ping reply settings.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'tre_healthcheck_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['tre_healthcheck.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('tre_healthcheck.settings');

    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable healthcheck endpoint'),
      '#description' => $this->t('When enabled, the /healthcheck endpoint will respond to authorized requests.'),
      '#default_value' => $config->get('enabled') ?? TRUE,
    ];

    $tokens = $config->get('ping_auth_tokens') ?? [];
    $tokens_text = is_array($tokens) ? implode("\n", $tokens) : '';

    $form['ping_auth_tokens'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Access tokens'),
      '#description' => $this->t('Enter one access token per line. These tokens are required in the token query parameter to access the healthcheck endpoint.'),
      '#default_value' => $tokens_text,
      '#rows' => 10,
      '#states' => [
        'visible' => [
          ':input[name="enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $enabled = $form_state->getValue('enabled');
    $tokens_text = trim($form_state->getValue('ping_auth_tokens'));

    // Check if tokens will exist after submission.
    // This includes tokens from the textarea OR tokens already set via settings.php.
    if ($enabled) {
      $will_have_tokens = !empty($tokens_text);
        
      // If textarea is empty, check if tokens exist in config (e.g. from settings.php).
      if (!$will_have_tokens) {
        $existing_tokens = $this->config('tre_healthcheck.settings')->get('ping_auth_tokens') ?? [];
        $will_have_tokens = !empty(array_filter($existing_tokens));
      }

      if (!$will_have_tokens) {
        $form_state->setErrorByName('ping_auth_tokens', $this->t('At least one access token is required when the healthcheck endpoint is enabled. Tokens can be provided via this form or configured in settings.php.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $tokens_text = trim($form_state->getValue('ping_auth_tokens'));
    $tokens = [];

    if (!empty($tokens_text)) {
      // Split by newlines, trim each token, and filter out empty lines.
      $tokens = array_values(
        array_filter(
          array_map('trim', explode("\n", $tokens_text))
        )
      );
    }

    $this->config('tre_healthcheck.settings')
      ->set('enabled', $form_state->getValue('enabled'))
      ->set('ping_auth_tokens', $tokens)
      ->save();

    parent::submitForm($form, $form_state);
  }

}
