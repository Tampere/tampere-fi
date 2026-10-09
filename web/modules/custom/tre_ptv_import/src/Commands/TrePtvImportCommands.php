<?php

declare(strict_types=1);

namespace Drupal\tre_ptv_import\Commands;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\tre_ptv_import\Service\Storage\PtvIntermediateStorageInterface;
use Drupal\tre_ptv_import\Service\Api\PtvApiFetcher;
use Drupal\tre_ptv_import\Service\Migration\PtvImportValidator;
use Drupal\tre_ptv_import\Service\Migration\SingleItemUpdaterInterface;
use Drush\Commands\DrushCommands;
use Drush\Exceptions\CommandFailedException;

/**
 * Drush commands for the PTV import module.
 */
final class TrePtvImportCommands extends DrushCommands {

  /**
   * The node storage.
   */
  private EntityStorageInterface $nodeStorage;

  /**
   * Constructs the commands object.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    private readonly PtvIntermediateStorageInterface $ptvStorage,
    private readonly Connection $database,
    private readonly PtvApiFetcher $ptvFetcher,
    private readonly PtvImportValidator $ptvImportValidator,
    private readonly SingleItemUpdaterInterface $ptvUpdater,
  ) {
    $this->nodeStorage = $entityTypeManager->getStorage('node');
  }

  /**
   * Queues a single node for direct update from the PTV API.
   *
   * @param int $nid
   *   The node ID to update.
   *
   * @usage tre_ptv_import:update_single_node 667623
   *   Queues one PTV-backed node for an API refresh and migration update.
   *
   * @command tre_ptv_import:update_single_node
   * @aliases ptv_single_node_update
   */
  public function updateSingleNode(int $nid): void {
    $node = $this->nodeStorage->load($nid);

    if (
      !($node instanceof NodeInterface)
      || !$this->ptvUpdater->checkMigrationSource($node)
    ) {
      throw new CommandFailedException(
        'Given node is not refreshable from PTV.'
      );
    }

    if (!$this->ptvUpdater->updateSingleItem($node)) {
      throw new CommandFailedException(
        'Unable to queue node ' . $nid . ' for a PTV update.'
      );
    }

    $this->io()->success(
      'Queued node ' . $nid . ' to update from PTV.'
    );
  }

  /**
   * Refreshes PTV data into intermediate storage.
   *
   * @param string $serviceIds
   *   Either "all" or a comma-separated list of service content IDs.
   *
   * @option dry-run
   *   Fetches, validates and prepares import data without changing storage.
   *
   * @usage tre_ptv_import:ptv_data_refresh all --dry-run
   *   Fetches and validates the full import package without writing data.
   * @usage tre_ptv_import:ptv_data_refresh all
   *   Replaces intermediate storage with fresh PTV API data.
   * @usage tre_ptv_import:ptv_data_refresh <uuid1>,<uuid2>
   *   Updates only the selected services and their connected channels.
   *
   * @command tre_ptv_import:ptv_data_refresh
   * @aliases ptv_data_refresh
   */
  public function refreshPtvServicesAndServiceChannels(
    string $serviceIds = 'all',
  ): void {
    $serviceIds = trim($serviceIds);
    $isFullRefresh = $serviceIds === 'all';
    $dryRun = (bool) $this->input()->getOption('dry-run');

    if ($serviceIds === '') {
      throw new CommandFailedException(
        'Provide "all" or a comma-separated list of service content IDs.'
      );
    }
    /**
     * You can think this command as 4 step flow:
     * 1. Fetch the Services, Connections and Service channels, Connections are PTV12 data that connects Services
     * with Service channels
     * 2. After we have fetched all the data, we validate the data
     * 3. After validation we convert / prepare the data for the storage - basically converting DTOs into storeable encoded JSON
     * 4. Based on the refresh type (single or all) we either upsert or replace the storage tables
     */
    try {
      if ($isFullRefresh) {
        // 1. STEP : Fetch all Services, Connections and Service channels
        $importData = $this->ptvFetcher->fetchImportData();
      }
      else {
        $selectedServiceIds = array_values(array_filter(
          array_map('trim', str_getcsv($serviceIds)),
          static fn (string $serviceId): bool => $serviceId !== '',
        ));

        if ($selectedServiceIds === []) {
          throw new CommandFailedException(
            'Provide at least one valid service content ID.'
          );
        }

        $importData = $this->ptvFetcher->fetchImportDataForServices(
          $selectedServiceIds
        );
      }
      // 2. STEP validate the data and get a summary (summary is more for development / debugging).
      $report = $this->ptvImportValidator->validate($importData);
      $summary = $report['summary'] ?? [];

      if (($summary['is_valid'] ?? FALSE) !== TRUE) {
        throw new CommandFailedException(
          'PTV import validation failed with '
          . (int) ($summary['error_count'] ?? 0)
          . ' error(s). Intermediate storage was not changed.'
        );
      }

      if ((int) ($summary['service_count'] ?? 0) === 0) {
        throw new CommandFailedException(
          'PTV import returned zero services. Intermediate storage was not changed.'
        );
      }
    /**
     * 3. STEP: Prepares the validated import data for intermediate storage.
     *
     * Generated DTOs are converted into JSON-compatible data and encoded as JSON.
     * Migration source plugins later decode the records and deserialize payloads
     * back into generated DTOs for migration mapping (DTO are needed to access the getters).
     */
      $preparedImportData = $this->ptvStorage
        ->prepareImportDataForStorage($importData);

      $summaryMessage = 'PTV import is valid. Services: '
        . (int) ($summary['service_count'] ?? 0)
        . ', connections: '
        . (int) ($summary['connection_count'] ?? 0)
        . ', channels: '
        . (int) ($summary['channel_count'] ?? 0)
        . ', warnings: '
        . (int) ($summary['warning_count'] ?? 0)
        . '.';
      // If dry run was added for the drush command - stop here - does not update the storage
      if ($dryRun) {
        $this->io()->success(
          $summaryMessage . ' Dry run completed; intermediate storage was not changed.'
        );

        return;
      }
      /**
       * 4. STEP: Based on the drush command we either replace or upsert -
       *  if list of uuid values were provided -> Upsert, if not -> All is replaces
       */ 
      $writtenStatistics = $isFullRefresh
        ? $this->ptvStorage->replaceImportData($preparedImportData)
        : $this->ptvStorage->upsertImportData($preparedImportData);

      $storageAction = $isFullRefresh ? 'replaced' : 'updated';

      $this->io()->success(
        $summaryMessage
        . ' Intermediate storage '
        . $storageAction
        . ' with '
        . $writtenStatistics['service_count']
        . ' service row(s) and '
        . $writtenStatistics['channel_count']
        . ' channel row(s).'
      );
    }
    catch (CommandFailedException $exception) {
      throw $exception;
    }
    catch (\Throwable $exception) {
      throw new CommandFailedException(
        'PTV refresh failed before intermediate storage was changed: '
        . $exception->getMessage(),
        0,
        $exception
      );
    }
  }

