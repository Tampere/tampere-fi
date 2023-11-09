<?php

namespace Drupal\tre_ptv_import\Service;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Queue\QueueFactory;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\tre_ptv_import\PtvUpdateQueueItem;

/**
 * Single item update and checking functionality.
 */
final class SingleItemUpdater implements SingleItemUpdaterInterface {

  /**
   * Migration plugin manager service.
   *
   * @var \Drupal\migrate\Plugin\MigrationPluginManagerInterface
   */
  private MigrationPluginManagerInterface $migrationPluginManager;

  /**
   * Queue factory service.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  private QueueFactory $queueFactory;

  /**
   * Class constructor.
   */
  public function __construct(MigrationPluginManagerInterface $migration_plugin_manager, QueueFactory $queue_factory) {
    $this->migrationPluginManager = $migration_plugin_manager;
    $this->queueFactory = $queue_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function checkMigrationSource(NodeInterface $node): bool {
    if (!isset(self::CONTENT_TYPES_TO_MIGRATIONS_MAP[$node->bundle()][$node->language()->getId()])) {
      return FALSE;
    }

    $source_id = $this->getMigrationMapData($node);

    return !empty($source_id);
  }

  /**
   * {@inheritdoc}
   */
  public function updateSingleItem(NodeInterface $node): bool {
    try {
      $migrate_map_row = $this->getMigrationMapData($node);
    }
    catch (PluginException $e) {
      return FALSE;
    }

    if (empty($migrate_map_row)) {
      return FALSE;
    }

    $langcode = $node->language()->getId();
    $content_type = $node->bundle();
    $source_id = $migrate_map_row['sourceid1'];

    $queue = $this->queueFactory->get('ptv_api_migrations', TRUE);
    $queue_item = new PtvUpdateQueueItem($langcode);

    switch ($content_type) {
      case 'ptv_service':
        $queue_item->setServices([$source_id]);
        break;

      case 'place_of_business':
        $queue_item->setServiceLocations([$source_id]);
        break;

      case 'service_channel':
        $queue_item->setServiceChannels([$source_id]);
        break;
    }

    return $queue->createItem($queue_item);
  }

  /**
   * Fetches the migration map data for a node.
   *
   * The migration is based on the node's content type and language.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to check the map data for.
   *
   * @return array|null
   *   Returns the data from the migration map or NULL if none exists.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  private function getMigrationMapData(NodeInterface $node): ?array {
    $langcode = $node->language()->getId();
    $content_type = $node->bundle();
    $migration_id = self::CONTENT_TYPES_TO_MIGRATIONS_MAP[$content_type][$langcode];

    /** @var \Drupal\migrate\Plugin\MigrationInterface $migration */
    $migration = $this->migrationPluginManager->createInstance($migration_id);

    $id_map = $migration->getIdMap();
    $map_row = $id_map->getRowByDestination(['nid' => $node->id()]);

    return empty($map_row) ? NULL : $map_row;
  }

}
