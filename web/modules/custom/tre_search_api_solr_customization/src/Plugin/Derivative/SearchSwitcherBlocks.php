<?php

namespace Drupal\tre_search_api_solr_customization\Plugin\Derivative;

use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\views\ViewEntityInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides block plugin definitions for search switcher blocks.
 *
 * @see Drupal\tre_search_api_solr_customization\Plugin\Block\SearchSwitcherBlock
 */
final class SearchSwitcherBlocks implements ContainerDeriverInterface {

  public const VIEWS = [
    'search' => [
      'id' => 'search',
      'display_id' => 'page_1',
    ],
    'search_ext' => [
      'id' => 'search_ext',
      'display_id' => 'page_1',
    ],
  ];

  use StringTranslationTrait;

  /**
   * List of derivative definitions.
   *
   * @var array
   */
  protected $derivatives = [];

  /**
   * The view storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $viewStorage;

  /**
   * The base plugin ID that the derivative is for.
   *
   * @var string
   */
  protected $basePluginId;

  /**
   * Constructs a SearchSwitcherBlocks object.
   *
   * @param string $base_plugin_id
   *   The base plugin ID.
   * @param \Drupal\Core\Entity\EntityStorageInterface $view_storage
   *   The entity storage to load views.
   */
  public function __construct($base_plugin_id, EntityStorageInterface $view_storage) {
    $this->basePluginId = $base_plugin_id;
    $this->viewStorage = $view_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $base_plugin_id,
      $container->get('entity_type.manager')->getStorage('view')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinition($derivative_id, $base_plugin_definition) {
    if (!empty($this->derivatives) && !empty($this->derivatives[$derivative_id])) {
      return $this->derivatives[$derivative_id];
    }
    $this->getDerivativeDefinitions($base_plugin_definition);
    return $this->derivatives[$derivative_id];
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    // Check all Views for displays with an exposed filter block.
    foreach ($this->viewStorage->loadMultiple() as $view) {
      // Do not return results for disabled views.
      if (!($view instanceof ViewEntityInterface) || !$view->status() || !in_array($view->id(), array_keys(self::VIEWS), TRUE)) {
        continue;
      }
      $executable = $view->getExecutable();
      $executable->initDisplay();
      foreach ($executable->displayHandlers as $display) {
        if (isset($display) && $display->display['id'] === self::VIEWS[$view->id()]['display_id']) {
          // Add a block definition for the block.
          $delta = $view->id() . '-' . $display->display['id'];
          $translation_args = [
            '@view' => $view->id(),
            '@display_id' => $display->display['id'],
          ];
          $desc = $this->t('Search switcher block: @view-@display_id', $translation_args);
          $this->derivatives[$delta] = [
            'admin_label' => $desc,
            'config_dependencies' => [
              'config' => [
                $view->getConfigDependencyName(),
              ],
            ],
          ];
          $this->derivatives[$delta] += $base_plugin_definition;
        }
      }
    }
    return $this->derivatives;
  }

}
