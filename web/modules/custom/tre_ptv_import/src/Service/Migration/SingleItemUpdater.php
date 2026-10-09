<?php

declare(strict_types=1);

namespace Drupal\tre_ptv_import\Service\Migration;

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
  public function __construct(
    MigrationPluginManagerInterface $migrationPluginManager,
    QueueFactory $queueFactory,
  ) {
    $this->migrationPluginManager = $migrationPluginManager;
    $this->queueFactory = $queueFactory;
  }

  /**
   * {@inheritdoc}
   */
  public function checkMigrationSource(NodeInterface $node): bool {
    if (!isset(self::CONTENT_TYPES_TO_MIGRATIONS_MAP[$node->bundle()][$node->language()->getId()])) {
      return FALSE;
    }

    $migrationMapRow = $this->getMigrationMapData($node);

    return !empty($migrationMapRow);
  }

  /**
   * {@inheritdoc}
   */
  public function updateSingleItem(NodeInterface $node): bool {
    try {
      $migrationMapRow = $this->getMigrationMapData($node);
    }
    catch (PluginException) {
      return FALSE;
    }

    if (empty($migrationMapRow)) {
      return FALSE;
    }

    $langcode = $node->language()->getId();
    $contentType = $node->bundle();
    $sourceId = $migrationMapRow['sourceid1'];

    $queue = $this->queueFactory->get('ptv_api_migrations', TRUE);
    $queueItem = new PtvUpdateQueueItem($langcode);

    switch ($contentType) {
      case 'ptv_service':
        $queueItem->setServices([$sourceId]);
        break;

      case 'place_of_business':
        $queueItem->setServiceLocations([$sourceId]);
        break;

      case 'service_channel':
        $queueItem->setServiceChannels([$sourceId]);
        break;
    }

    return $queue->createItem($queueItem) !== FALSE;
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
    $contentType = $node->bundle();
    $migrationId = self::CONTENT_TYPES_TO_MIGRATIONS_MAP[$contentType][$langcode];

    /** @var \Drupal\migrate\Plugin\MigrationInterface $migration */
    $migration = $this->migrationPluginManager->createInstance($migrationId);

    $idMap = $migration->getIdMap();
    $mapRow = $idMap->getRowByDestination(['nid' => $node->id()]);

    return empty($mapRow) ? NULL : $mapRow;
  }

}