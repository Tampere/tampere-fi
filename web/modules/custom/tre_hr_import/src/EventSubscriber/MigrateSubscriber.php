<?php

namespace Drupal\tre_hr_import\EventSubscriber;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateImportEvent;
use Drupal\node\NodeInterface;
use Drush\Drupal\Migrate\MigrateMissingSourceRowsEvent;
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
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    $events[MigrateEvents::POST_IMPORT][] = ['onMigratePostImport'];
    $events[MigrateMissingSourceRowsEvent::class][] = ['onMissingSourceRows'];

    return $events;
  }

  /**
   * Constructs a new MigrateSubscriber.
   *
   * @param \Drupal\Core\Database\Connection $db
   *   The current database connection.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $factory
   *   The logger channel factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(Connection $db, LoggerChannelFactoryInterface $factory, EntityTypeManagerInterface $entity_type_manager) {
    $this->db = $db;
    $this->loggerFactory = $factory;
    $this->entityTypeManager = $entity_type_manager;
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

  /**
   * Reacts on detecting a list of missing source rows after an import.
   *
   * Soft deletes Person nodes when migration notices removed Persons in CSV source:
   * 1. Unpublishes the node if it's currently published (and missing in the source CSV)
   * 2. Deletes the node only if it's already unpublished (and missing in the source CSV)
   *
   * @param \Drush\Drupal\Migrate\MigrateMissingSourceRowsEvent $event
   *   The event object.
   */
  public function onMissingSourceRows(MigrateMissingSourceRowsEvent $event) {
    if (!$event->getDestinationIds()) {
      return;
    }

    // If either there is no argument or --delete flag is not set in
    // the Drush command, stop here.
    if (!isset($_SERVER['argv']) || !in_array('--delete', $_SERVER['argv'])) {
      return;
    }

    $migration = $event->getMigration();
    $migration_name = $event->getMigration()->getBaseId();
    $id_map = $migration->getIdMap();

    if ($migration_name == 'ipaas_import_csv') {
      foreach ($event->getDestinationIds() as $nid) {
        $node = $this->entityTypeManager->getStorage('node')->load($nid['nid']);

        // If published content - we unpublish it first
        // If already unpublished content - we delete it
        $isTranslationPublished = FALSE;
        if ($node instanceof NodeInterface) {
          foreach ($node->getTranslationLanguages() as $langcode => $language) {
            if ($node->hasTranslation($langcode)) {
              $translation = $node->getTranslation($langcode);
              if ($translation instanceof NodeInterface) {
                if ($translation->isPublished()) {
                  $isTranslationPublished = TRUE;
                  $translation->setUnpublished();
                }
              }
            }
          }
          // If isTranslationPublished is true - it means that we have unpublished the content in previous foreach()
          // -> Save status update for the content
          if ($isTranslationPublished) {
            $node->save();
            $this->loggerFactory->get('tre_hr_import')->info("Person content with node ID: @id is now Unpublished and will be removed in next migration", ['@id' => $nid['nid']]);
          }
          // Else - means its already unpublished content -> Delete the node
          else {
            $node->delete();
            $id_map->deleteDestination(['nid' => $nid['nid']]);
            $this->loggerFactory->get('tre_hr_import')->info("Person content with node ID: @id is now Deleted", ['@id' => $nid['nid']]);
          }
        }
      }

      // We stop nodes from being deleted by default.
      $event->stopPropagation();
    }
  }

}
