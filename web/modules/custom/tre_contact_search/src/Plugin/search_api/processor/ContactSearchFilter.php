<?php

namespace Drupal\tre_contact_search\Plugin\search_api\processor;

use Drupal\node\NodeInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;

/**
 * Filters persons based on integration origin and references.
 *
 * @SearchApiProcessor(
 * id = "tre_contact_search_filter",
 * label = @Translation("Tampere Contact Search Filter"),
 * description = @Translation("Excludes manually created persons and unreferenced persons."),
 * stages = {
 * "alter_items" = 0,
 * }
 * )
 */
class ContactSearchFilter extends ProcessorPluginBase {

  /**
   * Static cache for person reference checks during an indexing run.
   *
   * @var array
   */
  private array $referencedPersonsCache = [];

  public function alterIndexedItems(array &$items) {
    foreach ($items as $item_id => $item) {
      $entity = $item->getOriginalObject()->getValue();

      if (!$entity instanceof NodeInterface || $entity->bundle() !== 'person') {
        continue;
      }

      // Only index persons created by the automated integration (owner 0 or 1).
      $owner_id = (int) $entity->getOwnerId();
      if ($owner_id !== 0 && $owner_id !== 1) {
        unset($items[$item_id]);
        continue;
      }

      $person_id = (int) $entity->id();

      if (!isset($this->referencedPersonsCache[$person_id])) {
        $this->referencedPersonsCache[$person_id] = $this->isPersonReferenced($person_id);
      }

      if (!$this->referencedPersonsCache[$person_id]) {
        unset($items[$item_id]);
      }
    }
  }

  /**
   * Determines if a person node is currently referenced in any active liftup paragraphs.
   *
   * @param int $person_id
   * The node ID of the person.
   *
   * @return bool
   * True if the person is referenced by an active paragraph on a published node.
   */
  private function isPersonReferenced(int $person_id): bool {
    $indexing_context = drupal_static('tre_contact_search_indexing_context', []);
    $triggering_node_id = isset($indexing_context['triggering_node_id'])
      ? (string) $indexing_context['triggering_node_id']
      : NULL;
    $new_persons = $indexing_context['new_persons'] ?? [];

    // Short-circuit: person is in the triggering node's in-memory state (ADD
    // case, new paragraphs may not have parent_id in DB yet).
    if ($triggering_node_id !== NULL && in_array($person_id, $new_persons)) {
      return TRUE;
    }

    $paragraph_storage = \Drupal::entityTypeManager()->getStorage('paragraph');

    // Query in small batches to avoid loading all references into memory.
    $batch_size = 50;
    $offset = 0;

    do {
      $query = $paragraph_storage->getQuery()->accessCheck(FALSE);

      $group = $query->orConditionGroup()
        ->condition('field_person_liftup', $person_id)
        ->condition('field_person_liftups', $person_id);

      $pids = $query
        ->condition($group)
        ->sort('id')
        ->range($offset, $batch_size)
        ->execute();

      if (empty($pids)) {
        return FALSE;
      }

      $paragraphs = $paragraph_storage->loadMultiple($pids);

      foreach ($paragraphs as $paragraph) {
        // Skip paragraphs on the triggering node — ERR orphan deletion is async
        // (cron queue), so DB state is stale during this request.
        if ($triggering_node_id !== NULL) {
          $root_node_id = $this->getParagraphRootNodeId($paragraph);
          if ($root_node_id === $triggering_node_id) {
            continue;
          }
        }

        if ($this->isParagraphActiveOnPublishedNode($paragraph)) {
          return TRUE;
        }
      }

      $offset += $batch_size;
    } while (count($pids) === $batch_size);

    return FALSE;
  }

  /**
   * Walks up the paragraph parent chain and returns the root node ID.
   *
   * Uses paragraphs_item_field_data (entity base table) which is updated
   * synchronously, unlike the ERR field tables which are async-purged.
   */
  private function getParagraphRootNodeId($paragraph): ?string {
    $parent_type = $paragraph->get('parent_type')->getString();
    $parent_id = $paragraph->get('parent_id')->getString();
    $depth = 0;

    while ($parent_type === 'paragraph' && !empty($parent_id) && $depth < 10) {
      $parent = \Drupal::entityTypeManager()->getStorage('paragraph')->load($parent_id);
      if (!$parent) {
        return NULL;
      }
      $parent_type = $parent->get('parent_type')->getString();
      $parent_id = $parent->get('parent_id')->getString();
      $depth++;
    }

    return ($parent_type === 'node' && !empty($parent_id)) ? (string) $parent_id : NULL;
  }

  /**
   * Returns TRUE if the paragraph is attached to a published node.
   *
   * Walks the parent chain to handle nested paragraph structures.
   */
  private function isParagraphActiveOnPublishedNode($paragraph): bool {
    $parent_type = $paragraph->get('parent_type')->getString();
    $parent_id = $paragraph->get('parent_id')->getString();
    $parent_field_name = $paragraph->get('parent_field_name')->getString();

    if (empty($parent_type) || empty($parent_id) || empty($parent_field_name)) {
      return FALSE;
    }

    // loadUnchanged() bypasses the static cache, ensuring we see the current
    // DB state and not a pre-save cached revision.
    $parent = \Drupal::entityTypeManager()->getStorage($parent_type)->loadUnchanged($parent_id);

    if (!$parent) {
      return FALSE;
    }

    if (!$parent->hasField($parent_field_name)) {
      return FALSE;
    }

    $is_active = FALSE;
    foreach ($parent->get($parent_field_name) as $item) {
      if ($item->target_id == $paragraph->id()) {
        $is_active = TRUE;
        break;
      }
    }

    if (!$is_active) {
      return FALSE;
    }

    if ($parent instanceof \Drupal\node\NodeInterface) {
      return $parent->isPublished();
    }

    if ($parent->getEntityTypeId() === 'paragraph') {
      return $this->isParagraphActiveOnPublishedNode($parent);
    }

    return FALSE;
  }

}