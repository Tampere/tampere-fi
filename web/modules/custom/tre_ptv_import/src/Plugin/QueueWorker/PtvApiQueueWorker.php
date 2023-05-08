<?php

namespace Drupal\tre_ptv_import\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\tre_ptv_import\PtvUpdateQueueItem;
use Drupal\tre_ptv_import\Service\PtvDataHelpers;
use Drupal\tre_ptv_import\Service\PtvServiceIntermediateStorage;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Tampere\PtvV11\ApiException;

/**
 * Queue worker handling the ptv_api_migrations queue.
 *
 * @QueueWorker(
 *   id = "ptv_api_migrations",
 *   title = @Translation("PTV API Migrations Queue"),
 *   cron = {"time" = 240}
 * )
 */
final class PtvApiQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The PTV intermediate storage service.
   *
   * @var \Drupal\tre_ptv_import\Service\PtvServiceIntermediateStorage
   */
  private PtvServiceIntermediateStorage $ptvStorage;

  /**
   * The queue for creating new items to update using Migrate API.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  private QueueInterface $queue;

  /**
   * The PTV data helpers service.
   *
   * @var \Drupal\tre_ptv_import\Service\PtvDataHelpers
   */
  private PtvDataHelpers $dataHelpers;

  /**
   * Processes queue items for PTV updates.
   *
   * This is the first-round queue which handles two tasks: it fetches refreshed
   * data from the PTV API for the items set to update in the queue and creates
   * new queue items of type ptv_node_migrations which will be handled
   * separately.
   *
   * @{inheritdoc}
   */
  public function processItem($data) {
    if (!($data instanceof PtvUpdateQueueItem)) {
      return;
    }

    // Since $data now is quaranteed to be a PtvUpdateQueueItem, we can use the
    // API to fetch the data.
    if (!empty($data->getServiceLocations())) {
      try {
        $channels = $this->ptvStorage->getServiceChannelsByIdsFromApi($data->getServiceLocations(), TRUE);
        $this->ptvStorage->insertServiceChannels($channels);
        foreach (array_keys($channels) as $location_id) {
          $new_data = new PtvUpdateQueueItem($data->getLangcode());
          $new_data->setServiceLocations([$location_id]);
          $this->queue->createItem($new_data);
        }
      }
      catch (ApiException $e) {
        throw new SuspendQueueException("API connection failed.", $e->getCode(), $e);
      }
    }

    if (!empty($data->getServiceChannels())) {
      try {
        $channels = $this->ptvStorage->getServiceChannelsByIdsFromApi($data->getServiceChannels(), TRUE);
        $this->ptvStorage->insertServiceChannels($channels);
        foreach (array_keys($channels) as $channel_id) {
          $new_data = new PtvUpdateQueueItem($data->getLangcode());
          $new_data->setServiceChannels([$channel_id]);
          $this->queue->createItem($new_data);
        }
      }
      catch (ApiException $e) {
        throw new SuspendQueueException("API connection failed.", $e->getCode(), $e);
      }
    }

    if (!empty($data->getServices())) {
      $location_data = [];
      $channel_data = [];

      $services = $this->ptvStorage->getSpecificServicesFromApi($data->getServices());
      if (empty($services)) {
        throw new SuspendQueueException("No services received from API using IDs: " . implode(", ", $data->getServices()));
      }
      $this->ptvStorage->insertServices($services);
      $service_data = new PtvUpdateQueueItem($data->getLangcode());
      $service_data->setServices(array_keys($services));

      try {
        $channels = $this->ptvStorage->getServiceChannelsForServicesFromApi($services, FALSE);
        $this->ptvStorage->insertServiceChannels($channels);

        [$service_locations, $service_channels] = $this->dataHelpers::separateLocationsFromOtherServiceChannels($channels);
        foreach (array_keys($service_channels) as $channel_id) {
          $channel_item = new PtvUpdateQueueItem($data->getLangcode());
          $channel_item->setServiceChannels([$channel_id]);
          $channel_data[] = $channel_item;
        }

        foreach (array_keys($service_locations) as $location_id) {
          $location_item = new PtvUpdateQueueItem($data->getLangcode());
          $location_item->setServiceLocations([$location_id]);
          $location_data[] = $location_item;
        }
      }
      catch (ApiException $e) {
        throw new SuspendQueueException("API connection failed.", $e->getCode(), $e);
      }

      // Ensure that the dependencies of the service are queued first and the
      // service itself last.
      if (!empty($channel_data)) {
        foreach ($channel_data as $channel_item) {
          $this->queue->createItem($channel_item);
        }
      }
      if (!empty($location_data)) {
        foreach ($location_data as $location_item) {
          $this->queue->createItem(($location_item));
        }
      }
      $this->queue->createItem($service_data);

    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new self($configuration, $plugin_id, $plugin_definition);
    $instance->ptvStorage = $container->get('tre_ptv_import.ptv_intermediate_storage');
    $instance->queue = $container->get('queue')->get('ptv_node_migrations', TRUE);
    $instance->dataHelpers = $container->get('tre_ptv_import.ptv_data_helpers');

    return $instance;
  }

}
