<?php

declare(strict_types=1);

namespace Drupal\tre_ptv_import\Service\Migration;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use OpenAPI\Client\Model\ModelInterface;

/**
 * Validates the fetched PTV import package before storage is replaced.
 */
final class PtvImportValidator {

  /**
   * Service channel types supported by the source mappers.
   */
  private const SERVICE_CHANNEL_TYPES = [
    'EService',
    'TelephoneService',
    'PrintableForm',
    'ServiceLocation',
    'WebPage',
  ];

  /**
   * The module logger.
   */
  private LoggerChannelInterface $logger;

  /**
   * Constructs the validator.
   */
  public function __construct(
    LoggerChannelFactoryInterface $loggerFactory,
  ) {
    $this->logger = $loggerFactory->get('tre_ptv_import');
  }

  /**
   * Validates the fetched import package before intermediate storage is replaced.
   *
   * Checks the package structure, verifies that payloads and connections are
   * generated DTO models, and uses DTO getters to validate record IDs,
   * Service-to-Connection links, referenced Channels and supported Channel types.
   *
   * @param array $importData
   *   Data returned by the PTV API fetcher.
   *
   * @return array
   *   Validation summary and errors.
   */
  public function validate(array $importData): array {
    $errors = [];

    $services = $this->getCollection($importData, 'services', $errors);
    $channels = $this->getCollection($importData, 'channels', $errors);

    $serviceIds = $this->createRecordIdSet($services, 'service', $errors);
    $channelIds = $this->createRecordIdSet($channels, 'channel', $errors);
    $channelTypeCounts = array_fill_keys(self::SERVICE_CHANNEL_TYPES, 0);
    $serviceConnectionCount = 0;

    foreach ($services as $serviceId => $serviceRecord) {
      $serviceId = is_string($serviceId) ? trim($serviceId) : '';

      if ($serviceId === '' || !isset($serviceIds[$serviceId])) {
        continue;
      }

      $record = $this->getRecord($serviceRecord, 'service', $serviceId, $errors);
      if ($record === NULL) {
        continue;
      }

      $service = $this->getPayloadModel($record, 'service', $serviceId, $errors);
      if ($service !== NULL) {
        $this->validatePayloadContentId($service, 'service', $serviceId, $errors);
      }

      foreach ($this->getConnectionModels($record, $serviceId, $errors) as $connectionIndex => $connection) {
        $serviceConnectionCount++;

        $connectionServiceId = $this->getModelString($connection, 'getServiceContentId');
        if ($connectionServiceId === NULL) {
          $errors[] = 'Service "' . $serviceId . '" connection ' . $connectionIndex . ' does not contain serviceContentId.';
        }
        elseif ($connectionServiceId !== $serviceId) {
          $errors[] = 'Service "' . $serviceId . '" connection ' . $connectionIndex . ' points to service "' . $connectionServiceId . '".';
        }

        $connectionChannelId = $this->getModelString($connection, 'getServiceChannelContentId');
        if ($connectionChannelId === NULL) {
          $errors[] = 'Service "' . $serviceId . '" connection ' . $connectionIndex . ' does not contain channelContentId.';
        }
        elseif (!isset($channelIds[$connectionChannelId])) {
          $errors[] = 'Service "' . $serviceId . '" references channel "' . $connectionChannelId . '", but the channel was not fetched.';
        }
      }
    }

    foreach ($channels as $channelId => $channelRecord) {
      $channelId = is_string($channelId) ? trim($channelId) : '';

      if ($channelId === '' || !isset($channelIds[$channelId])) {
        continue;
      }

      $record = $this->getRecord($channelRecord, 'channel', $channelId, $errors);
      if ($record === NULL) {
        continue;
      }

      $channel = $this->getPayloadModel($record, 'channel', $channelId, $errors);
      if ($channel === NULL) {
        continue;
      }

      $this->validatePayloadContentId($channel, 'channel', $channelId, $errors);

      $channelType = $this->getModelString($channel, 'getServiceChannelType');
      if ($channelType === NULL) {
        $errors[] = 'Channel "' . $channelId . '" does not contain serviceChannelType.';
        continue;
      }

      if (!in_array($channelType, self::SERVICE_CHANNEL_TYPES, TRUE)) {
        $errors[] = 'Channel "' . $channelId . '" has unsupported serviceChannelType "' . $channelType . '".';
        continue;
      }

      $channelTypeCounts[$channelType]++;
    }

    ksort($channelTypeCounts);

    $summary = [
      'is_valid' => $errors === [],
      'service_count' => count($services),
      'connection_count' => $serviceConnectionCount,
      'channel_count' => count($channels),
      'error_count' => count($errors),
      'warning_count' => 0,
    ];

    if ($summary['is_valid']) {
      $this->logger->notice(
        'PTV import validation succeeded. Services: @services, connections: @connections, channels: @channels.',
        [
          '@services' => $summary['service_count'],
          '@connections' => $summary['connection_count'],
          '@channels' => $summary['channel_count'],
        ]
      );
    }
    else {
      $this->logger->error(
        'PTV import validation failed with @errors error(s).',
        [
          '@errors' => $summary['error_count'],
        ]
      );
    }

    return [
      'summary' => $summary,
      'channel_types' => [
        'counts' => $channelTypeCounts,
      ],
      'errors' => $errors,
    ];
  }

