<?php

namespace Drupal\tre_ptv_import\Commands;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Site\Settings;
use Drupal\node\NodeInterface;
use Drupal\tre_ptv_import\PtvServiceIntermediateStorageInterface;
use Drupal\tre_ptv_import\Service\SingleItemUpdaterInterface;
use Drush\Commands\DrushCommands;
use Drush\Exceptions\CommandFailedException;

/**
 * Drush commandfile for the tre_hr_import module.
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
   * Constructs the commands object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, PtvServiceIntermediateStorageInterface $ptv_storage, SingleItemUpdaterInterface $ptv_updater) {
    $this->nodeStorage = $entity_type_manager->getStorage('node');
    $this->ptvStorage = $ptv_storage;
    $this->ptvUpdater = $ptv_updater;
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

}