  /**
   * Deletes unused service nodes and map points through delete hooks.
   *
   * @param string $contentType
   *   The content type to process.
   * @param string $language
   *   The language to process.
   * @param bool $dryRun
   *   Whether to perform a dry run.
   *
   * @usage tre_ptv_import:delete_unused_service_nodes_and_map_points <content-type> <language> <dry-run>
   *   Deletes nodes not found in the relevant migration map. Use "0" as the
   *   third parameter to perform deletion.
   *
   * @command tre_ptv_import:delete_unused_service_nodes_and_map_points
   * @aliases ptv_rm_services
   */
  public function deleteUnusedServiceNodesAndMapPoints(
    string $contentType,
    string $language,
    bool $dryRun = TRUE,
  ): void {
    $migrateTable = match ($contentType) {
      'place_of_business' => 'migrate_map_ptv_service_locations'
        . ($language === 'en' ? '_en' : ''),
      'ptv_service' => 'migrate_map_ptv_services'
        . ($language === 'en' ? '_en' : ''),
      'service_channel' => 'migrate_map_ptv_service_channels'
        . ($language === 'en' ? '_en' : ''),
      default => throw new \InvalidArgumentException(
        'This content type cannot be processed.'
      ),
    };

    $query = $this->database->select('node', 'node');
    $query->fields('node', ['nid']);
    $query->condition('node.type', $contentType);
    $query->condition('node.langcode', $language);

    $query->join('node_field_data', 'node_field_data', 'node.nid = node_field_data.nid');
    $query->condition('node_field_data.uid', 0);

    $query->leftJoin(
      $migrateTable,
      'migration_map',
      'node.nid = migration_map.destid1'
    );
    $query->isNull('migration_map.destid1');

    $results = $query->execute()->fetchAll();

    if ($dryRun) {
      $this->io()->writeln(
        count($results) . ' node(s) of type ' . $contentType . ' would be deleted.'
      );

      return;
    }

    $deletedCount = 0;

    foreach ($results as $result) {
      $node = $this->nodeStorage->load($result->nid);

      if ($node instanceof NodeInterface) {
        $node->delete();
        $deletedCount++;
      }
    }

    $this->io()->success(
      'Deleted ' . $deletedCount . ' ' . $contentType . ' node(s).'
    );
  }

}