<?php

namespace Drupal\tre_preprocess_embedded_content_and_map_tabs\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\node\NodeInterface;
use Drupal\tre_preprocess_utility_functions\Utils\HelperFunctionsInterface;

/**
 * Service class that builds horizontal accordion elements.
 */
class HorizontalAccordionBuilder {
  use StringTranslationTrait;

  /**
   * Constructor.
   */
  public function __construct(
    private EntityTypeManagerInterface $entityTypeManager,
    private EntityRepositoryInterface $entityRepository,
    TranslationInterface $stringTranslation,
    private HelperFunctionsInterface $helperFunctions,
  ) {
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * Build tabs for many nodes (keyed by nid).
   */
  public function buildForNodeIds(array $nids, bool $include_map_tab = TRUE): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $nodes = $storage->loadMultiple($nids);
    $out = [];

    /** @var \Drupal\node\NodeInterface $node */
    foreach ($nodes as $node) {
      if ($node instanceof NodeInterface) {
        $tabs = $this->buildForNode($node, $include_map_tab);
        if (!empty($tabs)) {
          $out[$node->id()] = $tabs;
        }
      }
    }

    return $out;
  }

  /**
   * Build tabs for a single node (returns an array of tabs or []).
   */
  public function buildForNode(NodeInterface $node, bool $include_map_tab = TRUE): array {
    // Parse and collect data for horizontal accordion tabs.
    $node = $this->entityRepository->getTranslationFromContext($node);
    $tabs = [];
    $locations = [];

    // Accessibility json sentences from referenced address nodes.
    // These are parsed for Place Of Business type nodes.
    if ($node->hasField('field_addresses') && !$node->get('field_addresses')->isEmpty()) {
      $addresses = $node->get('field_addresses');
      $access_info_list = [];

      foreach ($addresses as $address_item) {
        $map_location_node = $address_item->entity;
        if ($map_location_node instanceof NodeInterface
          && $map_location_node->hasField('field_access_info_sentences_json')) {

          $json = $map_location_node->get('field_access_info_sentences_json')->value;
          if (!empty($json)) {
            $decoded = json_decode($json, TRUE);
            if (is_array($decoded)) {
              $access_info_list = array_merge($access_info_list, $decoded);
            }
          }
        }
      }

      if (!empty($access_info_list)) {
        $tabs[] = [
          'title' => $this->t('Accessibility'),
          'accessibility_sentences' => $access_info_list,
        ];
      }

      // Map tab content and locations.
      $location_nodes = $node->get('field_addresses')->referencedEntities();
      foreach ($location_nodes as $location_node) {
        if (!$location_node instanceof NodeInterface) {
          continue;
        }
        $node_has_latitude_field_value = $location_node->hasField(HelperFunctionsInterface::LATITUDE_FIELD_NAME) && !$location_node->get(HelperFunctionsInterface::LATITUDE_FIELD_NAME)->isEmpty();
        $node_has_longitude_field_value = $location_node->hasField(HelperFunctionsInterface::LONGITUDE_FIELD_NAME) && !$location_node->get(HelperFunctionsInterface::LONGITUDE_FIELD_NAME)->isEmpty();
        if ($node_has_latitude_field_value && $node_has_longitude_field_value) {
          $latitude = $location_node->get(HelperFunctionsInterface::LATITUDE_FIELD_NAME)->getString();
          $longitude = $location_node->get(HelperFunctionsInterface::LONGITUDE_FIELD_NAME)->getString();
          $locations[] = [
            'latitude' => $latitude,
            'longitude' => $longitude,
          ];
        }
      }
    }

    // If at this point the locations array is empty, we are most likely
    // working with a Place type node so we attempt to fetch the location
    // fields using the helper function.
    if (empty($locations)) {
      $locations = $this->helperFunctions->getNodeLiftupLocation($node);
    }

    // Location data -> parse to a tab.
    if (!empty($locations) && $include_map_tab) {
      $map_render_array = $this->helperFunctions->getNodeLiftupMap($node, 'liftup-map');
      $map_render_array['frame']['#attached']['drupalSettings']['tampere']['nodeLiftupLocations'][$node->id()] = $locations;
      $tabs[] = [
        'title' => $this->t('Location on a map'),
        'content' => $map_render_array,
        'accordion_content_modifiers' => ['no-margin'],
      ];
    }

    // Image gallery -> add tab if there are images.
    if ($node->hasField('field_image_gallery') && !$node->get('field_image_gallery')->isEmpty()) {
      $gallery_has_images = FALSE;
      $gallery_paragraph = $node->get('field_image_gallery')->entity;

      // Go through the attached image gallery paragraph to
      // check if it contains any actual images.
      if ($gallery_paragraph && $gallery_paragraph->hasField('field_images_limited') && !$gallery_paragraph->get('field_images_limited')->isEmpty()) {
        // Go through each image in the gallery.
        $gallery_item_paragraphs = $gallery_paragraph->get('field_images_limited')->referencedEntities();
        foreach ($gallery_item_paragraphs as $item_paragraph) {
          if ($this->helperFunctions->getImageInformation($item_paragraph, 'field_media') !== NULL) {
            // We have an image -> break the loop and
            // move onto creating the actual tab.
            $gallery_has_images = TRUE;
            break;
          }
        }
      }

      // Create the image gallery tab.
      if ($gallery_has_images) {
        $image_gallery_render_array = $node->get('field_image_gallery')->view('default');
        $tabs[] = [
          'title' => $this->t('Pictures of the location'),
          'content' => $image_gallery_render_array,
        ];
      }
    }

    return $tabs;
  }

}
