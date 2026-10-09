<?php

declare(strict_types=1);

namespace Drupal\tre_ptv_import\Service\Migration;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\tre_ptv_import\Service\Api\PtvApiFetcher;

/**
 * Resolves localized organization names from the PTV API.
 */
final class PtvOrganizationNameResolver {

  /**
   * The cache ID prefix reserved for organization payloads.
   */
  private const CACHE_ID_PREFIX = 'tre_ptv_import_organization__';

  /**
   * Cache lifetime in seconds.
   */
  private const CACHE_LIFETIME = 900;

  /**
   * Supported organization language version getters.
   */
  private const LANGUAGE_GETTERS = [
    'fi' => 'getFi',
    'en' => 'getEn',
  ];

  /**
   * Constructs the organization name resolver.
   */
  public function __construct(
    private readonly PtvApiFetcher $fetcher,
    private readonly CacheBackendInterface $cache,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Gets an organization name in the requested language.
   *
   * Falls back to Finnish, English, Swedish, and then the other supported
   * language versions when the requested language is unavailable.
   *
   * @param string $organizationContentId
   *   The organization content ID.
   * @param string $language
   *   The requested language code.
   *
   * @return string|null
   *   The resolved organization name, or NULL when no usable name exists.
   */
  public function getName(
    string $organizationContentId,
    string $language,
  ): ?string {
    $organizationContentId = trim($organizationContentId);
    $language = trim($language);

    if ($organizationContentId === '') {
      throw new \InvalidArgumentException(
        'The organization content ID cannot be empty.'
      );
    }

    if ($language === '') {
      throw new \InvalidArgumentException(
        'The requested organization language cannot be empty.'
      );
    }

    return $this->getLocalizedName(
      $this->getOrganization($organizationContentId),
      $language
    );
  }

  /**
   * Gets organization names for multiple organization IDs.
   *
   * @param string[] $organizationContentIds
   *   Organization content IDs.
   * @param string $language
   *   The requested language code.
   *
   * @return string[]
   *   Unique non-empty organization names in input order.
   */
  public function getNames(
    array $organizationContentIds,
    string $language,
  ): array {
    $names = [];

    foreach ($organizationContentIds as $organizationContentId) {
      if (!is_string($organizationContentId)) {
        throw new \InvalidArgumentException(
          'All organization content IDs must be strings.'
        );
      }

      $name = $this->getName($organizationContentId, $language);

      if ($name !== NULL) {
        $names[$name] = $name;
      }
    }

    return array_values($names);
  }

  /**
   * Gets an organization DTO from cache or the PTV API.
   */
  private function getOrganization(
    string $organizationContentId,
  ): object {
    $cacheId = self::CACHE_ID_PREFIX . $organizationContentId;
    $cached = $this->cache->get($cacheId);

    if (isset($cached->data) && is_object($cached->data)) {
      return $cached->data;
    }

    if ($cached !== FALSE) {
      $this->cache->delete($cacheId);
    }

    $organization = $this->fetcher->fetchOrganization(
      $organizationContentId
    );

    $this->cache->set(
      $cacheId,
      $organization,
      $this->time->getCurrentTime() + self::CACHE_LIFETIME
    );

    return $organization;
  }

  /**
   * Gets the best available localized organization name.
   */
  private function getLocalizedName(
    object $organization,
    string $requestedLanguage,
  ): ?string {
    $languageVersions = $this->getObjectFromGetter(
      $organization,
      'getLanguageVersions'
    );

    if ($languageVersions === NULL) {
      throw new \RuntimeException(
        'PTV organization response does not contain languageVersions.'
      );
    }

    $fallbackLanguages = array_values(array_unique([
      $requestedLanguage,
      'fi',
      'en',
    ]));

    foreach ($fallbackLanguages as $language) {
      $name = $this->getNameFromLanguageVersion(
        $languageVersions,
        $language
      );

      if ($name !== NULL) {
        return $name;
      }
    }

    return NULL;
  }

  /**
   * Gets a normalized name from one localized organization DTO.
   */
  private function getNameFromLanguageVersion(
    object $languageVersions,
    string $language,
  ): ?string {
    $getter = self::LANGUAGE_GETTERS[$language] ?? NULL;

    if ($getter === NULL || !method_exists($languageVersions, $getter)) {
      return NULL;
    }

    $languageVersion = $languageVersions->{$getter}();

    if (
      !is_object($languageVersion)
      || !method_exists($languageVersion, 'getName')
    ) {
      return NULL;
    }

    $name = $languageVersion->getName();

    if (!is_string($name)) {
      return NULL;
    }

    $name = trim($name);

    return $name === '' ? NULL : $name;
  }

  /**
   * Gets an object value from a generated DTO getter.
   */
  private function getObjectFromGetter(
    object $model,
    string $getter,
  ): ?object {
    if (!method_exists($model, $getter)) {
      return NULL;
    }

    $value = $model->{$getter}();

    return is_object($value) ? $value : NULL;
  }

}