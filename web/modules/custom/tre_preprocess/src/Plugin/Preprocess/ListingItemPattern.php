<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;

/**
 * Listing item pattern preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.pattern_listing_item",
 *   hook = "pattern_listing_item"
 * )
 */
class ListingItemPattern extends TrePreProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $pattern_context = $variables['context'];

    $bundle = $pattern_context->getProperty('bundle');
    $node = $pattern_context->getProperty('entity');

    $mapped_types = $this->getMappedSearchContentTypes();
    if (isset($mapped_types[$bundle])) {
      $variables['listing_item__type'] = $mapped_types[$bundle];
    }

    if ($node instanceof NodeInterface) {
      $translated_node = $this->entityRepository->getTranslationFromContext($node);
      if ($translated_node instanceof NodeInterface) {
        $current_group = $this->helperFunctions->getNodeGroup($translated_node);
        if ($current_group instanceof GroupInterface) {
          $is_minisite = $current_group->bundle() == 'minisite';
          $variables['listing_item__palette'] = $this->helperFunctions->getGroupPaletteClass($current_group);

          if (!empty($is_minisite)) {
            /** @var \Drupal\group\Entity\GroupInterface $translated_group */
            $translated_group = $this->entityRepository->getTranslationFromContext($current_group);
            $variables['listing_item__minisite_title'] = $translated_group->label();
            $variables['listing_item__type'] = $this->t('On subsite @title', ['@title' => $translated_group->label()], ['context' => 'Search result type for minisite content']);
          }
        }

        $variables['listing_item__breadcrumbs'] = $this->getBreadcrumbTitles($translated_node);

        $node_id = $translated_node->id();
        $variables['#cache']['tags'][] = "node:{$node_id}";

        if ($bundle == 'person') {
          // Content of type 'person' should not link to anywhere currently.
          return $variables;
        }
        elseif ($bundle == 'place_of_business') {
          if (!$translated_node->get('field_links')->isEmpty()) {
            // Taking the first link at the request of client.
            $variables['listing_item__url'] = Url::fromUri($translated_node->get('field_links')[0]->uri)->toString();
            $variables['listing_item__link__aria_label'] = $translated_node->get('field_links')[0]->title;
          }
        }
        else {
          $variables['listing_item__url'] = $translated_node->toUrl()->toString(TRUE)->getGeneratedUrl();
          $variables['listing_item__link__aria_label'] = $translated_node->getTitle();
        }
      }

    }

    return $variables;

  }

  /**
   * Returns an array of breadcrumb titles for the given node.
   *
   * @return array|null
   *   An array of breadcrumb titles if successful, null otherwise.
   */
  protected function getBreadcrumbTitles($node) {
    $breadcrumbs = $this->helperFunctions->getBreadcrumbs($node);

    if (!$breadcrumbs) {
      return NULL;
    }

    $links = $breadcrumbs['#links'];

    // Remove last link pointing to node itself.
    array_pop($links);

    $breadcrumb_link_texts = [];
    foreach ($links as $key => $link) {
      // Ignore first link text as it is pointing to the front page.
      if ($key !== array_key_first($links)) {
        $breadcrumb_link_texts[] = $link->getText();
      }
    }

    return $breadcrumb_link_texts;
  }

  /**
   * Get the list of mapped content types to show in search.
   *
   * @return array
   *   The list of mapped content types to show in search.
   */
  protected function getMappedSearchContentTypes() {
    return [
      'blog_article' => $this->t('Blog post', [], ['context' => 'Mapped content type']),
      'front_page' => $this->t('Front page', [], ['context' => 'Mapped content type']),
      'news_item' => $this->t('News item', [], ['context' => 'Mapped content type']),
      'notice' => $this->t('Notice', [], ['context' => 'Mapped content type']),
      'rich_article' => $this->t('Rich article', [], ['context' => 'Mapped content type']),
      'basic_content_page' => $this->t('Page', [], ['context' => 'Mapped content type']),
      'moderated_page' => $this->t('Page', [], ['context' => 'Mapped content type']),
      'project' => $this->t('Page', [], ['context' => 'Mapped content type']),
      'organization' => $this->t('Page', [], ['context' => 'Mapped content type']),
      'place' => $this->t('Page', [], ['context' => 'Mapped content type']),
      'collection_page' => $this->t('Page', [], ['context' => 'Mapped content type']),
      'city_planning_and_constructions' => $this->t('Page', [], ['context' => 'Mapped content type']),
      'zoning_information' => $this->t('Page', [], ['context' => 'Mapped content type']),
      'involvement_opportunity' => $this->t('Page', [], ['context' => 'Mapped content type']),
    ];

  }

}
