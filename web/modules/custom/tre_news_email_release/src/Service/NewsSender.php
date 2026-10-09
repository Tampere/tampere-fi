<?php

namespace Drupal\tre_news_email_release\Service;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
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
   */
  private MessageNotifier $notifier;

  /**
   * Message storage.
   */
  private EntityStorageInterface $messageStorage;

  /**
   * TRE custom entity renderer service.
   */
  private EntityRendererInterface $entityRenderer;

  /**
   * The language manager.
   */
  private LanguageManagerInterface $languageManager;

  /**
   * Constructor for the service.
   */
  public function __construct(
    MessageNotifier $message_notifier,
    EntityTypeManagerInterface $entity_type_manager,
    EntityRendererInterface $entity_renderer,
    LanguageManagerInterface $language_manager
  ) {
    $this->notifier = $message_notifier;
    $this->messageStorage = $entity_type_manager->getStorage('message');
    $this->entityRenderer = $entity_renderer;
    $this->languageManager = $language_manager;
  }

  /**
   * Service function for sending a news_item node by email to recipients.
   */
  public function sendMailForNewsNode(NodeInterface $node, array $delivery_lists) {
    // Determine the language of the news item.
    $langcode = $node->language()->getId();
    $target_language = $this->languageManager->getLanguage($langcode);

    // Ensure we use the translated version of the node for title and rendering.
    if ($node->hasTranslation($langcode)) {
      $node = $node->getTranslation($langcode);
    }

    $messages_created = [];

    try {
      foreach ($delivery_lists as $list) {
        /** @var \Drupal\message\MessageInterface $message */
        $message = Message::create([
          'template' => 'news_item_to_media',
          'langcode' => $langcode,
        ]);

        // Translate the subject line.
        $subject = $this->t('News release: @label', 
          ['@label' => $node->label()], 
          ['context' => 'Tampere.fi news email releases', 'langcode' => $langcode]
        );
        $message->set('field_news_item_title', $subject);

        // Build the absolute URL.
        $node_url = $node->toUrl('canonical', [
          'absolute' => TRUE, 
          'language' => $target_language
        ])->toString();

        // Translate the link title.
        $link_title = $this->t('Read the news release on Tampere.fi', [], [
          'context' => 'Tampere.fi news email releases',
          'langcode' => $langcode,
        ]);

        $message->set('field_link_to_content', [
          'uri' => $node_url,
          'title' => $link_title,
        ]);

        // Signal the current rendering langcode so preprocess hooks can read
        // it. We cannot rely on #object->language() there because
        // non-translatable fields always set #object to the default translation.
        $rendering_langcode = &drupal_static('tre_news_email_release_rendering_langcode');
        $rendering_langcode = $langcode;

        // Render the node content in the correct language.
        $message->set('field_news_markup', [
          'markup' => $this->entityRenderer->renderEntity($node, 'news_media_delivery', $langcode),
        ]);

        $rendering_langcode = NULL;

        $message->save();
        $messages_created[] = $message;

        $address_lists = $list->get('field_mailing_list_group')->referencedEntities();
        $address_list = reset($address_lists);

        if ($address_list instanceof GroupInterface && $address_list->hasField('field_emails')) {
          foreach ($address_list->get('field_emails') as $email_value) {
            $this->notifier->send($message, ['mail' => $email_value->getString()]);
          }
        }
      }
    }
    catch (\Exception $e) {
      throw $e;
    }
    finally {
      // Invalidate the node's render cache so other renders later in the same
      // cron run get a fresh build.
      Cache::invalidateTags(['node:' . $node->id()]);

      if (!empty($messages_created)) {
        $this->messageStorage->delete($messages_created);
      }
    }
  }

}
