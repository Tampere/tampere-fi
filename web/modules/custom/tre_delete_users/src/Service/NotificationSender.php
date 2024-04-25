<?php

namespace Drupal\tre_delete_users\Service;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\message\Entity\Message;
use Drupal\message_notify\MessageNotifier;

/**
 * Service class for sending user deletion notification via email.
 */
final class NotificationSender {
  use StringTranslationTrait;

  /**
   * The message_notify.sender service.
   *
   * @var \Drupal\message_notify\MessageNotifier
   */
  private MessageNotifier $notifier;

  /**
   * Message storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  private EntityStorageInterface $messageStorage;

  /**
   * Drupal state.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  private StateInterface $state;

  /**
   * Constructor for the service.
   */
  public function __construct(MessageNotifier $message_notifier, EntityTypeManagerInterface $entity_type_manager, StateInterface $state) {
    $this->notifier = $message_notifier;
    $this->messageStorage = $entity_type_manager->getStorage('message');
    $this->state = $state;
  }

  /**
   * Service function for sending a user deleteion email to recipients.
   *
   * @param string $title
   *   The email's title to send.
   * @param string $body
   *   The email's body to send.
   */
  public function sendUserDeletionNotificationMail($title, $body) {
    $messages_created = [];
    $notification_email_list_text = $this->state->get('tre_delete_users.notification_email_list');

    if ($notification_email_list_text === '') {
      return;
    }

    $notification_email_list = preg_split("/\\r\\n|\\r|\\n/", $notification_email_list_text);
    $message = Message::create(['template' => 'delete_user_message']);
    $message->set('field_delete_user_message_title', $title);
    $message->set('field_delete_user_message_body', ['markup' => $body]);
    $message->save();

    $messages_created[] = $message;

    foreach ($notification_email_list as $email) {
      $this->notifier->send($message, [
        'mail' => $email,
      ]);
    }

    $this->messageStorage->delete($messages_created);
  }

}
