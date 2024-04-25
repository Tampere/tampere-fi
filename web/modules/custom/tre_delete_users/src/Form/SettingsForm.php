<?php

namespace Drupal\tre_delete_users\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implements the form controller.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The queue object.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $queue;

  /**
   * Drupal state.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  private StateInterface $state;

  /**
   * The collator in Fi definition.
   *
   * @var object
   */
  protected $collatorFi;

  /**
   * Main constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Queue\QueueInterface $queue
   *   The queue interface.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state interface.
   */
  final public function __construct(EntityTypeManagerInterface $entity_type_manager, QueueInterface $queue, StateInterface $state) {
    $this->entityTypeManager = $entity_type_manager;
    $this->queue = $queue;
    $this->state = $state;
    $this->collatorFi = new \Collator('fi_FI');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('queue')->get('tre_add_nodes_to_deletion_queue', TRUE),
      $container->get('state'),

    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'tre_delete_users_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['tre_delete_users.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $users = $this->entityTypeManager->getStorage('user')->loadMultiple();
    $options = [];

    usort($users, function ($item1, $item2) {
      return $this->collatorFi->compare($item1->getDisplayName(), $item2->getDisplayName());
    });

    // Create a user list with deletion checkboxes.
    foreach ($users as $user) {
      // Skip if the user id is zero which means it's the Anonymous user.
      if ($user->id()) {
        $label = $user->getDisplayName();
        $options[$user->id()] = $label;

        if ($this->state->get('tre_delete_users.added' . $user->id())) {
          $label .= $this->t("&nbsp;(This user has been already added to the deletion queue)");
        }
        else {
          if ($this->state->get('tre_delete_users.user' . $user->id())) {
            $label .= $this->t("&nbsp;(You can still uncheck the user from getting deleted)");
          }
        }

        $form['user_list']['user' . $user->id()] = [
          '#type' => 'checkbox',
          '#title' => $label,
          '#default_value' => boolval($this->state->get('tre_delete_users.user' . $user->id())),
          '#required' => FALSE,
          '#disabled' => boolval($this->state->get('tre_delete_users.added' . $user->id())),
        ];
      }
    }

    $form['destination_user_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Select the destination user'),
      '#default_value' => $this->state->get('tre_delete_users.destination_user_id'),
      '#required' => TRUE,
      '#options' => $options,
    ];

    $form['actions'] = ['#type' => 'actions'];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#button_type' => 'primary',
      '#attributes' => [
        'style' => 'background-color: #ff0000; color: #ffffff;',
      ],
      '#value' => $this->t('Delete Selected User(s)'),
    ];

    $form['notification_email_list'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Notify the following email(s):'),
      '#default_value' => $this->state->get('tre_delete_users.notification_email_list'),
      '#description' => $this->t("Put one email address in each line."),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $destination_user_id = $form_state->getValue('destination_user_id');

    $users = $this->entityTypeManager->getStorage('user')->loadMultiple();
    foreach ($users as $user) {
      $marked_for_deletion = $form_state->getValue('user' . $user->id());

      if ($marked_for_deletion) {
        $this->state->set('tre_delete_users.user' . $user->id(), TRUE);
      }
      else {
        $this->state->delete('tre_delete_users.user' . $user->id());
      }

      if ($marked_for_deletion) {
        $this->queue->createItem([
          'user_id' => $user->id(),
          'destination_user_id' => $destination_user_id,
        ]);
      }
      else {
        $this->state->delete('tre_delete_users.added' . $user->id());
      }

    }

    $this->state->set('tre_delete_users.destination_user_id', $form_state->getValue('destination_user_id'));
    $this->state->set('tre_delete_users.notification_email_list', $form_state->getValue('notification_email_list'));

    parent::submitForm($form, $form_state);
  }

}
