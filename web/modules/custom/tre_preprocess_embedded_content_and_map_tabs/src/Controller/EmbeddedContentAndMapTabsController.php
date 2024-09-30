<?php

namespace Drupal\tre_preprocess_embedded_content_and_map_tabs\Controller;

use Drupal\Core\Controller\ControllerBase;
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
   * Constructor.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    EntityRepositoryInterface $entity_repository,
    RendererInterface $renderer,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityRepository = $entity_repository;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $entity_type_manager = $container->get('entity_type.manager');
    $entity_repository = $container->get('entity.repository');
    $renderer = $container->get('renderer');
    return new static($entity_type_manager, $entity_repository, $renderer);
  }

  /**
   * {@inheritdoc}
   */
  public function render($node_id) {
    $acceptable_types = [
      'place',
      'place_of_business',
      'city_planning_and_constructions',
      'zoning_information',
      'comprehensive_plan',
    ];

    $node = $this->entityTypeManager()->getStorage('node')->load($node_id);

    if (
      $node instanceof NodeInterface &&
      in_array($node->bundle(), $acceptable_types) &&
      $node->isPublished()
    ) {
      $translated_node = $this->entityRepository->getTranslationFromContext($node);
      $map_node_build = $this->entityTypeManager()->getViewBuilder('node')->view($translated_node, self::DEFAULT_MAP_ITEM_VIEW_MODE);
      return new Response($this->renderer->render($map_node_build));
    }

    return new Response('Access forbidden', Response::HTTP_FORBIDDEN);
  }

}
