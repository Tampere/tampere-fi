<?php

namespace Drupal\tre_node_location_coordinate_conversion\Service;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\tre_node_location_coordinate_conversion\Configuration;
use Psr\Log\LoggerInterface;

/**
 * The NodeGeographicalAreaManipulator service.
 */
final class NodeGeographicalAreaManipulator {

  private const VOCABULARY = 'geographical_areas';
  private const FIELD_NAME = 'field_geographical_areas';

  /**
   * The entity_type.manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private EntityTypeManagerInterface $entityTypeManager;

  /**
   * The tre_node_location_coordinate_conversion logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  private LoggerInterface $logger;

  /**
   * Constructs the NodeGeographicalAreaManipulator service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    LoggerInterface $logger
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;
  }

  /**
   * Updates the geographical areas field of the node with the correct term.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node whose field is being updated.
   * @param string $area_name
   *   The name of the geographical area to use in the update.
   */
  public function updateField(NodeInterface $node, string $area_name): void {
    if (!in_array($node->bundle(), Configuration::CONTENT_TYPES_WITH_FIELD_GEOGRAPHICAL_AREAS_TO_AUTOMATE, TRUE)) {
      return;
    }

    /** @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface $list */
    $list = $node->get(self::FIELD_NAME);
    $current_terms = $list->referencedEntities();
    $entity_name_extractor = function (EntityInterface $term) {
      return $term->label();
    };
    $current_term_names = array_map($entity_name_extractor, $current_terms);
    if (in_array($area_name, $current_term_names, TRUE)) {
      return;
    }

    $conditions = [
      'vid' => self::VOCABULARY,
      'name' => $area_name,
    ];
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $terms = $term_storage->loadByProperties($conditions);
    if (count($terms) !== 1) {
      $context = [
        '@vocabulary' => self::VOCABULARY,
        '@name' => $area_name,
      ];
      $this->logger->warning('No taxonomy term or more than one taxonomy term found in @vocabulary with name @name. Aborting automatic assignment of terms.', $context);
      return;
    }

    $term = reset($terms);

    $node->get(self::FIELD_NAME)->setValue(['target_id' => $term->id()]);
    $debug_context = [
      '@node_title' => $node->label(),
      '@node' => $node->id(),
      '@area' => $area_name,
      '@area_id' => $term->id(),
    ];
    $this->logger->debug('Updated geographical area for @node_title (@node): @area (@area_id).', $debug_context);
  }

  /**
   * Clears the field_geographical_areas field for a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node whose field_geographical_areas field is to be cleared.
   */
  public function clearField(NodeInterface $node): void {
    if (!in_array($node->bundle(), Configuration::CONTENT_TYPES_WITH_FIELD_GEOGRAPHICAL_AREAS_TO_AUTOMATE, TRUE)) {
      return;
    }

    $node->get(self::FIELD_NAME)->setValue([]);
  }

  /**
   * Makes sure the correct node instance is being used for the update.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to check.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The node that should be updated, or null in case the parent node of the
   *   given map_point node can't be found.
   */
  public function getCorrectDestinationNode(NodeInterface $node) {
    // Switch the target node where the area is to be written if the source of
    // the point was a map_point node since the field_geographical_areas field
    // lives in its parent node (of type place_of_business).
    if ($node->bundle() !== 'map_point') {
      return $node;
    }

    $node = $this->findHostNodeForMapPoint($node);
    if (!($node instanceof NodeInterface)) {
      return NULL;
    }

    return $node;
  }

  /**
   * Finds the place_of_business parent node for a map_point node.
   *
   * If there are no hosts or multiple hosts, returns NULL.
   *
   * @param \Drupal\node\NodeInterface|null $node
   *   The node whose parent is to be found.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The parent node, or null if not found definitively.
   */
  private function findHostNodeForMapPoint(?NodeInterface $node): ?NodeInterface {
    if (empty($node)) {
      return NULL;
    }

    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->entityTypeManager->getStorage('node');
    $query = $node_storage->getQuery()->accessCheck(FALSE);
    $query
      ->condition('type', 'place_of_business')
      ->condition('field_addresses.target_id', $node->id());

    $result = $query->execute();
    $debug_context = [
      '@title' => $node->label(),
      '@nid' => $node->id(),
    ];
    if (empty($result)) {
      $this->logger->debug('No parent nodes found for map point @title (@nid)', $debug_context);
      return NULL;
    }

    if (count($result) > 1) {
      $this->logger->debug('More than 1 parent nodes found for map point @title (@nid)', $debug_context);
      return NULL;
    }

    $nid = reset($result);
    /** @var \Drupal\node\NodeInterface|null $node */
    $node = $node_storage->load($nid);
    return $node;
  }

}
