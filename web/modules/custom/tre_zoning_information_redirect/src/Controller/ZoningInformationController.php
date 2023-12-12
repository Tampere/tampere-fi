<?php

namespace Drupal\tre_zoning_information_redirect\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Routing\LocalRedirectResponse;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for zoning information plan number routes.
 */
final class ZoningInformationController extends ControllerBase {

  /**
   * The Entity Type Manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The Renderer service.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * Constructor.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    Renderer $renderer
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('renderer')
    );
  }

  /**
   * Redirects plan number routes to their corresponding nodes.
   *
   * @param string $plan_number
   *   The plan number to use for matching the correct zoning information node.
   *
   * @return \Drupal\Core\Routing\LocalRedirectResponse
   *   Redirect response to the original node.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   If no matching, published nodes are found for the given plan number.
   */
  public function redirectToNode(string $plan_number): LocalRedirectResponse {
    // Setting up a render-context to avoid LogicException caused by leaked
    // metadata (https://www.drupal.org/node/2513810).
    $context = new RenderContext();

    $response = $this->renderer->executeInRenderContext($context, function () use ($plan_number) {
      // Get the ID for the 'zoning_information' node with the matching plan
      // number. One plan number should only be attached to one node, but the
      // most recent one should be selected in case there are multiple.
      $node_storage = $this->entityTypeManager->getStorage('node');
      $node_ids = $node_storage->getQuery()->accessCheck(TRUE)
        ->condition('field_plan_number.entity.name', $plan_number)
        ->condition('status', 1)
        ->condition('type', 'zoning_information')
        ->range(0, 1)
        ->sort('created', 'DESC')
        ->execute();

      if (empty($node_ids)) {
        throw new NotFoundHttpException();
      }

      $node_id = reset($node_ids);
      $node = $node_storage->load($node_id);

      if (!($node instanceof NodeInterface)) {
        throw new NotFoundHttpException();
      }

      $node_url = Url::fromRoute('entity.node.canonical', ['node' => $node_id])->toString(TRUE)->getGeneratedUrl();

      $response = new LocalRedirectResponse($node_url, 301);
      $response->addCacheableDependency($node);

      return $response;
    });

    // If there is metadata left on the context, apply it on the response.
    if (!$context->isEmpty()) {
      $metadata = $context->pop();
      $response->addCacheableDependency($metadata);
    }

    return $response;
  }

}
