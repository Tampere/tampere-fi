<?php

namespace Drupal\tre_preprocess_embedded_content_and_map_tabs\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
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

    $node = $storage->load($data['nid']);

    if (!($node instanceof NodeInterface)) {
      return;
    }

    foreach ($node->getTranslationLanguages() as $language) {
      try {
        $translation = $node->getTranslation($language->getId());
        if ($translation instanceof NodeInterface) {
          $translation->save();
        }
      }
      catch (\InvalidArgumentException $e) {
        continue;
      }
    }
  }

}
