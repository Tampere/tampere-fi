<?php

namespace Drupal\tre_ptv_import\Plugin\QueueWorker;

use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\MigrationPluginManager;
use Drupal\migrate_tools\MigrateExecutable;
use Drupal\tre_ptv_import\PtvUpdateQueueItem;
use Drupal\tre_ptv_import\Service\SingleItemUpdaterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Queue worker handling the ptv_node_migrations queue.
 *
 * @QueueWorker(
 *   id = "ptv_node_migrations",
 *   title = @Translation("PTV Node Migrations Queue"),
 *   cron = {"time" = 240}
 * )
 */
final class PtvNodeQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The migration plugin manager service.
   *
   * @var \Drupal\migrate\Plugin\MigrationPluginManager
   */
  private MigrationPluginManager $migrationPluginManager;

  /**
   * The logger channel for this instance.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  private LoggerChannelInterface $logger;

  /**
   * Processes queue items for PTV updates regarding node updates.
   *
   * The items from this queue generate work for Migrate API: each queue item
   * can hold any number of source IDs for nodes to update. In our
   * implementation however, this number is usually exactly 1 to keep the length
   * of the processing manageable and to be able to monitor queue lenghts more
   * easily.
   *
   * @{inheritdoc}
   */
  public function processItem($data) {
    if (!($data instanceof PtvUpdateQueueItem)) {
      return;
    }

    $this->handleMigrationItem($data);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new self($configuration, $plugin_id, $plugin_definition);
    $instance->migrationPluginManager = $container->get('plugin.manager.migration');
    $instance->logger = $container->get('logger.factory')->get('tre_ptv_import');

    return $instance;
  }

  /**
   * Handles migrations for each of the properties of the queue item.
   *
   * Ensures the correct order of the migrations so that no stubs are needed in
   * the system.
   *
   * @param \Drupal\tre_ptv_import\PtvUpdateQueueItem $item
   *   The queue item whose migrations are being handled.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  private function handleMigrationItem(PtvUpdateQueueItem $item) {
    $migration_language_map = SingleItemUpdaterInterface::CONTENT_TYPES_TO_MIGRATIONS_MAP;

    $langcode = $item->getLangcode();
    $this->handleMigration($item->getServiceChannels(), $migration_language_map['service_channel'][$langcode]);
    $this->handleMigration($item->getServiceLocations(), $migration_language_map['place_of_business'][$langcode]);
    $this->handleMigration($item->getServices(), $migration_language_map['ptv_service'][$langcode]);
  }

  /**
   * Handles an individual migration for the items.
   *
   * @param string[] $items
   *   The UUIDs of the items to migrate.
   * @param string $migration_id
   *   The ID of the migration to run on the UUIDs (source IDs).
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   On Plugin errors.
   *
   * @throws \InvalidArgumentException
   *   If the passed-in $items contains values other than strings.
   *
   * @see \Drupal\migrate_tools\MigrateTools::buildIdList()
   * @see \Drupal\migrate_tools\Commands\MigrateToolsCommands::executeMigration()
   */
  private function handleMigration(array $items, string $migration_id) {
    if (empty($items)) {
      return;
    }

    $non_strings = array_filter($items, function ($item) {
      return !is_string($item);
    });

    // PHPStan thinks that the string[] in the phpdoc comment applies inside
    // this method but in fact it does not.
    // @phpstan-ignore-next-line
    if (!empty($non_strings)) {
      throw new \InvalidArgumentException("Passed argument contains items other than strings.");
    }

    /** @var \Drupal\migrate\Plugin\MigrationInterface $migration */
    $migration = $this->migrationPluginManager->createInstance($migration_id);
    $migration->setStatus(MigrationInterface::STATUS_IDLE);

    // Inferred keys like in
    // \Drupal\migrate_tools\Commands\MigrateToolsCommands::executeMigration.
    $keys = array_keys($migration->getSourcePlugin()->getIds());
    foreach ($items as $item) {
      $migration->getIdMap()->setUpdate(array_combine($keys, [$item]));
    }
    $options = [
      'idlist' => implode(',', $items),
    ];

    $executable = new MigrateExecutable($migration, new MigrateMessage(), $options);
    $this->logger->info("Starting import for items for {$migration_id}: " . implode(",", $items));
    $executable->import();
    $this->logger->info("Ended import for items for {$migration_id}: " . implode(",", $items));
  }

}
