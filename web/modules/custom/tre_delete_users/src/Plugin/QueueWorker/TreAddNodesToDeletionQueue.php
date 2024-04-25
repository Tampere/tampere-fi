<?php

namespace Drupal\tre_delete_users\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\tre_delete_users\Service\NotificationSender;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Queue worker handling the tre_add_nodes_to_deletion_queue queue.
 *
 * @QueueWorker(
 *   id = "tre_add_nodes_to_deletion_queue",
 *   title = @Translation("TRE Add Nodes To Deletion Queue"),
 *   cron = {"time" = 240}
 * )
 */
final class TreAddNodesToDeletionQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface {
  use StringTranslationTrait;

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
   * The message sender service to use.
   *
   * @var \Drupal\tre_delete_users\Service\NotificationSender
   */
  private NotificationSender $notificationSender;

  /**
   * Drupal state.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  private StateInterface $state;

  /**
   * Main constructor.
   *
   * @param array $configuration
   *   Configuration array.
   * @param mixed $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Queue\QueueInterface $queue
   *   The queue interface.
   * @param \Drupal\tre_delete_users\Service\NotificationSender $notificationSender
   *   The notification sender.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state interface.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, QueueInterface $queue, NotificationSender $notificationSender, StateInterface $state) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->queue = $queue;
    $this->notificationSender = $notificationSender;
    $this->state = $state;

  }

  /**
   * Used to grab functionality from the container.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container.
   * @param array $configuration
   *   Configuration array.
   * @param mixed $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   *
   * @return static
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('queue')->get('tre_delete_users_queue', TRUE),
      $container->get('tre_delete_users.notification_sender'),
      $container->get('state')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function processItem($data) {

    if (!array_key_exists('user_id', $data)) {
      return;
    }

    $user_id = $data['user_id'];
    $destination_user_id = $data['destination_user_id'];

    $entity_query = $this->entityTypeManager->getStorage('node')->getQuery();

    $marked_for_deletion = $this->state->get('tre_delete_users.user' . $user_id);

    if ($marked_for_deletion) {

      $nids = $entity_query->allRevisions()->condition('uid', $user_id)->accessCheck(TRUE)->execute();

      if (count($nids)) {
        // Iterate through nodes and add them to the deletion queue.
        foreach ($nids as $revision_id => $node_id) {
          $this->queue->createItem([
            'revision_id' => $revision_id,
            'original_user_id' => $user_id,
            'destination_user_id' => $destination_user_id,
          ]);
        }
      }
      else {
        $user = $this->entityTypeManager->getStorage('user')->load($user_id);
        if ($user instanceof UserInterface) {
          $original_fullname = $user->getDisplayName();

          $user->delete();

          $email_title = "Käyttäjä $original_fullname (id: $user_id) poistettu.";
          $email_body = "Käyttäjä $original_fullname (id: $user_id) poistettu. Tällä käyttäjällä ei ollut sisältöä.";

          $this->notificationSender->sendUserDeletionNotificationMail($email_title, $email_body);
        }
      }

      $this->state->set('tre_delete_users.added' . $user_id, TRUE);
    }
    else {
      $this->state->delete('tre_delete_users.added' . $user_id);
    }

  }

}
