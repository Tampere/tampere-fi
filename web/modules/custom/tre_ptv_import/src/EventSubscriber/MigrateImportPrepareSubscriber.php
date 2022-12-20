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
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->nodeStorage = $entity_type_manager->getStorage('node');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[MigrateEvents::POST_IMPORT][] = 'onPostImport';

    return $events;
  }

  /**
   * Removes stale, orphan map_point nodes.
   *
   * @param \Drupal\migrate\Event\MigrateImportEvent $event
   *   The migrate import event subscribed to.
   */
  public function onPostImport(MigrateImportEvent $event) {
    $migration = $event->getMigration();
    if ($migration->id() !== 'ptv_service_locations') {
      // Only act after imports of the ptv_service_locations migration.
      return;
    }

    $storage = $this->nodeStorage;

    // First, find out which map_point nodes are referred to by the current
    // place_of_business nodes.
    $places_of_business = $storage->loadByProperties(['type' => 'place_of_business']);
    $related_map_point_nids = [];
    /** @var \Drupal\node\NodeInterface $pob_node */
    foreach ($places_of_business as $pob_node) {
      foreach ($pob_node->get('field_addresses') as $field_value) {
        // @phpstan-ignore-next-line
        $related_map_point_nids[$field_value->target_id] = $field_value->target_id;
      }
    }

    // Construct the query for map points to remove.
    $map_point_query = $storage->getQuery()->accessCheck(FALSE);
    $map_point_query->condition('type', 'map_point');

    // Imported map_points are written by the anonymous user.
    $map_point_query->condition('uid', 0);
    $or_condition = $map_point_query->orConditionGroup();

    // Include all map_point nodes that don't have a value in
    // field_address_hash. These are from the old days before the field was
    // introduced. Note that we're also requiring for the uid on the node to be
    // 0, meaning that any nodes created by actual users are left alone.
    $or_condition->notExists('field_address_hash');
    // Also leave alone any nodes that are currently used in place_of_business
    // nodes.
    $or_condition->condition('nid', $related_map_point_nids, 'NOT IN');
    $map_point_query->condition($or_condition);

    $map_points_to_delete_nids = $map_point_query->execute();
    $nid_chunks = array_chunk($map_points_to_delete_nids, 50);
    $map_point_filter = function ($node) {
      return ($node instanceof NodeInterface) && $node->bundle() === 'map_point';
    };

    foreach ($nid_chunks as $chunk) {
      $nodes_to_delete = $storage->loadMultiple($chunk);
      $map_points_to_delete = array_filter($nodes_to_delete, $map_point_filter);
      $storage->delete($map_points_to_delete);
    }
  }

}
