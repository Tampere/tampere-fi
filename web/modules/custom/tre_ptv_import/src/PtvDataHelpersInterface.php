<?php

declare(strict_types=1);

namespace Drupal\tre_ptv_import;

/**
 * Interface for data helpers for PTV data.
 */
interface PtvDataHelpersInterface {

  /**
   * Processes service hours into the existing migration source fields.
   *
   * @param array $values
   *   The values array to transform to include information about the service
   *   hours.
   * @param mixed $serviceHours
   *   The generated service hours DTO, an empty array, or NULL.
   * @param string $language
   *   The language that should be in use for display texts.
   */
  public static function processServiceHours(array &$values, mixed $serviceHours, string $language): void;

}