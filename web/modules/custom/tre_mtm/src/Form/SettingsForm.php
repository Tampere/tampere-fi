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
      '#type' => 'checkbox',
      '#maxlength' => 20,
      '#required' => FALSE,
      '#size' => 15,
      '#default_value' => $config->get('admin-pages'),
    ];
    $form['general']['matomo-tag'] = [
      '#title' => $this->t('Container URL'),
      '#default_value' => $config->get('matomo-tag'),
      '#maxlength' => 150,
      '#size' => 80,
      '#type' => 'textfield',
      '#description' => $this->t(
        "You can add site to Matomo Tag Manager and add the CDN source here from the script.",
      ),
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
      ->set('matomo-tag', $form_state->getValue('matomo-tag'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
