<?php

namespace Drupal\tre_ptv_import\Commands;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Site\Settings;
use Drupal\node\NodeInterface;
use Drupal\tre_ptv_import\PtvServiceIntermediateStorageInterface;
use Drupal\tre_ptv_import\Service\SingleItemUpdaterInterface;
use Drush\Commands\DrushCommands;
use Drush\Exceptions\CommandFailedException;

/**
 * Drush commandfile for the tre_ptv_import module.
 */
class TrePtvImportCommands extends DrushCommands {

  /**
   * The entity storage service.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $nodeStorage;

  /**
   * The PTV data intermediate storage.
   *
   * @var \Drupal\tre_ptv_import\PtvServiceIntermediateStorageInterface
   */
  private PtvServiceIntermediateStorageInterface $ptvStorage;

  /**
   * The PTV single item updater service.
   *
   * @var \Drupal\tre_ptv_import\Service\SingleItemUpdaterInterface
   */
  private SingleItemUpdaterInterface $ptvUpdater;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $db;

  /**
   * Constructs the commands object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, PtvServiceIntermediateStorageInterface $ptv_storage, SingleItemUpdaterInterface $ptv_updater, Connection $database) {
    $this->nodeStorage = $entity_type_manager->getStorage('node');
    $this->ptvStorage = $ptv_storage;
    $this->ptvUpdater = $ptv_updater;
    $this->db = $database;
  }

  /**
   * Queues a single node for direct update from the PTV API.
   *
   * @param int $nid
   *   The node id of the node to update.
   *
   * @command tre_ptv_import:update_single_node
   * @aliases ptv_single_node_update
   *
   * @throws \Drush\Exceptions\CommandFailedException
   */
  public function updateSingleNode(int $nid) {
    $node = $this->nodeStorage->load($nid);
    if (!($node instanceof NodeInterface) || !$this->ptvUpdater->checkMigrationSource($node)) {
      throw new CommandFailedException("Given node is not refreshable from PTV.");
    }

    $this->ptvUpdater->updateSingleItem($node);
    $this->logger()->success("Queued node $nid to update from PTV.");
  }

  /**
   * Refreshes services and related data from PTV API into intermediate storage.
   *
   * @param string $service_ids
   *   The UUIDs of the services as a comma-separated string. Defaults to 'all'
   *   in which case all the services and related data will be refreshed.
   *
   * @option refresh-cache
   *   When given, refreshes the cached service and service channel definitions.
   *
   * @usage tre_ptv_import:ptv_data_refresh <uuid1>,<uuid2>
   *   (Re-)imports the data of given PTV services from the PTV API into the
   *   intermediate storage.
   *
   * @command tre_ptv_import:ptv_data_refresh
   * @aliases ptv_data_refresh
   */
  public function refreshPtvServicesAndServiceChannels($service_ids = 'all') {
    $refresh_requested = $this->input()->hasOption('refresh-cache');
    if ($service_ids !== 'all') {
      $uuids = str_getcsv($service_ids);
      $services = $this->ptvStorage->getSpecificServicesFromApi($uuids);
    }
    else {
      $services = $this->ptvStorage->getServicesFromApi($refresh_requested);
    }

    if (is_array($services) && count($services) > 0) {
      // Only clean the intermediate tables in case _all_ services are being
      // updated.
      if ($service_ids === 'all') {
        $this->ptvStorage->wipePtvData();
      }

      $this->ptvStorage->insertServices($services);
      $service_chunks = array_chunk($services, Settings::get('ptv_service_import_batch_size', 50));
      foreach ($service_chunks as $chunk) {
        $service_channels = $this->ptvStorage->getServiceChannelsForServicesFromApi($chunk, $refresh_requested);
        $this->ptvStorage->insertServiceChannels($service_channels);
      }
    }
  }

  /**
   * Updates the data of a single service channel from PTV API to DB storage.
   *
   * @param string $id
   *   The UUID to update from the API.
   *
   * @command tre_ptv_import:update_channel
   * @aliases update_ptv_channel
   */
  public function updateSingleServiceChannel(string $id) {
    $service_channel_response = $this->ptvStorage->getServiceChannelsByIdsFromApi([$id], TRUE);
    $this->ptvStorage->insertServiceChannels($service_channel_response);

    $this->io()->success("Service channel with $id updated successfully.");
  }

