<?php

namespace Drupal\tre_ptv_import\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\tre_ptv_import\Service\SingleItemUpdaterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form class for requesting immediate updates for PTV nodes.
 */
final class PtvRefreshForm extends FormBase {

  /**
   * The Single Item Updater service.
   *
   * @var \Drupal\tre_ptv_import\Service\SingleItemUpdaterInterface
   */
  private SingleItemUpdaterInterface $updater;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->updater = $container->get('tre_ptv_import.single_item_updater');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $node = $form_state->getBuildInfo()['args'][0];
    if (!($node instanceof NodeInterface)) {
      $this->messenger()->addError($this->t("Unable to update data from PTV API because of missing source data. Please retry."));
      return;
    }

    if ($this->updater->updateSingleItem($node)) {
      $this->messenger()->addStatus($this->t("Data update from PTV requested successfully."));
      $form_state->setRedirect('entity.node.edit_form', ['node' => $node->id()]);
    }
    else {
      $this->messenger()->addError($this->t("Data update request from PTV failed. Please retry."));
    }

  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'tre_ptv_import_refresh';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $node = NULL) {
    if (!($node instanceof NodeInterface)) {
      return [];
    }

    $build = [
      'question' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t("Are you sure you want to update the data for %node_label from the PTV API?", ['%node_label' => $node->label()]),
      ],
      // The actions section has been copied and adapted from
      // \Drupal\Core\Entity\ContentEntityConfirmFormBase.
      'actions' => [
        'submit' => [
          '#type' => 'submit',
          '#value' => $this->t('Refresh', [], ['context' => 'PTV Refresh']),
          '#submit' => [
            [$this, 'submitForm'],
          ],
        ],
        'cancel' => [
          '#type' => 'link',
          '#title' => $this->t('Cancel'),
          '#attributes' => ['class' => ['button']],
          '#url' => Url::fromRoute('entity.node.canonical', ['node' => $node->id()]),
          '#cache' => [
            'contexts' => [
              'url.query_args:destination',
            ],
          ],
        ],
      ],
    ];

    return $build;
  }

}
