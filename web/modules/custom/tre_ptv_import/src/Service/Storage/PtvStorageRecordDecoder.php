<?php

declare(strict_types=1);

namespace Drupal\tre_ptv_import\Service\Storage;

/**
 * Decodes and validates PTV intermediate storage records.
 */
final class PtvStorageRecordDecoder {

  /**
   * Decodes one intermediate storage record.
   *
   * @param string $encodedData
   *   The JSON stored in a data column.
   *
   * @return array{
   *   payload: array<string, mixed>,
   *   connections: array<int, array<string, mixed>>,
   *   accessibility: array<string, mixed>,
   *   areas: array<int, array{code: string, name: array<string, string|null>}>
   * }
   *   The decoded and validated storage record.
   */
  public static function decode(string $encodedData): array {
    try {
      $record = json_decode($encodedData, TRUE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException $exception) {
      throw new \RuntimeException(
        'Unable to decode PTV intermediate storage JSON: ' . $exception->getMessage(),
        0,
        $exception
      );
    }

    if (!is_array($record)) {
      throw new \RuntimeException('PTV intermediate storage data must decode into an array.');
    }

    $payload = $record['payload'] ?? NULL;
    if (!is_array($payload)) {
      throw new \RuntimeException('PTV intermediate storage data does not contain a payload array.');
    }

    $connections = $record['connections'] ?? NULL;
    if (!is_array($connections)) {
      throw new \RuntimeException('PTV intermediate storage data does not contain a connections array.');
    }

    foreach ($connections as $index => $connection) {
      if (!is_array($connection)) {
        throw new \RuntimeException('PTV intermediate storage connection at index ' . $index . ' is not an array.');
      }
    }

    $accessibility = $record['accessibility'] ?? [];
    if (!is_array($accessibility)) {
      throw new \RuntimeException('PTV intermediate storage accessibility data must be an array.');
    }

    foreach ($accessibility as $servicePointId => $languageValues) {
      if (!is_string($servicePointId) || trim($servicePointId) === '') {
        throw new \RuntimeException(
          'PTV intermediate storage accessibility data contains an invalid service point ID.'
        );
      }

      if (!is_array($languageValues)) {
        throw new \RuntimeException(
          'PTV intermediate storage accessibility data for service point "'
          . $servicePointId
          . '" must be an array.'
        );
      }
    }

    $areas = $record['areas'] ?? [];
    if (!is_array($areas)) {
      throw new \RuntimeException('PTV intermediate storage areas data must be an array.');
    }

    foreach ($areas as $index => $area) {
      if (!is_array($area)) {
        throw new \RuntimeException('PTV intermediate storage area at index ' . $index . ' is not an array.');
      }
    }

    return [
      'payload' => $payload,
      'connections' => $connections,
      'accessibility' => $accessibility,
      'areas' => $areas,
    ];
  }

  /**
   * Gets the localized payload section for one language.
   *
   * No fallback language is used. A migration row is skipped for a locale when
   * its required localized name is absent.
   *
   * @param array<string, mixed> $payload
   *   The decoded entity payload.
   * @param string $language
   *   The requested locale, for example fi, en or sv.
   *
   * @return array<string, mixed>|null
   *   The localized version, or NULL when it does not exist.
   */
  public static function getLocalizedVersion(array $payload, string $language): ?array {
    $language = trim($language);
    if ($language === '') {
      throw new \InvalidArgumentException('The requested content language cannot be empty.');
    }

    $languageVersions = $payload['languageVersions'] ?? NULL;
    if ($languageVersions === NULL) {
      return NULL;
    }
    if (!is_array($languageVersions)) {
      throw new \RuntimeException('PTV payload languageVersions must be an array.');
    }

    $localizedVersion = $languageVersions[$language] ?? NULL;
    if ($localizedVersion === NULL) {
      return NULL;
    }
    if (!is_array($localizedVersion)) {
      throw new \RuntimeException('PTV languageVersions.' . $language . ' must be an array.');
    }

    return $localizedVersion;
  }

  /**
   * Gets one localized string value from a payload.
   *
   * @param array<string, mixed> $payload
   *   The decoded entity payload.
   * @param string $language
   *   The requested locale.
   * @param string $key
   *   The language-version key, for example name or description.
   *
   * @return string|null
   *   The localized string, or NULL when no value exists.
   */
  public static function getLocalizedString(array $payload, string $language, string $key): ?string {
    $localizedVersion = self::getLocalizedVersion($payload, $language);
    if ($localizedVersion === NULL) {
      return NULL;
    }

    $value = $localizedVersion[$key] ?? NULL;
    if ($value === NULL) {
      return NULL;
    }
    if (!is_string($value)) {
      throw new \RuntimeException('PTV languageVersions.' . $language . '.' . $key . ' must be a string or null.');
    }

    $value = trim($value);
    return $value === '' ? NULL : $value;
  }

  /**
   * Gets a top-level array of strings from a payload.
   *
   * @param array<string, mixed> $payload
   *   The decoded entity payload.
   * @param string $key
   *   The payload key.
   *
   * @return string[]
   *   Unique non-empty strings in their original order.
   */
  public static function getStringList(array $payload, string $key): array {
    $value = $payload[$key] ?? NULL;
    if ($value === NULL) {
      return [];
    }
    if (!is_array($value)) {
      throw new \RuntimeException('PTV payload.' . $key . ' must be an array.');
    }

    $strings = [];
    foreach ($value as $item) {
      if (!is_string($item)) {
        throw new \RuntimeException('PTV payload.' . $key . ' must contain only strings.');
      }

      $item = trim($item);
      if ($item !== '') {
        $strings[$item] = $item;
      }
    }

    return array_values($strings);
  }

  /**
   * Gets unique channel IDs from a service record's connections.
   *
   * @param array<int, array<string, mixed>> $connections
   *   Connection records from a decoded service storage record.
   *
   * @return string[]
   *   Unique channel content IDs.
   */
  public static function getChannelIdsFromConnections(array $connections): array {
    return self::getConnectionIds($connections, 'serviceChannelContentId');
  }

  /**
   * Gets unique service IDs from a channel record's connections.
   *
   * @param array<int, array<string, mixed>> $connections
   *   Connection records from a decoded channel storage record.
   *
   * @return string[]
   *   Unique service content IDs.
   */
  public static function getServiceIdsFromConnections(array $connections): array {
    return self::getConnectionIds($connections, 'serviceContentId');
  }

  /**
   * Gets a required service channel type.
   *
   * @param array<string, mixed> $payload
   *   The decoded channel payload.
   *
   * @return string
   *   The service channel type.
   */
  public static function getChannelType(array $payload): string {
    return self::getRequiredString($payload, 'serviceChannelType');
  }

  /**
   * Gets unique IDs from connection records.
   *
   * @param array<int, array<string, mixed>> $connections
   *   Connection records.
   * @param string $key
   *   The connection key to read.
   *
   * @return string[]
   *   Unique non-empty IDs.
   */
  private static function getConnectionIds(array $connections, string $key): array {
    $ids = [];

    foreach ($connections as $index => $connection) {
      $id = self::getRequiredString($connection, $key, 'PTV connection at index ' . $index);
      $ids[$id] = $id;
    }

    return array_values($ids);
  }

  /**
   * Gets a required non-empty string from a structure.
   *
   * @param array<string, mixed> $data
   *   The structure containing the value.
   * @param string $key
   *   The required key.
   * @param string|null $label
   *   Optional human-readable label for exception messages.
   *
   * @return string
   *   The normalized string value.
   */
  private static function getRequiredString(array $data, string $key, ?string $label = NULL): string {
    $value = $data[$key] ?? NULL;

    if (!is_string($value) || trim($value) === '') {
      throw new \RuntimeException(($label ?? 'PTV data') . ' does not contain a valid ' . $key . ' value.');
    }

    return trim($value);
  }

}