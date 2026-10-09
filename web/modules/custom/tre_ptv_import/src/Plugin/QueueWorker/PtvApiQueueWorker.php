<?php

declare(strict_types=1);

namespace Drupal\tre_ptv_import\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\tre_ptv_import\Service\Storage\PtvIntermediateStorageInterface;
use Drupal\tre_ptv_import\PtvUpdateQueueItem;
use Drupal\tre_ptv_import\Service\Api\PtvApiFetcher;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * The PTV API fetcher.
   *
   * @var \Drupal\tre_ptv_import\Service\Api\PtvApiFetcher
   */
  private PtvApiFetcher $apiFetcher;

  /**
   * The PTV intermediate storage service.
   *
   * @var \Drupal\tre_ptv_import\Service\Storage\PtvIntermediateStorageInterface
   */
  private PtvIntermediateStorageInterface $ptvStorage;

  /**
   * The queue for creating new items to update using Migrate API.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  private QueueInterface $queue;

  /**
   * Processes queue items for PTV updates.
   *
   * This queue fetches refreshed PTV data, stores it in intermediate storage
   * and creates node migration queue items.
   *
   * {@inheritdoc}
   */
  public function processItem($data) {
    if (!($data instanceof PtvUpdateQueueItem)) {
      return;
    }

    try {
      $langcode = $data->getLangcode();

      $this->refreshChannels($data->getServiceLocations(), $langcode);
      $this->refreshChannels($data->getServiceChannels(), $langcode);
      $this->refreshServices($data->getServices(), $langcode);
    }
    catch (\Throwable $exception) {
      throw new SuspendQueueException(
        'PTV API update failed: ' . $exception->getMessage(),
        0,
        $exception
      );
    }
  }

  /**
   * Fetches, stores and queues updates for selected service channels.
   *
   * Service locations are also service channels in the API, so both
   * service-channel and place-of-business updates use this method.
   *
   * @param string[] $channelContentIds
   *   PTV service channel content IDs.
   * @param string $langcode
   *   Content language.
   */
  private function refreshChannels(
    array $channelContentIds,
    string $langcode,
  ): void {
    if ($channelContentIds === []) {
      return;
    }

    $importData = $this->apiFetcher->fetchImportDataForChannels(
      $channelContentIds
    );

    $this->storeAndQueueImportData($importData, $langcode);
  }

  /**
   * Fetches, stores and queues updates for selected services.
   *
   * A service refresh also fetches its connections and connected channels.
   * Channels and locations are queued before services to retain the existing
   * migration dependency order.
   *
   * @param string[] $serviceContentIds
   *   PTV service content IDs.
   * @param string $langcode
   *   Content language.
   */
  private function refreshServices(
    array $serviceContentIds,
    string $langcode,
  ): void {
    if ($serviceContentIds === []) {
      return;
    }

    $importData = $this->apiFetcher->fetchImportDataForServices(
      $serviceContentIds
    );

    $this->storeAndQueueImportData($importData, $langcode);
  }

  /**
   * Stores import data and queues the affected node migrations.
   *
   * @param array<string, mixed> $importData
   *   Fetched import data.
   * @param string $langcode
   *   Content language.
   */
  private function storeAndQueueImportData(
    array $importData,
    string $langcode,
  ): void {
    $preparedImportData = $this->ptvStorage->prepareImportDataForStorage(
      $importData
    );

    $this->ptvStorage->upsertImportData($preparedImportData);

    $channelRows = $preparedImportData['channels'] ?? [];
    $serviceRows = $preparedImportData['services'] ?? [];

    if (is_array($channelRows)) {
      $this->queueChannelMigrations($channelRows, $langcode);
    }

    if (is_array($serviceRows)) {
      $this->queueServiceMigrations($serviceRows, $langcode);
    }
  }

  /**
   * Queues service channel and service location migrations.
   *
   * Non-location channels are queued first, followed by locations.
   *
   * @param array<string, array<string, mixed>> $channelRows
   *   Prepared channel rows keyed by content ID.
   * @param string $langcode
   *   Content language.
   */
  private function queueChannelMigrations(
    array $channelRows,
    string $langcode,
  ): void {
    $serviceChannelIds = [];
    $serviceLocationIds = [];

    foreach ($channelRows as $channelRow) {
      $uuid = $channelRow['uuid'] ?? NULL;
      $type = $channelRow['type'] ?? NULL;

      if (!is_string($uuid) || trim($uuid) === '') {
        continue;
      }

      if ($type === 'ServiceLocation') {
        $serviceLocationIds[] = $uuid;
      }
      else {
        $serviceChannelIds[] = $uuid;
      }
    }

    foreach ($serviceChannelIds as $channelId) {
      $queueItem = new PtvUpdateQueueItem($langcode);
      $queueItem->setServiceChannels([$channelId]);
      $this->queue->createItem($queueItem);
    }

    foreach ($serviceLocationIds as $locationId) {
      $queueItem = new PtvUpdateQueueItem($langcode);
      $queueItem->setServiceLocations([$locationId]);
      $this->queue->createItem($queueItem);
    }
  }

  /**
   * Queues service migrations.
   *
   * @param array<string, array<string, mixed>> $serviceRows
   *   Prepared service rows keyed by content ID.
   * @param string $langcode
   *   Content language.
   */
  private function queueServiceMigrations(
    array $serviceRows,
    string $langcode,
  ): void {
    foreach ($serviceRows as $serviceRow) {
      $uuid = $serviceRow['uuid'] ?? NULL;

      if (!is_string($uuid) || trim($uuid) === '') {
        continue;
      }

      $queueItem = new PtvUpdateQueueItem($langcode);
      $queueItem->setServices([$uuid]);
      $this->queue->createItem($queueItem);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new self($configuration, $plugin_id, $plugin_definition);
    $instance->apiFetcher = $container->get('tre_ptv_import.api_fetcher');
    $instance->ptvStorage = $container->get('tre_ptv_import.intermediate_storage');
    $instance->queue = $container->get('queue')->get('ptv_node_migrations', TRUE);

    return $instance;
  }

}