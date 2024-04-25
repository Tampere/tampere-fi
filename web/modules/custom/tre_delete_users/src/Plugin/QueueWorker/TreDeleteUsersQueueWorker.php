<?php

namespace Drupal\tre_delete_users\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;
use Drupal\tre_delete_users\Service\NotificationSender;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Queue worker handling the tre_delete_users_queue queue.
 *
 * @QueueWorker(
 *   id = "tre_delete_users_queue",
 *   title = @Translation("TRE Delete Users Queue"),
 *   cron = {"time" = 240}
 * )
 */
final class TreDeleteUsersQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {
  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The message sender service to use.
   *
   * @var \Drupal\tre_delete_users\Service\NotificationSender
   */
  private NotificationSender $notificationSender;

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
   * @param \Drupal\tre_delete_users\Service\NotificationSender $notificationSender
   *   The notification sender.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, NotificationSender $notificationSender) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->notificationSender = $notificationSender;

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
      $container->get('tre_delete_users.notification_sender')

    );
  }

  /**
   * {@inheritDoc}
   */
  public function processItem($data) {

    $revision_id = $data['revision_id'];
    $original_user_id = $data['original_user_id'];
    $destination_user_id = $data['destination_user_id'];

    $node_storage = $this->entityTypeManager->getStorage('node');
    // @phpstan-ignore-next-line
    $revision = $node_storage->loadRevision($revision_id);

    if ($revision instanceof NodeInterface) {
      foreach ($revision->getTranslationLanguages() as $lang_code => $language) {
        $revision_translation = $revision->getTranslation($lang_code);
        $revision_translation->set('uid', $destination_user_id);
        $revision_translation->setSyncing(TRUE);
        $revision_translation->save();
      }
    }

    $entity_query = $node_storage->getQuery();
    $revisions = $entity_query->allRevisions()->condition('uid', $original_user_id)->accessCheck(TRUE)->execute();
    $nodes = $node_storage->loadMultiple($revisions);

    $original_user = $this->entityTypeManager->getStorage('user')->load($original_user_id);

    if (empty($nodes) && $original_user instanceof UserInterface) {
      $original_fullname = $original_user->getDisplayName();
      $original_user->delete();

      $destination_user = $this->entityTypeManager->getStorage('user')->load($destination_user_id);
      $destination_fullname = $destination_user->getDisplayName();

      $email_title = "Käyttäjä $original_fullname (id: $original_user_id ) poistettu.";
      $email_body = "Käyttäjä $original_fullname (id: $original_user_id ) poistettu. Kaikki käyttäjän sisältö siirretty käyttäjälle $destination_fullname (id: $destination_user_id).";

      $this->notificationSender->sendUserDeletionNotificationMail($email_title, $email_body);

    }

  }

}
