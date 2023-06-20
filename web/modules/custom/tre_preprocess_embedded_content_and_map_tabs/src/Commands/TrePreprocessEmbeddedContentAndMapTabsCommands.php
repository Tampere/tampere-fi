<?php

namespace Drupal\tre_preprocess_embedded_content_and_map_tabs\Commands;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Commands\DrushCommands;

/**
 * Drush commandfile for the tre_preprocess_embedded_content_and_map_tabs module.
 */
class TrePreprocessEmbeddedContentAndMapTabsCommands extends DrushCommands {

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
   * Resaving all nodes that has the field_listing_search_content field.
   *
   * @usage tre_preprocess_embedded_content_and_map_tabs-resave_nodes_has_listing_search_content_field
   *   Resaving all nodes that has the field_listing_search_content field.
   *
   * @command tre_preprocess_embedded_content_and_map_tabs:resave_nodes_has_listing_search_content_field
   * @aliases tre_resave_nodes_has_listing_search_content_field
   */
  public function reSaveNodesHasListingSearchContentField() {
    // Since this command is probably going to get exectued only once,
    // the content types are hard-coded.
    $content_types_has_listing_search_content_field = [
      'place',
      'place_of_business',
      'city_planning_and_constructions',
      'zoning_information',
    ];
    foreach ($content_types_has_listing_search_content_field as $content_type) {
      $nodes = $this->nodeStorage->loadByProperties([
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

}
