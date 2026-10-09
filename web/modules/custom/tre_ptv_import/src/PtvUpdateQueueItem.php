<?php

declare(strict_types=1);

namespace Drupal\tre_ptv_import;

/**
 * Queue Item model class for PTV updates.
 *
 * The idea is that whenever a PTV item needs an update, a queue item is created
 * using this class, marking the need for the update for the items by adding the
 * UUIDs of the items in the correct property of the instance. The langcode is
 * needed too because otherwise the queue worker would not know which migration
 * to use for the update.
 *
 * The choice of storage format as strings as opposed to full objects is
 * intentional to keep the serialization light.
 */
final class PtvUpdateQueueItem {

  /**
   * Holds the service UUIDs needing update.
   *
   * @var string[]
   */
  private array $services = [];

  /**
   * Holds the UUIDs of service locations needing update.
   *
   * @var string[]
   */
  private array $serviceLocations = [];

  /**
   * Holds the UUIDs of service channels other than locations needing update.
   *
   * @var string[]
   */
  private array $serviceChannels = [];

  /**
   * The language code for the update migration needed.
   */
  private string $langcode;

  /**
   * The constructor.
   */
  public function __construct(string $langcode) {
    $this->langcode = $langcode;
  }

  /**
   * Getter for the langcode property.
   *
   * @return string
   *   The langcode for the migration.
   */
  public function getLangcode(): string {
    return $this->langcode;
  }

  /**
   * Setter for the service UUIDs.
   *
   * @param string[] $services
   *   The UUIDs for the services to update.
   */
  public function setServices(array $services): void {
    $nonStrings = array_filter(
      $services,
      static fn (mixed $item): bool => !is_string($item)
    );

    if (!empty($nonStrings)) {
      throw new \InvalidArgumentException('Only service id strings should be set.');
    }

    $this->services = $services;
  }

  /**
   * Setter for the service location UUIDs.
   *
   * @param string[] $serviceLocations
   *   The UUIDs for the service locations to update.
   */
  public function setServiceLocations(array $serviceLocations): void {
    $nonStrings = array_filter(
      $serviceLocations,
      static fn (mixed $item): bool => !is_string($item)
    );

    if (!empty($nonStrings)) {
      throw new \InvalidArgumentException('Only service location id strings should be set.');
    }

    $this->serviceLocations = $serviceLocations;
  }

  /**
   * Setter for the service channel UUIDs.
   *
   * @param string[] $serviceChannels
   *   The UUIDs for the service channels, other than service locations, to
   *   update.
   */
  public function setServiceChannels(array $serviceChannels): void {
    $nonStrings = array_filter(
      $serviceChannels,
      static fn (mixed $item): bool => !is_string($item)
    );

    if (!empty($nonStrings)) {
      throw new \InvalidArgumentException('Only service channel id strings should be set.');
    }

    $this->serviceChannels = $serviceChannels;
  }

  /**
   * Getter for service UUIDs.
   *
   * @return string[]
   *   The service UUIDs.
   */
  public function getServices(): array {
    return $this->services;
  }

  /**
   * Getter for service location UUIDs.
   *
   * @return string[]
   *   The service location UUIDs.
   */
  public function getServiceLocations(): array {
    return $this->serviceLocations;
  }

  /**
   * Getter for service channel UUIDs.
   *
   * @return string[]
   *   The service channel UUIDs.
   */
  public function getServiceChannels(): array {
    return $this->serviceChannels;
  }

}