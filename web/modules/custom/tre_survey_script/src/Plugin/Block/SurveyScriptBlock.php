<?php

namespace Drupal\tre_survey_script\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a 'SurveryScriptBlock' to enable search in page header.
 */
#[Block(
  id: "survey_script_block",
  admin_label: new TranslatableMarkup("Survey script block"),
)]
class SurveyScriptBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'survey_url' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['survey_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Survey URL'),
      '#description' => $this->t('Link to the survey form. The popup is not shown if this is empty.'),
      '#default_value' => $this->configuration['survey_url'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['survey_url'] = trim($form_state->getValue('survey_url'));
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#markup' => '',
    ];
  }

}
