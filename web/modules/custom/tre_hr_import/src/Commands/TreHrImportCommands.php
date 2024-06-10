<?php

namespace Drupal\tre_hr_import\Commands;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
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
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $db;

  /**
   * Constructs the commands object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, Connection $database) {
    $this->nodeStorage = $entity_type_manager->getStorage('node');
    $this->db = $database;
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
    // Any person node imported from CSV will have the field_hr_id field
    // populated. The import always imports the content in Finnish, so checking
    // only Finnish content.
    $query = $this->nodeStorage->getQuery()->accessCheck(FALSE)
      ->condition('type', 'person')
      ->condition('langcode', 'fi')
      ->condition('default_langcode', 1)
      ->exists('field_hr_id', 'fi');
    $person_count = $query->count()->execute();

    if ($person_count == 0) {
      $person_count = 1;
    }

    $percentage = floor(100 * $number_of_lines_in_import_csv / $person_count);

    $this->io()->writeln($percentage);
  }

  /**
   * Deleting all person nodes that are not referrenced anywhere.
   *
   * @usage tre_hr_import-remove_all_unused_person_nodes
   *   Deleting all person nodes that are not referrenced anywhere.
   *
   * @command tre_hr_import:remove_all_unused_person_nodes
   * @aliases hr_rm_person_nodes
   */
  public function removeAllUnusedPersonNodes() {
    $query = $this->db->select('node', 'n');

    // Get ids of all person nodes that are not referrenced anywhere.
    $query->leftJoin('paragraph__field_person_liftup', 'pfpl', 'pfpl.field_person_liftup_target_id = n.nid');
    $query->leftJoin('paragraph__field_media_contact_person', 'pfmcp', 'pfmcp.field_media_contact_person_target_id = n.nid');
    $query->leftJoin('paragraph__field_internal_link', 'pfil', 'pfil.field_internal_link_target_id = n.nid');
    $query->fields('n', ['nid'])
      ->condition('n.type', 'person', '=')
      ->condition('pfpl.field_person_liftup_target_id', NULL, 'IS NULL')
      ->condition('pfmcp.field_media_contact_person_target_id', NULL, 'IS NULL')
      ->condition('pfil.field_internal_link_target_id', NULL, 'IS NULL');
    $result = $query->execute()->fetchAll();
    foreach ($result as $row) {
      $node = $this->nodeStorage->load($row->nid);
      if ($node instanceof NodeInterface) {
        // The published status lives in different table (node_field_data)
        // so we're doing the check here instead.
        // If any of the translations is published, don't delete.
        $isTranslationPublished = FALSE;
        foreach ($node->getTranslationLanguages() as $langcode => $language) {
          if ($node->hasTranslation($langcode)) {
            $translation = $node->getTranslation($langcode);
            if ($translation instanceof NodeInterface) {
              if ($translation->isPublished()) {
                $isTranslationPublished = TRUE;
              }
            }

          }
        }
        if (!$isTranslationPublished) {
          $node->delete();
          $this->io()->writeln(sprintf('Node %u is deleted.', $row->nid));
        }
      }
    }
    $this->io()->writeln('Finish!');
  }

}
