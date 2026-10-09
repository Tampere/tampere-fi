<?php

declare(strict_types=1);

namespace Drupal\tre_ptv_import\Service\Storage;

use Drupal\Core\Database\Connection;
use OpenAPI\Client\Model\ModelInterface;
use OpenAPI\Client\ObjectSerializer;

/**
 * Stores prepared PTV API data in intermediate storage tables.
 */
final class PtvIntermediateStorage implements PtvIntermediateStorageInterface {

  /**
   * The maximum number of rows written in one insert query.
   */
  private const WRITE_BATCH_SIZE = 50;

  /**
   * The service intermediate storage table.
   */
  private const SERVICE_TABLE = 'tre_ptv_import_service';

  /**
   * The service channel intermediate storage table.
   */
  private const CHANNEL_TABLE = 'tre_ptv_import_channel';

  /**
   * Constructs intermediate storage.
   */
  public function __construct(
    private readonly Connection $database,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function prepareImportDataForStorage(array $importData): array {
    $services = $this->getCollection($importData, 'services');
    $channels = $this->getCollection($importData, 'channels');

    $preparedServices = [];
    $preparedChannels = [];
    $connectionCount = 0;

    foreach ($services as $serviceId => $service) {
      $id = $this->getId($serviceId, 'service');
      $payload = $this->getPayload($service, $id);
      $connections = $this->getConnections($service, $id);
      $areas = $this->getAreas($service, $id);

      $connectionCount += count($connections);

      $preparedServices[$id] = [
        'uuid' => $id,
        'data' => $this->encodeStorageRecord(
          $payload,
          $connections,
          [],
          $areas
        ),
      ];
    }

    foreach ($channels as $channelId => $channel) {
      $id = $this->getId($channelId, 'channel');
      $payload = $this->getPayload($channel, $id);
      $accessibility = $this->getAccessibility($channel, $id);
      $areas = $this->getAreas($channel, $id);

      $preparedChannels[$id] = [
        'uuid' => $id,
        'type' => $this->getChannelType($payload, $id),
        'data' => $this->encodeStorageRecord(
          $payload,
          [],
          $accessibility,
          $areas
        ),
      ];
    }

    return [
      'services' => $preparedServices,
      'channels' => $preparedChannels,
      'statistics' => [
        'service_count' => count($preparedServices),
        'connection_count' => $connectionCount,
        'channel_count' => count($preparedChannels),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function replaceImportData(array $preparedImportData): array {
    $serviceRows = $this->getPreparedRows($preparedImportData, 'services');
    $channelRows = $this->getPreparedRows($preparedImportData, 'channels');

    $transaction = $this->database->startTransaction();

    try {
      $this->database->delete(self::CHANNEL_TABLE)->execute();
      $this->database->delete(self::SERVICE_TABLE)->execute();

      $this->insertServiceRows($serviceRows);
      $this->insertChannelRows($channelRows);
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }

    $statistics = $preparedImportData['statistics'] ?? [];

    return [
      'service_count' => count($serviceRows),
      'connection_count' => is_int($statistics['connection_count'] ?? NULL)
        ? $statistics['connection_count']
        : 0,
      'channel_count' => count($channelRows),
    ];
  }

  /**
   * Inserts or updates selected intermediate storage rows.
   *
   * Unlike replaceImportData(), this does not remove unrelated rows.
   */
  public function upsertImportData(array $preparedImportData): array {
    $serviceRows = $this->getPreparedRows($preparedImportData, 'services');
    $channelRows = $this->getPreparedRows($preparedImportData, 'channels');

    $transaction = $this->database->startTransaction();

    try {
      $this->upsertServiceRows($serviceRows);
      $this->upsertChannelRows($channelRows);
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }

    $statistics = $preparedImportData['statistics'] ?? [];

    return [
      'service_count' => count($serviceRows),
      'connection_count' => is_int($statistics['connection_count'] ?? NULL)
        ? $statistics['connection_count']
        : 0,
      'channel_count' => count($channelRows),
    ];
  }

  /**
   * Inserts prepared service rows.
   *
   * @param array<string, array<string, mixed>> $rows
   *   Prepared service rows keyed by content ID.
   */
  private function insertServiceRows(array $rows): void {
    foreach (array_chunk($rows, self::WRITE_BATCH_SIZE, TRUE) as $chunk) {
      $insert = $this->database->insert(self::SERVICE_TABLE)
        ->fields(['uuid', 'data']);

      foreach ($chunk as $row) {
        $insert->values([
          $this->getPreparedString($row, 'uuid'),
          $this->getPreparedString($row, 'data'),
        ]);
      }

      $insert->execute();
    }
  }

  /**
   * Inserts prepared channel rows.
   *
   * @param array<string, array<string, mixed>> $rows
   *   Prepared channel rows keyed by content ID.
   */
  private function insertChannelRows(array $rows): void {
    foreach (array_chunk($rows, self::WRITE_BATCH_SIZE, TRUE) as $chunk) {
      $insert = $this->database->insert(self::CHANNEL_TABLE)
        ->fields(['uuid', 'type', 'data']);

      foreach ($chunk as $row) {
        $insert->values([
          $this->getPreparedString($row, 'uuid'),
          $this->getPreparedString($row, 'type'),
          $this->getPreparedString($row, 'data'),
        ]);
      }

      $insert->execute();
    }
  }

  /**
   * Inserts or updates prepared service rows.
   *
   * @param array<string, array<string, mixed>> $rows
   *   Prepared service rows keyed by content ID.
   */
  private function upsertServiceRows(array $rows): void {
    foreach (array_chunk($rows, self::WRITE_BATCH_SIZE, TRUE) as $chunk) {
      $upsert = $this->database->upsert(self::SERVICE_TABLE)
        ->fields(['uuid', 'data'])
        ->key('uuid');

      foreach ($chunk as $row) {
        $upsert->values([
          $this->getPreparedString($row, 'uuid'),
          $this->getPreparedString($row, 'data'),
        ]);
      }

      $upsert->execute();
    }
  }

  /**
   * Inserts or updates prepared channel rows.
   *
   * @param array<string, array<string, mixed>> $rows
   *   Prepared channel rows keyed by content ID.
   */
  private function upsertChannelRows(array $rows): void {
    foreach (array_chunk($rows, self::WRITE_BATCH_SIZE, TRUE) as $chunk) {
      $upsert = $this->database->upsert(self::CHANNEL_TABLE)
        ->fields(['uuid', 'type', 'data'])
        ->key('uuid');

      foreach ($chunk as $row) {
        $upsert->values([
          $this->getPreparedString($row, 'uuid'),
          $this->getPreparedString($row, 'type'),
          $this->getPreparedString($row, 'data'),
        ]);
      }

      $upsert->execute();
    }
  }

  /**
   * Gets areas from a raw service import record.
   *
   * @return array<int, array{code: string, name: array<string, string|null>}>
   */
  private function getAreas(mixed $service, string $id): array {
    if (!is_array($service)) {
      return [];
    }

    return isset($service['areas']) && is_array($service['areas'])
      ? $service['areas']
      : [];
  }

  /**
   * Gets an import collection.
   *
   * @return array<string, mixed>
   *   The requested collection.
   */
  private function getCollection(array $importData, string $key): array {
    $collection = $importData[$key] ?? NULL;

    if (!is_array($collection)) {
      throw new \InvalidArgumentException('Import data does not contain a valid ' . $key . ' collection.');
    }

    return $collection;
  }

  /**
   * Gets a non-empty record ID.
   */
  private function getId(mixed $id, string $type): string {
    if (!is_string($id) || trim($id) === '') {
      throw new \InvalidArgumentException(ucfirst($type) . ' records must be keyed by a content ID.');
    }

    return trim($id);
  }

  /**
   * Gets a generated payload model from an import record.
   */
  private function getPayload(mixed $record, string $id): ModelInterface {
    $payload = is_array($record) ? ($record['payload'] ?? NULL) : NULL;

    if (!$payload instanceof ModelInterface) {
      throw new \InvalidArgumentException('Import record "' . $id . '" does not contain a payload model.');
    }

    return $payload;
  }

  /**
   * Gets generated connection models from an import record.
   *
   * @return \OpenAPI\Client\Model\ModelInterface[]
   *   Connection models.
   */
  private function getConnections(mixed $record, string $id): array {
    $connections = is_array($record) ? ($record['connections'] ?? []) : [];

    if (!is_array($connections)) {
      throw new \InvalidArgumentException('Import record "' . $id . '" does not contain a connections array.');
    }

    foreach ($connections as $connection) {
      if (!$connection instanceof ModelInterface) {
        throw new \InvalidArgumentException('Import record "' . $id . '" contains an invalid connection.');
      }
    }

    return array_values($connections);
  }

  /**
   * Gets optional accessibility information from a channel record.
   *
   * @return array<string, mixed>
   *   Accessibility information keyed by accessibility service point ID.
   */
  private function getAccessibility(
    mixed $record,
    string $id,
  ): array {
    if (!is_array($record)) {
      return [];
    }

    $accessibility = $record['accessibility'] ?? [];

    if (!is_array($accessibility)) {
      throw new \InvalidArgumentException(
        'Import record "'
        . $id
        . '" does not contain a valid accessibility array.'
      );
    }

    foreach ($accessibility as $servicePointId => $languageValues) {
      if (
        !is_string($servicePointId)
        || trim($servicePointId) === ''
      ) {
        throw new \InvalidArgumentException(
          'Import record "'
          . $id
          . '" contains an invalid accessibility service point ID.'
        );
      }

      if (!is_array($languageValues)) {
        throw new \InvalidArgumentException(
          'Import record "'
          . $id
          . '" contains invalid accessibility data for service point "'
          . $servicePointId
          . '".'
        );
      }
    }

    return $accessibility;
  }

  /**
   * Gets the service channel type from a generated channel model.
   */
  private function getChannelType(ModelInterface $channel, string $channelId): string {
    if (!method_exists($channel, 'getServiceChannelType')) {
      throw new \InvalidArgumentException('Channel "' . $channelId . '" does not expose its type.');
    }

    $type = $channel->getServiceChannelType();

    if (!is_string($type) || trim($type) === '') {
      throw new \InvalidArgumentException('Channel "' . $channelId . '" does not contain a type.');
    }

    return trim($type);
  }

  /**
   * Encodes one payload and its related data for intermediate storage.
   *
   * @param \OpenAPI\Client\Model\ModelInterface $payload
   *   The generated payload model.
   * @param \OpenAPI\Client\Model\ModelInterface[] $connections
   *   Generated connection models.
   * @param array<string, mixed> $accessibility
   *   Optional accessibility information.
   * @param array<int, array{code: string, name: array<string, string|null>}> $areas
   *   Optional area details.
   *
   * @return string
   *   The JSON encoded storage record.
   *
   * @throws \RuntimeException
   *   Thrown when JSON encoding fails.
   */
  private function encodeStorageRecord(
    ModelInterface $payload,
    array $connections,
    array $accessibility = [],
    array $areas = [],
  ): string {
    $storageRecord = [
      'payload' => ObjectSerializer::sanitizeForSerialization(
        $payload
      ),
      'connections' => ObjectSerializer::sanitizeForSerialization(
        $connections
      ),
    ];

    if ($accessibility !== []) {
      $storageRecord['accessibility'] = $accessibility;
    }

    if ($areas !== []) {
      $storageRecord['areas'] = $areas;
    }

    try {
      return json_encode(
        $storageRecord,
        JSON_THROW_ON_ERROR
        | JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
      );
    }
    catch (\JsonException $exception) {
      throw new \RuntimeException(
        'Unable to encode PTV intermediate storage JSON.',
        0,
        $exception
      );
    }
  }

  /**
   * Gets prepared rows for one storage collection.
   *
   * @return array<string, array<string, mixed>>
   *   Prepared rows keyed by content ID.
   */
  private function getPreparedRows(array $preparedImportData, string $key): array {
    $rows = $preparedImportData[$key] ?? NULL;

    if (!is_array($rows)) {
      throw new \InvalidArgumentException('Prepared import data does not contain a valid ' . $key . ' collection.');
    }

    return $rows;
  }

  /**
   * Gets a required string from one prepared row.
   */
  private function getPreparedString(array $row, string $key): string {
    $value = $row[$key] ?? NULL;

    if (!is_string($value) || trim($value) === '') {
      throw new \InvalidArgumentException('Prepared storage row does not contain a valid ' . $key . ' value.');
    }

    return trim($value);
  }

}