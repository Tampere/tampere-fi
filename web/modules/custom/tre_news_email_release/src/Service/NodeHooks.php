<?php

namespace Drupal\tre_news_email_release\Service;

use Drupal\tre_node_logger\Service\NodeLogger;
use Drupal\Core\State\StateInterface;
use Drupal\node\NodeInterface;
use Drupal\tre_preprocess_utility_functions\Utils\HelperFunctionsInterface;
use Psr\Log\LoggerInterface;
use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Service to implement node hooks utilising other services.
 */
final class NodeHooks {

  const STATE_KEY = 'tre_news_email_release_data';

  /**
   * The helper functions.
   *
   * @var \Drupal\tre_preprocess_utility_functions\Utils\HelperFunctionsInterface
   */
  private HelperFunctionsInterface $helperFunctions;

  /**
   * Drupal state.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  private StateInterface $state;

  /**
   * The message sender service to use.
   *
   * @var \Drupal\tre_news_email_release\Service\NewsSender
   */
  private NewsSender $sender;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  private LoggerInterface $logger;

  /**
   * A node logger instance.
   *
   * @var Drupal\tre_node_logger\Service\NodeLogger
   */
  private NodeLogger $node_logger;

  /**
   * The constructor.
   */
  public function __construct(HelperFunctionsInterface $helper_functions, StateInterface $state, NewsSender $sender, LoggerInterface $logger, NodeLogger $node_logger) {
    $this->helperFunctions = $helper_functions;
    $this->state = $state;
    $this->sender = $sender;
    $this->logger = $logger;
    $this->node_logger = $node_logger;
  }

  /**
   * Checks the value of field_send_to_media to prepare sending emails.
   *
   * In case the field value evaluates to true, the value from
   * field_mailing_list_information is saved in Drupal's state to signal that
   * to the update hook that the mail should be sent.
   *
   * The field_send_media value is always reset to 'false' so that the checkbox
   * checked again to initiate a new email.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node about to be saved.
   *
   * @see hook_ENTITY_TYPE_presave()
   */
  public function preSave(NodeInterface $node) {
    if ($node->bundle() !== 'news_item') {
      return;
    }

    if ($this->helperFunctions->getFieldValueString($node, 'field_send_to_media') !== HelperFunctionsInterface::BOOLEAN_FIELD_TRUE) {
      return;
    }

    if (!$node->isPublished()) {
      return;
    }

    if ($node->get('field_mailing_list_information')->isEmpty()) {
      return;
    }

    $node->set('field_send_to_media', '0');
    $node_media_send_data = $this->state->get(self::STATE_KEY, []);
    // Add the information on the node revision so that the email sending can be
    // bound to this one save event instance.
    $new_send_data = [
      'time_added' => $node->getRevisionCreationTime(),
      'delivery_lists' => $node->get('field_mailing_list_information'),
    ];
    $node_media_send_data[] = $new_send_data;
    $this->state->set(self::STATE_KEY, $node_media_send_data);
  }

  /**
   * Sends mail to mailing lists for an updated, published news_item node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node that has been updated.
   *
   * @see hook_ENTITY_TYPE_update()
   */
  public function update(NodeInterface $node) {
    if ($node->bundle() !== 'news_item' || !$node->isPublished()) {
      return;
    }
    $node_media_send_data = $this->state->get(self::STATE_KEY, []);
    $node_updated_time = $node->getRevisionCreationTime();
    $log_message_context = [
      '@nid' => $node->id(),
    ];

    foreach ($node_media_send_data as $key => $node_emails_paragraph_list_with_send_time) {
      /** @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface $node_emails_paragraph_list */
      $node_emails_paragraph_list = $node_emails_paragraph_list_with_send_time['delivery_lists'];
      if ($node_emails_paragraph_list->getEntity()->id() !== $node->id()) {
        continue;
      }

      // Skip processing altogether and clear the item from queue if the
      // timestamps don't match.
      $time_added = $node_emails_paragraph_list_with_send_time['time_added'];

      if ($time_added !== $node_updated_time) {
        unset($node_media_send_data[$key]);
        $this->logger->warning('Tried to send stale emails for node @nid', $log_message_context);
        continue;
      }
      /** @var \Drupal\paragraphs\ParagraphInterface[] $mail_paragraphs */
      $mail_paragraphs = $node_emails_paragraph_list->referencedEntities();
      try {
        $this->sender->sendMailForNewsNode($node, $mail_paragraphs);

        // Log a message saying that the email is sent
        $message = "The email for this news item has been sent";
        // $node_logger = new NodeLogger($message, $node->id());
        // $node_logger->saveLog();
        $this->node_logger->saveLog($message, $node->id());

      }
      catch (\Exception $e) {
        $this->logger->error('Failed to send an email for node @nid', $log_message_context);
        watchdog_exception('tre_news_email_release', $e);
      }
      unset($node_media_send_data[$key]);
    }

    $this->state->set(self::STATE_KEY, $node_media_send_data);
  }

}
