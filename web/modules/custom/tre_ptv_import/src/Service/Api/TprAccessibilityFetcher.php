<?php

declare(strict_types=1);

namespace Drupal\tre_ptv_import\Service\Api;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Fetches and maps accessibility sentences from the TPR API.
 */
final class TprAccessibilityFetcher {

  /**
   * Languages supported by the website migrations.
   */
  private const LANGUAGES = [
    'fi',
    'en',
  ];

  /**
   * The module logger.
   */
  private LoggerChannelInterface $logger;

  /**
   * Constructs the TPR accessibility fetcher.
   */
  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly PtvApiSettings $settings,
    LoggerChannelFactoryInterface $loggerFactory,
  ) {
    $this->logger = $loggerFactory->get('tre_ptv_import');
  }

  /**
   * Fetches and maps accessibility sentences for one service point.
   *
   * @param string $servicePointId
   *   The accessibility register service point ID received from PTV.
   *
   * @return array{
   *   fi: array<int, array{
   *     title: string,
   *     sentences: string[]
   *   }>,
   *   en: array<int, array{
   *     title: string,
   *     sentences: string[]
   *   }>
   * }
   *   Accessibility information grouped by language and title.
   */
  public function fetch(string $servicePointId): array {
    $servicePointId = trim($servicePointId);

    if ($servicePointId === '') {
      throw new \InvalidArgumentException(
        'The TPR accessibility service point ID cannot be empty.'
      );
    }

    $url = $this->buildRequestUrl($servicePointId);

    try {
      $response = $this->httpClient->request(
        'GET',
        $url,
        [
          'headers' => [
            'Accept' => 'application/json',
          ],
          'http_errors' => FALSE,
          'timeout' => $this->settings->getRequestTimeout(),
        ]
      );
    }
    catch (GuzzleException $exception) {
      throw new \RuntimeException(
        'TPR accessibility API request failed for service point "'
        . $servicePointId
        . '": '
        . $exception->getMessage(),
        0,
        $exception
      );
    }

    $statusCode = $response->getStatusCode();

    if ($statusCode === 404) {
      $this->logger->notice(
        'TPR accessibility API did not find service point @service_point_id.',
        [
          '@service_point_id' => $servicePointId,
        ]
      );

      return $this->getEmptyResult();
    }

    if ($statusCode < 200 || $statusCode >= 300) {
      throw new \RuntimeException(
        'TPR accessibility API returned HTTP '
        . $statusCode
        . ' for service point "'
        . $servicePointId
        . '".'
      );
    }

    $responseBody = trim((string) $response->getBody());

    if ($responseBody === '') {
      return $this->getEmptyResult();
    }

    try {
      $rows = json_decode(
        $responseBody,
        TRUE,
        512,
        JSON_THROW_ON_ERROR
      );
    }
    catch (\JsonException $exception) {
      throw new \RuntimeException(
        'TPR accessibility API returned invalid JSON for service point "'
        . $servicePointId
        . '": '
        . $exception->getMessage(),
        0,
        $exception
      );
    }

    if (!is_array($rows)) {
      throw new \RuntimeException(
        'TPR accessibility API response for service point "'
        . $servicePointId
        . '" did not decode into an array.'
      );
    }

    return $this->mapResponse($rows, $servicePointId);
  }

  /**
   * Builds the API request URL for one service point.
   */
  private function buildRequestUrl(string $servicePointId): string {
    return $this->settings->getTprAccessibilityApiUrl()
      . '/'
      . rawurlencode($this->settings->getTprAccessibilitySystemId())
      . '/'
      . rawurlencode($servicePointId)
      . '/sentences';
  }

  /**
   * Maps the TPR response into the existing accessibility JSON structure.
   *
   * Each TPR response row contains one sentence. Rows with the same entrance
   * and localized sentence group title are combined into one title group.
   *
   * @param array<int, mixed> $rows
   *   Decoded TPR response rows.
   * @param string $servicePointId
   *   The requested accessibility service point ID.
   *
   * @return array{
   *   fi: array<int, array{
   *     title: string,
   *     sentences: string[]
   *   }>,
   *   en: array<int, array{
   *     title: string,
   *     sentences: string[]
   *   }>
   * }
   *   Mapped accessibility information.
   */
  private function mapResponse(
    array $rows,
    string $servicePointId,
  ): array {
    $groups = [
      'fi' => [],
      'en' => [],
    ];

    foreach (array_values($rows) as $rowIndex => $row) {
      if (!is_array($row)) {
        throw new \RuntimeException(
          'TPR accessibility API response for service point "'
          . $servicePointId
          . '" contains an invalid row at index '
          . $rowIndex
          . '.'
        );
      }

      $entranceId = $this->getEntranceId($row);
      $sentenceOrder = $this->getOptionalString(
        $row,
        'sentenceOrderText'
      );

      foreach (self::LANGUAGES as $language) {
        $title = $this->getLocalizedValue(
          $row['sentenceGroups'] ?? NULL,
          $language
        );

        $sentence = $this->getLocalizedValue(
          $row['sentences'] ?? NULL,
          $language
        );

        if ($title === NULL || $sentence === NULL) {
          continue;
        }

        $groupKey = $entranceId . "\0" . $title;

        if (!isset($groups[$language][$groupKey])) {
          $groups[$language][$groupKey] = [
            'title' => $title,
            'sentences' => [],
          ];
        }

        $groups[$language][$groupKey]['sentences'][] = [
          'value' => $sentence,
          'order' => $sentenceOrder,
          'source_index' => $rowIndex,
        ];
      }
    }

    return [
      'fi' => $this->finalizeLanguageGroups($groups['fi']),
      'en' => $this->finalizeLanguageGroups($groups['en']),
    ];
  }

  /**
   * Sorts sentences and removes internal mapping metadata.
   *
   * @param array<string, array{
   *   title: string,
   *   sentences: array<int, array{
   *     value: string,
   *     order: string|null,
   *     source_index: int
   *   }>
   * }> $groups
   *   Internally grouped accessibility values.
   *
   * @return array<int, array{
   *   title: string,
   *   sentences: string[]
   * }>
   *   Final v11-compatible accessibility groups.
   */
  private function finalizeLanguageGroups(array $groups): array {
    $result = [];

    foreach ($groups as $group) {
      $sentenceRows = $group['sentences'];

      usort(
        $sentenceRows,
        static function (array $left, array $right): int {
          $leftOrder = $left['order'];
          $rightOrder = $right['order'];

          if (
            is_string($leftOrder)
            && $leftOrder !== ''
            && is_string($rightOrder)
            && $rightOrder !== ''
          ) {
            $comparison = strcmp($leftOrder, $rightOrder);

            if ($comparison !== 0) {
              return $comparison;
            }
          }

          return $left['source_index'] <=> $right['source_index'];
        }
      );

      $sentences = [];

      foreach ($sentenceRows as $sentenceRow) {
        $sentences[] = $sentenceRow['value'];
      }

      if ($sentences === []) {
        continue;
      }

      $result[] = [
        'title' => $group['title'],
        'sentences' => $sentences,
      ];
    }

    return $result;
  }

  /**
   * Gets one localized string from a TPR language-value collection.
   */
  private function getLocalizedValue(
    mixed $localizedValues,
    string $language,
  ): ?string {
    if (!is_array($localizedValues)) {
      return NULL;
    }

    foreach ($localizedValues as $localizedValue) {
      if (!is_array($localizedValue)) {
        continue;
      }

      $itemLanguage = $localizedValue['language'] ?? NULL;
      $value = $localizedValue['value'] ?? NULL;

      if (
        $itemLanguage !== $language
        || !is_string($value)
        || trim($value) === ''
      ) {
        continue;
      }

      return trim($value);
    }

    return NULL;
  }

  /**
   * Gets the entrance ID used for keeping sentence groups separate.
   */
  private function getEntranceId(array $row): string {
    $entranceId = $row['entranceId'] ?? NULL;

    if (is_int($entranceId) || is_string($entranceId)) {
      return trim((string) $entranceId);
    }

    return '';
  }

  /**
   * Gets an optional normalized string from a response row.
   */
  private function getOptionalString(
    array $row,
    string $key,
  ): ?string {
    $value = $row[$key] ?? NULL;

    if (!is_string($value)) {
      return NULL;
    }

    $value = trim($value);

    return $value === '' ? NULL : $value;
  }

  /**
   * Gets an empty language result.
   *
   * @return array{
   *   fi: array<int, array{
   *     title: string,
   *     sentences: string[]
   *   }>,
   *   en: array<int, array{
   *     title: string,
   *     sentences: string[]
   *   }>
   * }
   *   Empty accessibility information.
   */
  private function getEmptyResult(): array {
    return [
      'fi' => [],
      'en' => [],
    ]; 
  }

}