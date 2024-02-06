<?php

namespace Drupal\tre_news_email_release\Service;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\group\Entity\GroupInterface;
use Drupal\message\Entity\Message;
use Drupal\message_notify\MessageNotifier;
use Drupal\node\NodeInterface;
use Drupal\tre_jsonapi_custom\EntityRendererInterface;

/**
 * Service class for sending news_item email using message_notify.
 */
final class NewsSender {

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
   * TRE custom entity renderer service.
   *
   * @var \Drupal\tre_jsonapi_custom\EntityRendererInterface
   */
  private EntityRendererInterface $entityRenderer;

  /**
   * Constructor for the service.
   */
  public function __construct(MessageNotifier $message_notifier, EntityTypeManagerInterface $entity_type_manager, EntityRendererInterface $entity_renderer) {
    $this->notifier = $message_notifier;
    $this->messageStorage = $entity_type_manager->getStorage('message');
    $this->entityRenderer = $entity_renderer;
  }

  /**
   * Service function for sending a news_item node by email to recipients.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to send.
   * @param \Drupal\paragraphs\ParagraphInterface[] $delivery_lists
   *   The lists containing metadata and the recipients.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\message_notify\Exception\MessageNotifyException
   */
  public function sendMailForNewsNode(NodeInterface $node, array $delivery_lists) {
    // Create a message with node author as message creator.
    $messages_created = [];

    foreach ($delivery_lists as $list) {
      $message = Message::create(['template' => 'news_item_to_media']);
      $news_item_title = $this->t('News release: @label', ['@label' => $node->label()], ['context' => 'Tampere.fi news email releases']);
      $message->set('field_news_item_title', $news_item_title);

      $node_url = $node->toUrl('canonical', ['absolute' => TRUE])->toString();
      // @todo Add and acquire here translation of configuration.
      $link_defaults = $message->get('field_link_to_content')->getFieldDefinition()->getDefaultValue($node);
      $first_default_link = current($link_defaults);
      $first_default_link['uri'] = $node_url;
      $message->set('field_link_to_content', $first_default_link);

      $message->set('field_news_markup', ['markup' => $this->entityRenderer->renderEntity($node, 'news_media_delivery')]);

      $message->save();
      $messages_created[] = $message;

      $address_lists = $list->get('field_mailing_list_group')->referencedEntities();
      $address_list = reset($address_lists);

      if ($address_list instanceof GroupInterface && $address_list->hasField('field_emails')) {
        /** @var \Drupal\Core\Field\FieldItemInterface $email_value */
        foreach ($address_list->get('field_emails') as $email_value) {
          $mail = $email_value->getString();
          $this->notifier->send($message, [
            'mail' => $mail,
          ]);
        }
      }
    }

    $this->messageStorage->delete($messages_created);
  }

}
