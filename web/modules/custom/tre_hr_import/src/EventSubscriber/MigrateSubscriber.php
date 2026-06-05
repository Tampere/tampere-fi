<?php

namespace Drupal\tre_hr_import\EventSubscriber;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateImportEvent;
use Drupal\migrate\Event\MigratePostRowSaveEvent;
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
    $events[MigrateEvents::POST_ROW_SAVE][] = ['onPostRowSave'];
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
   * 1. Stores the timestamp when the Person is first detected as removed from the CSV source.
   * 2. Unpublishes the node if it's currently published and missing in the source CSV.
   * 3. Deletes the node only if it has been missing from the source for at least one month.
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
    $migration_name = $migration->getBaseId();
    $id_map = $migration->getIdMap();

    if ($migration_name == 'ipaas_import_csv') {
      foreach ($event->getDestinationIds() as $nid) {
        $node_id = (int) $nid['nid'];
        $node = $this->entityTypeManager->getStorage('node')->load($node_id);

        if (!$node instanceof NodeInterface) {
          continue;
        }

        if (!$node->hasField('field_removed_from_source')) {
          $this->loggerFactory->get('tre_hr_import')->warning(
            'Person content @id does not have field_removed_from_source field. Soft-delete skipped.',
            ['@id' => $node_id]
          );

          continue;
        }

        $current_time = \Drupal::time()->getRequestTime();
        $isTranslationPublished = FALSE;
        $removedFromSourceChanged = FALSE;

        // Store the timestamp only once -> when this node is first detected
        // as missing from the CSV source.
        if ($node->get('field_removed_from_source')->isEmpty()) {
          $removed_from_source = $current_time;
          $node->set('field_removed_from_source', $removed_from_source);
          $removedFromSourceChanged = TRUE;

          $this->loggerFactory->get('tre_hr_import')->info(
            'Person content @id is missing from source. Soft-delete waiting period started.',
            ['@id' => $node_id]
          );
        }
        else {
          $removed_from_source = (int) $node->get('field_removed_from_source')->value;
        }

        foreach ($node->getTranslationLanguages() as $langcode => $language) {
          if ($node->hasTranslation($langcode)) {
            $translation = $node->getTranslation($langcode);

            if ($translation instanceof NodeInterface && $translation->isPublished()) {
              $isTranslationPublished = TRUE;
              $translation->setUnpublished();
            }
          }
        }

        if ($isTranslationPublished || $removedFromSourceChanged) {
          $node->save();
        }

        if ($isTranslationPublished) {
          $this->loggerFactory->get('tre_hr_import')->info(
            'Person content @id is now Unpublished',
            ['@id' => $node_id]
          );

          continue;
        }

        $delete_after = strtotime('+1 month', $removed_from_source);

        if ($current_time >= $delete_after) {
          $node->delete();
          $id_map->deleteDestination(['nid' => $node_id]);

          $this->loggerFactory->get('tre_hr_import')->info(
            'Person content @id DELETED after 1 month waiting period',
            ['@id' => $node_id]
          );
        }
        else {
          // Log the "remaining time" before deletion - easy to check when Person will be deleted
          $days_left = max(0, ceil(($delete_after - $current_time) / 86400));

          $this->loggerFactory->get('tre_hr_import')->debug(
            'Person content @id is in soft-delete grace period. @days days left.',
            [
              '@id' => $node_id,
              '@days' => $days_left,
            ]
          );
        }
      }

      // We stop nodes from being deleted by default.
      $event->stopPropagation();
    }
  }

  /**
   * Clears removed-from-source timestamp
   * 
   * removed-from-source timestamp is added when person is removed from source CSV data
   * If person gets readded with same ID - we clear the removed-from-source timestamp value from the node
   * 
   * @param \Drupal\migrate\Event\MigratePostRowSaveEvent $event
   *   The event object.
   */
  public function onPostRowSave(MigratePostRowSaveEvent $event) {
    $migration_name = $event->getMigration()->getBaseId();

    if ($migration_name !== 'ipaas_import_csv') {
      return;
    }

    $destination_ids = $event->getDestinationIdValues();

    $node_id = NULL;

    if (!empty($destination_ids['nid'])) {
      $node_id = (int) $destination_ids['nid'];
    }
    elseif (!empty($destination_ids[0])) {
      $node_id = (int) $destination_ids[0];
    }

    if (empty($node_id)) {
      $this->loggerFactory->get('tre_hr_import')->warning(
        'POST_ROW_SAVE could not resolve node ID. Destination IDs: @ids',
        ['@ids' => print_r($destination_ids, TRUE)]
      );

      return;
    }

    $node = $this->entityTypeManager->getStorage('node')->load($node_id);

    if (!$node instanceof NodeInterface) {
      return;
    }

    if (!$node->hasField('field_removed_from_source')) {
      return;
    }
    // Skip the PostRowSave event if this field is empty - no need to check each node
    // Only the ones that has previously removed from CSV data (has timestamp)
    if ($node->get('field_removed_from_source')->isEmpty()) {
      return;
    }

    $old_value = $node->get('field_removed_from_source')->value;

    $node->set('field_removed_from_source', []);
    $node->save();

    $this->loggerFactory->get('tre_hr_import')->info(
      'Person content @id exists in source again. Removed-from-source timestamp @old_value was cleared.',
      [
        '@id' => $node_id,
        '@old_value' => $old_value,
      ]
    );
  }

}
