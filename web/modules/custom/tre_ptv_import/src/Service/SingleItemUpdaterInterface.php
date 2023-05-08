<?php

namespace Drupal\tre_ptv_import\Service;

use Drupal\node\NodeInterface;

/**
 * Interface for SingleItemUpdater implementations.
 */
interface SingleItemUpdaterInterface {

  const CONTENT_TYPES_TO_MIGRATIONS_MAP = [
    'place_of_business' => [
      'fi' => 'ptv_service_locations',
      'en' => 'ptv_service_locations_en',
    ],
    'ptv_service' => [
      'fi' => 'ptv_services',
      'en' => 'ptv_services_en',
    ],
    'service_channel' => [
      'fi' => 'ptv_service_channels',
      'en' => 'ptv_service_channels_en',
    ],
  ];

  /**
   * Checks whether a node exists in a migration's source data.
   *
   * The migrations to be checked against are listed in
   * ::CONTENT_TYPES_TO_MIGRATIONS_MAP.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to check.
   *
   * @return bool
   *   True if the node is a migrated one, else otherwise.
   *
   * @see self::CONTENT_TYPES_TO_MIGRATIONS_MAP
   */
  public function checkMigrationSource(NodeInterface $node): bool;

  /**
   * Marks a single node for re-import in a queue.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to re-import.
   *
   * @return bool
   *   True on success, False on failure to add the item in the queue.
   */
  public function updateSingleItem(NodeInterface $node): bool;

}
