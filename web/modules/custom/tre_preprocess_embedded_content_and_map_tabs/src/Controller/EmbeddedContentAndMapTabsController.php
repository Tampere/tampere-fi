<?php

namespace Drupal\tre_preprocess_embedded_content_and_map_tabs\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller routines for tre_preprocess_embedded_content_and_map_tabs routes.
 */
class EmbeddedContentAndMapTabsController extends ControllerBase {
  /**
   * The view mode to use for the paragraph content in the map view by default.
   */
  const DEFAULT_MAP_ITEM_VIEW_MODE = 'map_tab_card_view_mode';
  const MAP_ITEM_WITH_HOURS_VIEW_MODE = 'map_tab_card_with_hours_view_mode';

  /**
   * The Entity Type Manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The Entity Repository service.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The Entity Display Repository service.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * Constructor.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    EntityRepositoryInterface $entity_repository,
    RendererInterface $renderer,
    EntityDisplayRepositoryInterface $entity_display_repository,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityRepository = $entity_repository;
    $this->renderer = $renderer;
    $this->entityDisplayRepository = $entity_display_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $entity_type_manager = $container->get('entity_type.manager');
    $entity_repository = $container->get('entity.repository');
    $renderer = $container->get('renderer');
    $entity_display_repository = $container->get('entity_display.repository');
    return new static($entity_type_manager, $entity_repository, $renderer, $entity_display_repository);
  }

  /**
   * {@inheritdoc}
   */
  public function render($node_id, $show_hours) {
    $acceptable_types = [
      'place',
      'place_of_business',
      'city_planning_and_constructions',
      'zoning_information',
      'comprehensive_plan',
    ];

    $node = $this->entityTypeManager()->getStorage('node')->load($node_id);
    $node_available_view_modes = $this->entityDisplayRepository->getViewModeOptionsByBundle('node', $node->bundle());

    $view_mode = self::DEFAULT_MAP_ITEM_VIEW_MODE;

    // If the node does not have a Map view with hours,
    // it should fall back to the default Map view.
    if ($show_hours === 'true'
        && array_key_exists(self::MAP_ITEM_WITH_HOURS_VIEW_MODE, $node_available_view_modes)) {
      $view_mode = self::MAP_ITEM_WITH_HOURS_VIEW_MODE;
    }

    if (
      $node instanceof NodeInterface &&
      in_array($node->bundle(), $acceptable_types) &&
      $node->isPublished()
    ) {
      $translated_node = $this->entityRepository->getTranslationFromContext($node);
      $map_node_build = $this->entityTypeManager()->getViewBuilder('node')->view($translated_node, $view_mode);
      return new Response($this->renderer->render($map_node_build));
    }

    return new Response('Access forbidden', Response::HTTP_FORBIDDEN);
  }

}