  /**
   * Shows debug output for PTV data from storage or from the API.
   *
   * @param string $type
   *   The type of the item to show. Either 'channel' for service channels or
   *   'service' for services.
   * @param string $storage
   *   The storage to use for fetching the data. Either 'db' for intermediate DB
   *   storage or 'api' for fetching the data directly from the API.
   * @param string $id
   *   The UUID of the item to show.
   *
   * @command tre_ptv_import:debug
   * @aliases ptv_debug,debug_ptv
   */
  public function debugSingleItem(string $type, string $storage, string $id) {
    if (!in_array($type, ['service', 'channel'], TRUE)) {
      throw new \InvalidArgumentException("Invalid type requested. Use either 'service' or 'channel'.");
    }

    if (!in_array($storage, ['api', 'db'])) {
      throw new \InvalidArgumentException("Invalid storage requested. Use either 'api' or 'db'.");
    }

    switch ($type) {
      case 'service':
        $this->debugService($storage, $id);
        break;

      case 'channel':
        $this->debugServiceChannel($storage, $id);
        break;
    }
  }

  /**
   * Outputs debug data about a PTV service.
   *
   * @param string $storage
   *   The type of storage to use for fetching the data. Either 'db' or 'api'.
   * @param string $id
   *   The UUID of the service to output.
   */
  private function debugService(string $storage, string $id) {
    $service = NULL;
    switch ($storage) {
      case 'db':
        $service = $this->ptvStorage->getServiceFromStorage($id);
        break;

      case 'api':
        $services = $this->ptvStorage->getServicesFromApi(TRUE);
        if (is_array($services)) {
          $filtered_services = array_filter($services, function ($service) use ($id) {
            return $service->getId() === $id;
          });
          $service = reset($filtered_services);
        }
        break;
    }

    $this->io()->text(print_r($service, TRUE));
  }

  /**
   * Outputs debug data for a PTV service channel.
   *
   * @param string $storage
   *   The type of storage to use for fetching the data. Either 'db' or 'api'.
   * @param string $id
   *   The UUID of the service channel to output.
   */
  private function debugServiceChannel(string $storage, string $id) {
    $channel = NULL;
    switch ($storage) {
      case 'db':
        $channel = $this->ptvStorage->getServiceChannelFromStorageById($id);
        break;

      case 'api':
        $channels = $this->ptvStorage->getServiceChannelsByIdsFromApi([$id], TRUE);
        if (is_array($channels)) {
          $channel = reset($channels);
        }
        break;
    }

    $this->io()->text(print_r($channel, TRUE));
  }

  /**
   * Deletes unused service nodes and map points (via delete hook).
   *
   * @param string $content_type
   *   The content type to process.
   * @param string $language
   *   The language to process.
   * @param bool $dry_run
   *   Whether to perform a dry run (no deletion) (default: TRUE).
   *
   * @usage tre_ptv_import:delete_unused_service_nodes_and_map_points <content-type> <language> <dry-run>
   *   Deleting all services not in the migrate map.
   *   Defaults to dry-run, use '0' as third parameter to actually delete.
   *
   * @command tre_ptv_import:delete_unused_service_nodes_and_map_points
   * @aliases ptv_rm_services
   */
  public function deleteUnusedServiceNodesAndMapPoints($content_type, $language, $dry_run = TRUE): void {

    $service_node_deleted = 0;
    // Let's exit the script if the content type is not supported.
    switch ($content_type) {
      case 'place_of_business':
        $migrate_table = 'migrate_map_ptv_service_locations' . (($language === 'en') ? '_en' : '');
        break;

      case 'ptv_service':
        $migrate_table = 'migrate_map_ptv_services' . (($language === 'en') ? '_en' : '');
        break;

      case 'service_channel':
        $migrate_table = 'migrate_map_ptv_service_channels' . (($language === 'en') ? '_en' : '');
        break;

      default:
        throw new \Exception(message: dt(string: 'This content type cannot be processed.'));
    }

    $query = $this->db->select(table: 'node', alias: 'n');

    $query->fields(table_alias: 'n', fields: ['nid']);
    $query->condition(field: 'n.type', value: $content_type, operator: '=');
    $query->condition(field: 'n.langcode', value: $language, operator: '=');

    $query->join(table: 'node_field_data', alias: 'nf', condition: 'n.nid = nf.nid');
    $query->condition(field: 'nf.uid', value: 0, operator: '=');

    $query->leftJoin(table: $migrate_table, alias: 'm', condition: 'n.nid = m.destid1');

    // Find the non-matching set.
    $query->isNull(field: 'm.destid1');

    $results = $query->execute()->fetchAll();

    // If we are not in a dry run, delete the nodes.
    if (!$dry_run) {
      foreach ($results as $result) {
        $node_to_delete = $this->nodeStorage->load(id: $result->nid);
        if ($node_to_delete instanceof NodeInterface) {
          $node_to_delete->delete();
          $service_node_deleted++;
        }
      }
      $this->io()->success(message: "Deleted {$service_node_deleted} {$content_type} node(s).");
    }
    else {
      $amount = count(value: $results);
      $this->io()->writeln(messages: "{$amount} node(s) of type {$content_type} would be deleted.");
    }
  }

}
