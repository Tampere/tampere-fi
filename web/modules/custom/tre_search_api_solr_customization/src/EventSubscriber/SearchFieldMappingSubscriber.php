<?php

namespace Drupal\tre_search_api_solr_customization\EventSubscriber;

use Drupal\search_api_solr\Event\PostFieldMappingEvent;
use Drupal\search_api_solr\Event\SearchApiSolrEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides the SearchFieldMappingSubscriber class.
 *
 * @package Drupal\tre_search_api_solr_customization\EventSubscriber
 */
class SearchFieldMappingSubscriber implements EventSubscriberInterface {

  /**
   * Reacts to the post field mapping event.
   *
   * @param \Drupal\search_api_solr\Event\PostFieldMappingEvent $event
   *   The post field mapping event.
   */
  public function fieldMappingAlter(PostFieldMappingEvent $event) {
    $field_mapping = $event->getFieldMapping();

    // By default the group search is based on itm_gid which includes
    // multiple groups, but we want to be based on its_gid which contains
    // a single group id.
    $field_mapping['gid'] = 'its_gid';
    $event->setFieldMapping($field_mapping);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      SearchApiSolrEvents::POST_FIELD_MAPPING => 'fieldMappingAlter',
    ];
  }

}
