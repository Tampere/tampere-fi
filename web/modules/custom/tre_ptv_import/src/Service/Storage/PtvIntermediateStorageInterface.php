<?php

declare(strict_types=1);

namespace Drupal\tre_ptv_import\Service\Storage;

/**
 * Defines intermediate storage operations for PTV import data.
 */
interface PtvIntermediateStorageInterface {

  /**
   * Prepares fetched API data for intermediate storage.
   *
   * Converts generated DTO payloads and connection data into JSON-compatible
   * storage rows. Migration source plugins later decode the stored JSON and
   * deserialize payloads back into generated DTOs for migration mapping.
   *
   * This method DOES NOT write to the database.
   *
   * @param array<string, mixed> $importData
   *   Fetched and validated API data.
   *
   * @return array<string, mixed>
   *   Prepared storage rows and import statistics.
   */
  public function prepareImportDataForStorage(array $importData): array;

  /**
   * Replaces all intermediate storage rows with prepared import data.
   *
   * @param array<string, mixed> $preparedImportData
   *   Prepared storage rows.
   *
   * @return array<string, int>
   *   Written import statistics.
   */
  public function replaceImportData(array $preparedImportData): array;

  /**
   * Inserts or updates only the supplied intermediate storage rows.
   *
   * Unlike replaceImportData(), this leaves unrelated rows untouched.
   *
   * @param array<string, mixed> $preparedImportData
   *   Prepared storage rows.
   *
   * @return array<string, int>
   *   Written import statistics.
   */
  public function upsertImportData(array $preparedImportData): array;

}