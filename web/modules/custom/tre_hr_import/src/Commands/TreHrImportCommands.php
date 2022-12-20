<?php

namespace Drupal\tre_hr_import\Commands;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Commands\DrushCommands;

/**
 * Drush commandfile for the tre_hr_import module.
 */
class TreHrImportCommands extends DrushCommands {

  /**
   * The entity storage service.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $nodeStorage;

  /**
   * Constructs the commands object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->nodeStorage = $entity_type_manager->getStorage('node');
  }

  /**
   * Calculates the percentage of new lines against existing person nodes.
   *
   * @param int $number_of_lines_in_import_csv
   *   The amount of new lines to compare against the number of existing nodes.
   *
   * @usage tre_hr_import-calculate_persons_percentage 11000
   *   Calculates the percentage (in %, rounded down) of nodes that the amount
   *   of new lines to import (11000 in this example) comprises.
   *
   * @command tre_hr_import:calculate_persons_percentage
   * @aliases hr_import_pct,hr_import_percentage
   */
  public function calculatePersonsPercentageToImport(int $number_of_lines_in_import_csv) {
    $query = $this->nodeStorage->getQuery()->accessCheck(FALSE);
    $query = $query->condition('type', 'person');
    $person_count = $query->count()->execute();

    if ($person_count == 0) {
      $person_count = 1;
    }

    $percentage = floor(100 * $number_of_lines_in_import_csv / $person_count);

    $this->io()->writeln($percentage);
  }

}
