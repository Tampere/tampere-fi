<?php

use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\Core\Database\Database;

/**
 * Delete all over saved revisions for a specific node (by nid).
 */
function delete_over_saved_revisions() {
  $database = Database::getConnection();

  $nids = get_nids_with_over_saved_revisions();

  foreach ($nids as $nid) {

    $node = Node::load($nid);
    if (!($node instanceof NodeInterface)) {
      continue;
    }

    $vids = \Drupal::entityTypeManager()->getStorage('node')->revisionIds($node);
    $remove_vids = array_slice($vids, 20);
    $current_revision_vid = $node->getRevisionId();
    $remove_vids = array_diff($remove_vids, [$current_revision_vid]);

    $total_revisions = count($remove_vids);
    $current_revision = 0;

    if (empty($remove_vids)) {
      continue;
    }

    foreach (array_chunk($remove_vids, 50) as $batch) {
      $database->delete('node_revision')
        ->condition('vid', $batch, 'IN')
        ->execute();

      $database->delete('node_field_revision')
        ->condition('vid', $batch, 'IN')
        ->execute();

      $current_revision += count($batch);
      $percentage = $current_revision / $total_revisions * 100;
      echo "\r # Deleting Over Saved Revisions For Node ID {$nid}: " . number_format($percentage, 2) . "%";
      flush();
    }

    echo PHP_EOL;
    print("**");
  }
}

/**
 * Get all node IDs that have over saved revisions.
 *
 * @return array
 *   An array of node IDs (nids).
 */
function get_nids_with_over_saved_revisions() {
  $database = Database::getConnection();
  $query = $database->select('node_revision', 'nr')
    ->fields('nr', ['nid'])
    ->groupBy('nr.nid')
    ->having('COUNT(nr.vid) > :revision_count', [':revision_count' => 100]);

  $result = $query->execute()->fetchCol();
  return $result;
}

delete_over_saved_revisions();
