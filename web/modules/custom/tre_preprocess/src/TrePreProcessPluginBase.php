<?php

namespace Drupal\tre_preprocess;

use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Http\RequestStack;
use Drupal\Core\Language\LanguageManager;
use Drupal\Core\Menu\MenuActiveTrail;
use Drupal\Core\Menu\MenuLinkTree;
use Drupal\Core\Pager\PagerManager;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Utility\Token;
use Drupal\preprocess\PreprocessPluginBase;
use Drupal\tre_preprocess_utility_functions\Utils\HelperFunctionsInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection;

/**
 * Plugin base for preprocessing, with added injected dependencies.
 */
abstract class TrePreProcessPluginBase extends PreprocessPluginBase implements ContainerFactoryPluginInterface {

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
   * The Date Formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;

  /**
   * The File Url Generator service.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  /**
   * The Entity Repository service.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The Entity Type Manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Helper Functions service.
   *
   * @var \Drupal\tre_preprocess_utility_functions\Utils\HelperFunctionsInterface
   */
  protected $helperFunctions;

  /**
   * The Language Manager service.
   *
   * @var \Drupal\Core\Language\LanguageManager
   */
  protected $languageManager;

  /**
   * The Menu Active Trail service.
   *
   * @var \Drupal\Core\Menu\MenuActiveTrail
   */
  protected $menuActiveTrail;

  /**
   * The Menu Link Tree service.
   *
   * @var \Drupal\Core\Menu\MenuLinkTree
   */
  protected $menuLinkTree;

  /**
   * The Pager Manager service.
   *
   * @var \Drupal\Core\Pager\PagerManager
   */
  protected $pagerManager;

  /**
   * The Renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The Current Route Match service.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $routeMatch;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $db;

  /**
   * Constructor.
   */
  final public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    FileUrlGeneratorInterface $file_url_generator,
    CurrentPathStack $current_path,
    DateFormatter $date_formatter,
    EntityRepositoryInterface $entity_repository,
    EntityTypeManagerInterface $entity_type_manager,
    HelperFunctionsInterface $helper_functions,
    LanguageManager $language_manager,
    MenuActiveTrail $menu_active_trail,
    MenuLinkTree $menu_link_tree,
    PagerManager $pager_manager,
    RendererInterface $renderer,
    RequestStack $request_stack,
    CurrentRouteMatch $route_match,
    Token $token,
    Connection $database
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->fileUrlGenerator = $file_url_generator;
    $this->currentPath = $current_path;
    $this->dateFormatter = $date_formatter;
    $this->entityRepository = $entity_repository;
    $this->entityTypeManager = $entity_type_manager;
    $this->helperFunctions = $helper_functions;
    $this->languageManager = $language_manager;
    $this->menuActiveTrail = $menu_active_trail;
    $this->menuLinkTree = $menu_link_tree;
    $this->pagerManager = $pager_manager;
    $this->renderer = $renderer;
    $this->currentRequest = $request_stack->getCurrentRequest();
    $this->routeMatch = $route_match;
    $this->token = $token;
    $this->db = $database;
  }

  /**
   * Implementation for the create method.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('file_url_generator'),
      $container->get('path.current'),
      $container->get('date.formatter'),
      $container->get('entity.repository'),
      $container->get('entity_type.manager'),
      $container->get('tre_preprocess_utility_functions.helper_functions'),
      $container->get('language_manager'),
      $container->get('menu.active_trail'),
      $container->get('menu.link_tree'),
      $container->get('pager.manager'),
      $container->get('renderer'),
      $container->get('request_stack'),
      $container->get('current_route_match'),
      $container->get('token'),
      $container->get('database')
    );
  }

}