  /**
   * Gets a named import collection.
   */
  private function getCollection(
    array $importData,
    string $key,
    array &$errors,
  ): array {
    $collection = $importData[$key] ?? NULL;

    if (!is_array($collection)) {
      $errors[] = 'PTV import package does not contain a valid "' . $key . '" collection.';
      return [];
    }

    return $collection;
  }

  /**
   * Creates a lookup set from record keys.
   */
  private function createRecordIdSet(
    array $records,
    string $entityType,
    array &$errors,
  ): array {
    $ids = [];

    foreach ($records as $id => $record) {
      if (!is_string($id) || trim($id) === '') {
        $errors[] = 'A ' . $entityType . ' record has an empty or invalid content ID key.';
        continue;
      }

      $ids[trim($id)] = TRUE;
    }

    return $ids;
  }

  /**
   * Gets one import record.
   */
  private function getRecord(
    mixed $record,
    string $entityType,
    string $id,
    array &$errors,
  ): ?array {
    if (!is_array($record)) {
      $errors[] = ucfirst($entityType) . ' "' . $id . '" is not a valid import record.';
      return NULL;
    }

    return $record;
  }

  /**
   * Gets a generated payload model from an import record.
   */
  private function getPayloadModel(
    array $record,
    string $entityType,
    string $id,
    array &$errors,
  ): ?ModelInterface {
    $payload = $record['payload'] ?? NULL;

    if (!$payload instanceof ModelInterface) {
      $errors[] = ucfirst($entityType) . ' "' . $id . '" does not contain a generated payload model.';
      return NULL;
    }

    return $payload;
  }

  /**
   * Gets generated connection models from a service record.
   *
   * @return \OpenAPI\Client\Model\ModelInterface[]
   *   Generated connection DTOs.
   */
  private function getConnectionModels(
    array $record,
    string $serviceId,
    array &$errors,
  ): array {
    $connections = $record['connections'] ?? NULL;

    if (!is_array($connections)) {
      $errors[] = 'Service "' . $serviceId . '" does not contain a connections array.';
      return [];
    }

    $models = [];

    foreach ($connections as $index => $connection) {
      if (!$connection instanceof ModelInterface) {
        $errors[] = 'Service "' . $serviceId . '" connection ' . $index . ' is not a generated model.';
        continue;
      }

      $models[$index] = $connection;
    }

    return $models;
  }

  /**
   * Validates that a payload content ID matches its import record key.
   */
  private function validatePayloadContentId(
    ModelInterface $payload,
    string $entityType,
    string $recordId,
    array &$errors,
  ): void {
    $payloadId = $this->getModelString($payload, 'getContentId');

    if ($payloadId === NULL) {
      $errors[] = ucfirst($entityType) . ' "' . $recordId . '" payload does not contain contentId.';
      return;
    }

    if ($payloadId !== $recordId) {
      $errors[] = ucfirst($entityType) . ' record key "' . $recordId . '" does not match payload contentId "' . $payloadId . '".';
    }
  }

  /**
   * Gets a normalized non-empty string from a generated model getter.
   */
  private function getModelString(
    ModelInterface $model,
    string $getter,
  ): ?string {
    if (!method_exists($model, $getter)) {
      return NULL;
    }

    $value = $model->{$getter}();

    if (!is_string($value) || trim($value) === '') {
      return NULL;
    }

    return trim($value);
  }

}