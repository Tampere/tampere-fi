<?php

namespace Drupal\tre_preprocess;

use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Http\RequestStack;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Language\LanguageManager;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Utility\Token;
use Drupal\Core\Pager\PagerManager;
use Drupal\preprocess\PreprocessPluginBase;
use Drupal\tre_preprocess_utility_functions\Utils\HelperFunctionsInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin base for preprocessing, with added injected dependencies.
 */
abstract class TrePreProcessPluginBase extends PreprocessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The Renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The Language Manager service.
   *
   * @var \Drupal\Core\Language\LanguageManager
   */
  protected $languageManager;

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
   * Helper Functions service.
   *
   * @var \Drupal\tre_preprocess_utility_functions\Utils\HelperFunctionsInterface
   */
  protected $helperFunctions;

  /**
   * The Current Route Match service.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $routeMatch;

  /**
   * The current path.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected $currentPath;

  /**
   * The currently active request object.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $currentRequest;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * The File Url Generator service.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  /**
   * The Pager Manager service.
   *
   * @var \Drupal\Core\Pager\PagerManager
   */
  protected $pagerManager;

  /**
   * Constructor.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    RendererInterface $renderer,
    LanguageManager $language_manager,
    EntityTypeManagerInterface $entity_type_manager,
    EntityRepositoryInterface $entity_repository,
    HelperFunctionsInterface $helper_functions,
    CurrentRouteMatch $route_match,
    CurrentPathStack $current_path,
    RequestStack $request_stack,
    Token $token,
    FileUrlGeneratorInterface $file_url_generator,
    PagerManager $pager_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->renderer = $renderer;
    $this->languageManager = $language_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityRepository = $entity_repository;
    $this->helperFunctions = $helper_functions;
    $this->routeMatch = $route_match;
    $this->currentPath = $current_path;
    $this->currentRequest = $request_stack->getCurrentRequest();
    $this->token = $token;
    $this->fileUrlGenerator = $file_url_generator;
    $this->pagerManager = $pager_manager;
  }

  /**
   * Implementation for the create method.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('renderer'),
      $container->get('language_manager'),
      $container->get('entity_type.manager'),
      $container->get('entity.repository'),
      $container->get('tre_preprocess_utility_functions.helper_functions'),
      $container->get('current_route_match'),
      $container->get('path.current'),
      $container->get('request_stack'),
      $container->get('token'),
      $container->get('file_url_generator'),
      $container->get('pager.manager')
    );
  }

}
