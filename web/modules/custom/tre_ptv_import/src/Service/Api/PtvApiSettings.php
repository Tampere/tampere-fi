<?php

declare(strict_types=1);

namespace Drupal\tre_ptv_import\Service\Api;

use Drupal\Core\Site\Settings;

/**
 * Provides validated settings for the PTV and TPR API integrations.
 */
final class PtvApiSettings {

  /**
   * The key used for module-specific settings.
   */
  private const SETTINGS_KEY = 'tre_ptv_import';

  /**
   * Gets the PTV API host.
   */
  public function getApiHost(): string {
    return rtrim($this->getRequiredString('api_host'), '/');
  }

  /**
   * Gets the PTV API key.
   */
  public function getApiKey(): string {
    return $this->getRequiredString('api_key');
  }

  /**
   * Gets the organization content ID used for service searches.
   */
  public function getOrganizationContentId(): string {
    return $this->getRequiredString('organization_content_id');
  }

  /**
   * Gets the TPR accessibility register system ID.
   */
  public function getTprAccessibilitySystemId(): string {
    return $this->getRequiredString('tpr_accessibility_api_id');
  }

  /**
   * Gets the TPR accessibility API base URL.
   */
  public function getTprAccessibilityApiUrl(): string {
    return rtrim(
      $this->getRequiredString('tpr_acceesibility_api_url'),
      '/'
    );
  }

  /**
   * Gets the HTTP request timeout in seconds.
   */
  public function getRequestTimeout(): float {
    $value = $this->getSettings()['request_timeout'] ?? 60;

    if (!is_numeric($value) || (float) $value <= 0) {
      throw new \RuntimeException(
        'The "'
        . self::SETTINGS_KEY
        . '.request_timeout" setting must be a positive number.'
      );
    }

    return (float) $value;
  }

  /**
   * Gets a required non-empty string setting.
   */
  private function getRequiredString(string $key): string {
    $value = $this->getSettings()[$key] ?? NULL;

    if (!is_string($value) || trim($value) === '') {
      throw new \RuntimeException(
        'Missing required settings.php value "'
        . self::SETTINGS_KEY
        . '.'
        . $key
        . '".'
      );
    }

    return trim($value);
  }

  /**
   * Gets the module-specific settings array.
   *
   * @return array<string, mixed>
   *   The configured values.
   */
  private function getSettings(): array {
    $settings = Settings::get(self::SETTINGS_KEY, []);

    if (!is_array($settings)) {
      throw new \RuntimeException(
        'The "'
        . self::SETTINGS_KEY
        . '" settings.php value must be an array.'
      );
    }

    return $settings;
  }

}
