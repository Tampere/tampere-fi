<?php

namespace Drupal\tre_ptv_import\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateImportEvent;
use Drupal\node\NodeInterface;
use Drupal\node\NodeStorageInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Handles removal of map_point nodes that were imported previously.
 */
class MigrateImportPrepareSubscriber implements EventSubscriberInterface {

  /**
   * The node storage.
   *
   * @var \Drupal\node\NodeStorageInterface
   */
  private NodeStorageInterface $nodeStorage;

  /**
   * Class constructor.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->nodeStorage = $entityTypeManager->getStorage('node');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[MigrateEvents::POST_IMPORT][] = 'onPostImport';

    return $events;
  }

  /**
   * Removes stale, orphan map_point nodes.
   *
   * @param \Drupal\migrate\Event\MigrateImportEvent $event
   *   The migrate import event subscribed to.
   */
  public function onPostImport(MigrateImportEvent $event): void {
    $migration = $event->getMigration();
    if ($migration->id() !== 'ptv_service_locations') {
      // Only act after imports of the ptv_service_locations migration.
      return;
    }

    $storage = $this->nodeStorage;

    // First, find out which map_point nodes are referred to by the current
    // place_of_business nodes.
    $placesOfBusiness = $storage->loadByProperties(['type' => 'place_of_business']);
    $relatedMapPointNids = [];

    /** @var \Drupal\node\NodeInterface $pobNode */
    foreach ($placesOfBusiness as $pobNode) {
      foreach ($pobNode->get('field_addresses') as $fieldValue) {
        // @phpstan-ignore-next-line
        $relatedMapPointNids[$fieldValue->target_id] = $fieldValue->target_id;
      }
    }

    // Construct the query for map points to remove.
    $mapPointQuery = $storage->getQuery()->accessCheck(FALSE);
    $mapPointQuery->condition('type', 'map_point');

    // Imported map_points are written by the anonymous user.
    $mapPointQuery->condition('uid', 0);
    $orCondition = $mapPointQuery->orConditionGroup();

    // Include all map_point nodes that don't have a value in
    // field_address_hash. These are from the old days before the field was
    // introduced. Note that we're also requiring for the uid on the node to be
    // 0, meaning that any nodes created by actual users are left alone.
    $orCondition->notExists('field_address_hash');

    // Also leave alone any nodes that are currently used in place_of_business
    // nodes.
    $orCondition->condition('nid', $relatedMapPointNids, 'NOT IN');
    $mapPointQuery->condition($orCondition);

    $mapPointsToDeleteNids = $mapPointQuery->execute();
    $nidChunks = array_chunk($mapPointsToDeleteNids, 50);
    $mapPointFilter = static fn (mixed $node): bool => $node instanceof NodeInterface
      && $node->bundle() === 'map_point';

    foreach ($nidChunks as $chunk) {
      // Prevent accidents if $chunk happens to be NULL for any reason.
      if (empty($chunk)) {
        continue;
      }

      $nodesToDelete = $storage->loadMultiple($chunk);
      $mapPointsToDelete = array_filter($nodesToDelete, $mapPointFilter);
      $storage->delete($mapPointsToDelete);
    }
  }

}