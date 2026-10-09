<?php

namespace Drupal\tre_preprocess_embedded_content_and_map_tabs\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Queue worker for the tre_list_and_map_update_field_computed_visibility queue.
 *
 * @QueueWorker(
 *   id = "tre_list_and_map_update_field_computed_visibility",
 *   title = @Translation("Resave passed-in nodes"),
 *   cron = {"time" = 240}
 * )
 */
final class ResaveNodesWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Main constructor.
   *
   * @param array $configuration
   *   Configuration array.
   * @param mixed $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Used to grab functionality from the container.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container.
   * @param array $configuration
   *   Configuration array.
   * @param mixed $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   *
   * @return static
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function processItem($data) {

    $storage = $this->entityTypeManager->getStorage('node');

    if (!($storage instanceof RevisionableStorageInterface)) {
      return;
    }

    // Check that queue items has nid value
    if (empty($data['nid'])) {
      return;
    }

    $nid = $data['nid'];

    // Load the Published (default revision) node
    $default_node = $storage->load($nid);

    foreach ($storage->load($nid)->getTranslationLanguages() as $language) {
      $langcode = $language->getId();
      $translation = $default_node->getTranslation($langcode);
      if (!($translation instanceof NodeInterface)) {
        continue;
      }

      /**
       * Get the revision states before saving anything - We need to compare if there
       * is a later revision when comparing with the published revision -
       * if so it means that there is a draft revision that shouldnt be overwritten.
       */
      $default_vid = $translation->getRevisionId();

      // Get the latest revision for the current translation.
      $query = $storage->getQuery()
        ->allRevisions()
        ->condition('nid', $nid)
        ->condition('langcode', $langcode)
        ->condition('revision_translation_affected', 1, '=', $langcode)
        // ->sort('vid', 'DESC')
        ->sort('revision_timestamp', 'DESC')
        ->range(0, 1) // Limit to the single latest result
        ->accessCheck(FALSE);
      $vids = $query->execute();
      $latest_vid = !empty($vids) ? key($vids) : NULL;

      // Load the DRAFT revision that was latest before the default revision was
      // saved. This keeps draft content visible in the content editor (not overwriting with Published revision)
      $draft_node = $storage->loadRevision($latest_vid);

      $node_edited_time = $translation->getRevisionCreationTime();
      $draft_edited_time = $draft_node->getRevisionCreationTime();

      $has_newer_draft = ($draft_edited_time > $node_edited_time);

      try {
        // Save the Published (default revision) first so presave hooks update computed fields
        // for the Published version of the content.
        $translation->save();
      }
      catch (\Exception $e) {
        continue;
      }

      /**
       * If there wasn't a newer draft revision - the work is done since we updated the
       * published version in previous step
       */
      if (!$has_newer_draft) {
        continue;
      }

      if (!($draft_node instanceof NodeInterface)) {
        continue;
      }

      try {
        // Save the original "latest draft" again and set its state to draft.
        $draft_node->set('moderation_state', 'draft');
        $draft_node->save();
      }
      catch (\Exception $e) {
        continue;
      }
    }
  }

}
