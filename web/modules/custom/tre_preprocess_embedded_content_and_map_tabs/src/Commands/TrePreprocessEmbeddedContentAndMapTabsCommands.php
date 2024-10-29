<?php

namespace Drupal\tre_preprocess_embedded_content_and_map_tabs\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for the tre_preprocess_embedded_content_and_map_tabs module.
 */
class TrePreprocessEmbeddedContentAndMapTabsCommands extends DrushCommands {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs the commands object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Resaving all nodes that has the field_listing_search_content field.
   *
   * @usage tre_preprocess_embedded_content_and_map_tabs-resave_nodes_has_listing_search_content_field
   *   Resaving all nodes that has the field_listing_search_content field.
   *
   * @command tre_preprocess_embedded_content_and_map_tabs:resave_nodes_has_listing_search_content_field
   * @aliases tre_resave_nodes_has_listing_search_content_field
   */
  public function reSaveNodesHasListingSearchContentField() {
    $nodeStorage = $this->entityTypeManager->getStorage('node');

    // Since this command is probably going to get exectued only once,
    // the content types are hard-coded.
    $content_types_has_listing_search_content_field = [
      'place',
      'place_of_business',
      'city_planning_and_constructions',
      'zoning_information',
      'comprehensive_plan',
    ];
    foreach ($content_types_has_listing_search_content_field as $content_type) {
      $nodes = $nodeStorage->loadByProperties([
        'type' => $content_type,
        'status' => 1,
      ]);
      foreach ($nodes as $node) {
        $this->io()->writeln($node->id());
        $node->save();
      }
    }
    $this->io()->writeln('Finish!');
  }

  /**
   * Set the omit field to TRUE in Place nodes based on URL patterns.
   *
   * @param array $urls
   *   The URL patterns to search for Place nodes.
   *   Any url for place nodes that contains the input url will be considered.
   *   Example: "/url-pattern1/" "/url-pattern2/" "/url-pattern3/".
   *
   * @usage tre_set_omit_field_for_place_nodes_by_urls "/url-pattern1/"
   *   Pass the URL pattern of palce nodes to set the omit field to True.
   * @usage tre_set_omit_field_for_place_nodes_by_urls "/url-pattern1/" "/url-pattern2/"
   *   Pass multiple URL patterns of palce nodes to set the omit field to True.
   *
   * @command tre_preprocess_embedded_content_and_map_tabs:set_omit_field_for_place_nodes_by_urls
   * @aliases tre_set_omit_field_for_place_nodes_by_urls
   */
  public function setOmitFieldForPlaceNodesByUrl(array $urls) {
    $nodeStorage = $this->entityTypeManager->getStorage('node');
    $pathAliasStorage = $this->entityTypeManager->getStorage('path_alias');

    foreach ($urls as $url) {
      $updated_nids = [];
      $query = $pathAliasStorage->getQuery();
      $alias_ids = $query->condition('alias', '%' . $url . '%', 'LIKE')->accessCheck(FALSE)->execute();

      $aliases = $pathAliasStorage->loadMultiple($alias_ids);

      foreach ($aliases as $alias) {
        /** @var \Drupal\path_alias\PathAliasInterface $alias */
        $internal_path = $alias->getPath();

        // Check if the internal path is a node path.
        if (strpos($internal_path, '/node/') !== 0) {
          continue;
        }

        $nid = Url::fromUserInput($internal_path)->getRouteParameters()['node'] ?? NULL;
        if (empty($nid)) {
          continue;
        }

        $node = $nodeStorage->load($nid);
        if (!($node instanceof Node && $node->bundle() == 'place')) {
          continue;
        }

        $node_langcode = $alias->get('langcode')->getLangcode();
        if (!$node->hasTranslation($node_langcode)) {
          continue;
        }

        $translation = $node->getTranslation($node_langcode);
        if (!$translation->hasField('field_omit_from_listing_map') || $translation->get('field_omit_from_listing_map')->value == 1) {
          continue;
        }

        $translation->set('field_omit_from_listing_map', TRUE);
        $translation->save();

        if (!in_array($nid, $updated_nids)) {
          $updated_nids[] = $nid;
        }

        $this->io()->writeln("Node ID " . $nid . ": field_omit_from_listing_map is set to True for langcode " . $node_langcode . PHP_EOL);
      }

      $this->io()->writeln('Updating field_omit_from_listing_map is completed for Places with URL pattern of ' . $url . " : " . count($updated_nids) . " nodes have been updated." . PHP_EOL);
    }
  }

}
