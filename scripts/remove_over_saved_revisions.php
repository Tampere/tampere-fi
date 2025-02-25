<?php

use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\Core\Database\Database;

/**
 * Delete all over saved revisions for a specific node (by nid).
 */
function delete_over_saved_revisions() {

  $nids = get_nids_with_over_saved_revisions();

  foreach ($nids as $nid) {

    $node = Node::load($nid);
    if (!($node instanceof NodeInterface)) {
      continue;
    }

    $vids = \Drupal::entityTypeManager()->getStorage('node')->revisionIds($node);
    $remove_vids = array_slice($vids, 20);
    $current_revision_vid = $node->getRevisionId();

    $total_revisions = count($remove_vids);
    $current_revision = 0;
    foreach ($remove_vids as $vid) {
      $percentage = $current_revision / $total_revisions * 100;
      echo "\r # Deleting Over Saved Revisions For Node id " . $nid . " : " . number_format($percentage, 2) . "%";
      $current_revision += 1;
      flush();

      if ($vid != $current_revision_vid) {
        \Drupal::entityTypeManager()->getStorage('node')->deleteRevision($vid);
      }
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
