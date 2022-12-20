<?php

namespace Drupal\tre_jsonapi_custom\Service;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\Theme\ThemeInitializationInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\tre_jsonapi_custom\EntityRendererInterface;

/**
 * Service for rendering entities as anonymous user using the default theme.
 */
class EntityRenderer implements EntityRendererInterface {

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected RendererInterface $renderer;

  /**
   * The account_switcher service.
   *
   * @var \Drupal\Core\Session\AccountSwitcherInterface
   */
  protected AccountSwitcherInterface $accountSwitcher;

  /**
   * The theme.initialization service.
   *
   * @var \Drupal\Core\Theme\ThemeInitializationInterface
   */
  protected ThemeInitializationInterface $themeInitialization;

  /**
   * The theme.manager service.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface
   */
  protected ThemeManagerInterface $themeManager;

  /**
   * The config_factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The entity_type.manager interface.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The class constructor.
   */
  public function __construct(RendererInterface $renderer,
                              AccountSwitcherInterface $account_switcher,
                              ThemeInitializationInterface $theme_initialization,
                              ThemeManagerInterface $theme_manager,
                              ConfigFactoryInterface $config_factory,
                              EntityTypeManagerInterface $entity_type_manager) {
    $this->renderer = $renderer;
    $this->accountSwitcher = $account_switcher;
    $this->themeInitialization = $theme_initialization;
    $this->themeManager = $theme_manager;
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\search_api\Plugin\search_api\processor\RenderedItem::addFieldValues()
   * @see \Drupal\Core\Cron::run()
   * @see \Drupal\Core\Render\RendererInterface::renderPlain()
   */
  public function renderEntity(ContentEntityInterface $entity, string $view_mode, string $langcode = NULL): MarkupInterface {
    $view_builder = $this->entityTypeManager->getViewBuilder($entity->getEntityType()->id());
    $build = $view_builder->view($entity, $view_mode, $langcode);

    // Account switching logic from core cron.
    $this->accountSwitcher->switchTo(new AnonymousUserSession());

    // Theme switching logic from search_api.
    $active_theme = $this->themeManager->getActiveTheme();
    $default_theme = $this->configFactory->get('system.theme')->get('default');
    $default_theme = $this->themeInitialization->getActiveThemeByName($default_theme);

    $active_theme_switched = FALSE;
    if ($default_theme->getName() !== $active_theme->getName()) {
      $this->themeManager->setActiveTheme($default_theme);
      // Ensure that static cached default variables are set correctly,
      // especially the directory variable.
      drupal_static_reset('template_preprocess');
      $active_theme_switched = TRUE;
    }

    // As mentioned in the documentation, the renderPlain method is not actually
    // for rendering plaintext but instead rendering without assets or metadata.
    $result = $this->renderer->renderPlain($build);

    // Restore the original theme if themes got switched before.
    if ($active_theme_switched) {
      $this->themeManager->setActiveTheme($active_theme);
      // Ensure that static cached default variables are set correctly,
      // especially the directory variable.
      drupal_static_reset('template_preprocess');
    }

    $this->accountSwitcher->switchBack();

    return $result;
  }

}
