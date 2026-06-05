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

      // We only want to index persons created by the automated integration.
      // Integration users are typically User 0 (system) or User 1 (admin).
      // If a standard user created this person manually, we exclude them from the index.
      $owner_id = (int) $entity->getOwnerId();
      if ($owner_id !== 0 && $owner_id !== 1) {
        unset($items[$item_id]);
        continue;
      }

      $person_id = (int) $entity->id();

      // Check if we have already calculated this person's reference status in this batch.
      if (!isset($this->referencedPersonsCache[$person_id])) {
        $this->referencedPersonsCache[$person_id] = $this->isPersonReferenced($person_id);
      }

      // Ensure the person is actually placed on a published page.
      // If they are not referenced anywhere active, we exclude them.
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
    $paragraph_storage = \Drupal::entityTypeManager()->getStorage('paragraph');

    // Check matching paragraphs in small batches so we do not load every
    // reference into memory when a person is heavily referenced.
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

      // Check each paragraph to see if it belongs to a published node.
      foreach ($paragraphs as $paragraph) {
        if ($this->isParagraphActiveOnPublishedNode($paragraph)) {
          return TRUE;
        }
      }

      $offset += $batch_size;
    } while (count($pids) === $batch_size);

    return FALSE;
  }

  /**
   * Recursively verifies that a paragraph is actively attached to a published node.
   * * This handles nested structures where a paragraph might be attached to another 
   * paragraph rather than directly to a node.
   *
   * @param \Drupal\Core\Entity\EntityInterface $paragraph
   * The paragraph entity to check.
   *
   * @return bool
   * True if the paragraph tree ultimately resolves to a published node.
   */
  private function isParagraphActiveOnPublishedNode($paragraph): bool {
    $parent = $paragraph->getParentEntity();

    if (!$parent) {
      return FALSE;
    }

    // Verify the paragraph still exists in the parent's current active revision.
    // This prevents old, deleted references from keeping a person in the index.
    $parent_field_name = $paragraph->get('parent_field_name')->getString();
    if (empty($parent_field_name) || !$parent->hasField($parent_field_name)) {
      return FALSE;
    }

    $is_active = FALSE;
    foreach ($parent->get($parent_field_name) as $item) {
      if ($item->target_id == $paragraph->id()) {
        $is_active = TRUE;
        break;
      }
    }

    // If the paragraph was removed from the parent, we consider it a dead reference.
    if (!$is_active) {
      return FALSE;
    }

    // If we have reached the top of the tree and found a node, check its published status.
    if ($parent instanceof \Drupal\node\NodeInterface) {
      return $parent->isPublished();
    }

    // If the parent is another paragraph, we need to recursively walk further up the tree.
    if ($parent->getEntityTypeId() === 'paragraph') {
      return $this->isParagraphActiveOnPublishedNode($parent);
    }

    // If the parent is a different entity type like a block or term, skip it.
    return FALSE;
  }

}