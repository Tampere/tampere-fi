<?php

namespace Drupal\tre_search_exclusion\EventSubscriber;

use Drupal\search_api\Event\QueryPreExecuteEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Provides the FrontPageSearchResultExclusion class.
 *
 * @package Drupal\tre_search_exclusion\EventSubscriber
 */
class FrontPageSearchResultExclusion implements EventSubscriberInterface {

  /**
   * The config_factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Constructs a new class instance.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config_factory service.
   */
  public function __construct(ConfigFactoryInterface $configFactory) {
    $this->configFactory = $configFactory;
  }

  /**
   * Reacts to the query alter event.
   *
   * @param \Drupal\search_api\Event\QueryPreExecuteEvent $event
   *   The query alter event.
   */
  public function queryAlter(QueryPreExecuteEvent $event) {
    $query = $event->getQuery();
    $config_factory = $this->configFactory;
    $site_config = $config_factory->get('system.site');
    $front_page = $site_config->get('page')['front'];
    $front_page_nid = str_replace('/node/', '', $front_page);
    $index = $query->getIndex();
    $fields = $index->getFields();
    if (array_key_exists('nid', $fields) && ctype_digit($front_page_nid)) {
      $query->addCondition('nid', $front_page_nid, '<>');
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // Workaround to avoid a fatal error during site install from existing
    // config.
    // @see https://www.drupal.org/project/facets/issues/3199156
    if (!class_exists('\Drupal\search_api\Event\SearchApiEvents', TRUE)) {
      return [];
    }

    return [
      SearchApiEvents::QUERY_PRE_EXECUTE => 'queryAlter',
    ];
  }

}
