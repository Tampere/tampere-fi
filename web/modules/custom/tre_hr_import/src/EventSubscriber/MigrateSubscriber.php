<?php

namespace Drupal\tre_hr_import\EventSubscriber;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateImportEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber for Migrate events.
 */
class MigrateSubscriber implements EventSubscriberInterface {

  /**
   * The current database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $db;

  /**
   * The logger channel factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    $events[MigrateEvents::POST_IMPORT][] = ['onMigratePostImport'];
    return $events;
  }

  /**
   * Constructs a new MigrateSubscriber.
   *
   * @param \Drupal\Core\Database\Connection $db
   *   The current database connection.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $factory
   *   The logger channel factory.
   */
  public function __construct(Connection $db, LoggerChannelFactoryInterface $factory) {
    $this->db = $db;
    $this->loggerFactory = $factory;
  }

  /**
   * Run after the person migration is done.
   *
   * @param \Drupal\migrate\Event\MigrateImportEvent $event
   *   The import event object.
   */
  public function onMigratePostImport(MigrateImportEvent $event) {
    $this->db->truncate('hr_import_temp_data')->execute();

    $this->loggerFactory->get('tre_hr_import')->info("Database table 'hr_import_temp_data' cleared.");
  }

}
