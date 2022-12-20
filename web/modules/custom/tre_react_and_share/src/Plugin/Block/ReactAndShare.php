<?php

namespace Drupal\tre_react_and_share\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Language\LanguageManager;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Site\Settings;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block for embedding 'React & Share'.
 *
 * @Block(
 *   id = "react_and_share",
 *   admin_label = @Translation("React & Share"),
 *   context_definitions = {
 *     "node" = @ContextDefinition("entity:node", required = TRUE,
 *       label = @Translation("Node Context")
 *     ),
 *   }
 * )
 */
final class ReactAndShare extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The Language Manager service.
   *
   * @var \Drupal\Core\Language\LanguageManager
   */
  protected $languageManager;

  /**
   * Constructor.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    LanguageManager $language_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $current_lang_code = $this->languageManager->getCurrentLanguage()->getId();

    $api_keys = Settings::get('react_and_share_api_keys');

    $api_key = '';
    if (is_array($api_keys) && isset($api_keys[$current_lang_code])) {
      $api_key = $api_keys[$current_lang_code];
    }

    $categories = Settings::get('react_and_share_categories');

    $categories_for_language = [];
    if (is_array($categories) && isset($categories[$current_lang_code])) {
      $categories_for_language = $categories[$current_lang_code];
    }

    $build['#theme'] = 'react_and_share';
    // The API key is not revealed on the front-end side by accident.
    // See https://docs.reactandshare.com/#embed-code-install
    $build['#attached']['drupalSettings']['tampere']['react_and_share']['key'] = $api_key;
    $build['#attached']['drupalSettings']['tampere']['react_and_share']['categories'] = $categories_for_language;
    $build['#attached']['library'][] = 'tre_react_and_share/react-and-share';

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return Cache::mergeTags(parent::getCacheTags());
  }

}
