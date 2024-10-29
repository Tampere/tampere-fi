<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;
use Drupal\tre_preprocess_utility_functions\Utils\HelperFunctionsInterface;

/**
 * Current listing paragraph type preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.paragraph__current_listing",
 *   hook = "paragraph__current_listing"
 * )
 */
class CurrentListing extends TrePreProcessPluginBase {

  /**
   * The default view mode to use for current listing liftups.
   */
  const DEFAULT_LIFTUP_VIEW_MODE = 'current_liftup_view_mode';

  /**
   * The view mode to use for current listing liftups without image.
   */
  const LIFTUP_WO_IMAGE_VIEW_MODE = 'current_liftup_wo_image_view_mode';

  /**
   * The liftup view modes key'd by their display types.
   */
  const AVAILABLE_LIFTUP_DISPLAY_TYPES = [
    'images' => self::DEFAULT_LIFTUP_VIEW_MODE,
    'no_images' => self::LIFTUP_WO_IMAGE_VIEW_MODE,
  ];

  /**
   * The shared taxonomy vocabularies for the listing.
   */
  const AVAILABLE_SHARED_TAXONOMY_VOCABULARIES = [
    'topics',
    'keywords',
    'life_situations',
    'geographical_areas',
    'minisites',
  ];

  /**
   * The content type specific taxonomy vocabularies for the listing.
   */
  const CONTENT_TYPE_SPECIFIC_TAXONOMY_VOCABULARIES = [
    'blogs' => 'blog_article',
    'notice_types' => 'notice',
  ];

  /**
   * The content types for the group filtering.
   */
  const GROUP_FILTER_CONTENT_TYPES = [
    'blog_article',
    'news_item',
    'small_news_item',
    'rich_article',
    'notice',
  ];

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $paragraph = $variables['paragraph'];

    $current_language_id = $this->languageManager->getCurrentLanguage()->getId();

    if ($paragraph instanceof ParagraphInterface) {
      /** @var \Drupal\paragraphs\ParagraphInterface $translated_paragraph */
      $translated_paragraph = $this->entityRepository->getTranslationFromContext($paragraph);

      if ($translated_paragraph->hasField('field_highlighted_content') && !$translated_paragraph->get('field_highlighted_content')->isEmpty()) {
        $referenced_entities = $translated_paragraph->get('field_highlighted_content')->referencedEntities();
        $highlighted_node = reset($referenced_entities);

        if ($highlighted_node instanceof NodeInterface) {
          /** @var \Drupal\node\NodeInterface $translated_highlighted_node */
          $translated_highlighted_node = $this->entityRepository->getTranslationFromContext($highlighted_node);

          $node_is_published = $translated_highlighted_node->isPublished();
          if (!$node_is_published) {
            // If the highlighted node isn't published, set content to an empty
            // array to prevent the highlighted liftup from being displayed.
            // The highlighted liftup is currently the only field in content.
            $variables['content'] = [];
          }
        }
      }

      [$current_listing_node_ids, $selected_content_types] = $this->getNodeIdsForCurrentListingParagraph($translated_paragraph, $current_language_id);

      // Prevent system from loading all nodes due to empty argument.
      if (empty($current_listing_node_ids)) {
        return $variables;
      }

      $current_listing_nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($current_listing_node_ids);

      $selected_liftup_display_style = $translated_paragraph->get('field_liftup_display')->getString();
      $liftup_view_mode = $this->getLiftupViewMode($selected_liftup_display_style);

      $renderable_current_listing_liftups = [];
      foreach ($current_listing_nodes as $current_listing_node) {
        $renderable_current_listing_liftups[] = $this->entityTypeManager->getViewBuilder('node')->view($current_listing_node, $liftup_view_mode);
      }

      foreach ($selected_content_types as $content_type) {
        $variables['#cache']['tags'][] = "node_list:{$content_type}";
      }

      $variables['current_listing_liftups'] = $renderable_current_listing_liftups;

      // Construct archive links.
      $paragraph_id = $paragraph->getRevisionId();
      if ($current_language_id == 'en') {
        $more_link_url = '/en/current-content-archive/' . $paragraph_id;
        $archive_link_url = '/en/news-and-article-archive';
      }
      else {
        $more_link_url = '/ajankohtaisarkisto/' . $paragraph_id;
        $archive_link_url = '/uutis-ja-artikkeliarkisto';
      }
      $display_archive_link_value = $this->helperFunctions->getFieldValueString($translated_paragraph, 'field_display_archive_link') === HelperFunctionsInterface::BOOLEAN_FIELD_TRUE;
      $hide_previous_content_link_value = $this->helperFunctions->getFieldValueString($translated_paragraph, 'field_hide_previous_content_link') === HelperFunctionsInterface::BOOLEAN_FIELD_TRUE;
      $link_list__items = [];
      $more_link = [
        'text' => $this->t('See all contents in this section'),
        'url' => $more_link_url,
      ];
      $archive_link = [
        'text' => $this->t('Search and filter all current content'),
        'url' => $archive_link_url,
      ];
      // The order of adding the items in the array matters, so adding the
      // 'more_link' first.
      if (!$hide_previous_content_link_value) {
        $link_list__items[] = $more_link;
      }
      if ($display_archive_link_value) {
        $link_list__items[] = $archive_link;
      }
      $variables['link_list__items'] = $link_list__items;
    }

    return $variables;
  }

  /**
   * Returns the view mode that matches the selected liftup display type.
   *
   * @param string $liftup_display_type
   *   The key for the selected liftup display type.
   *
   * @return string
   *   The correct view mode for the given display type.
   */
  protected function getLiftupViewMode($liftup_display_type): string {
    if (!array_key_exists($liftup_display_type, self::AVAILABLE_LIFTUP_DISPLAY_TYPES)) {
      return self::DEFAULT_LIFTUP_VIEW_MODE;
    }

    return self::AVAILABLE_LIFTUP_DISPLAY_TYPES[$liftup_display_type];
  }

  /**
   * Produces the filtered nids and selected content types for the paragraph.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $translated_paragraph
   *   The paragraph to get the filtered nids and used content types for.
   * @param string $current_language_id
   *   The code language to get the results in.
   * @param bool $use_paragraph_listing_limit
   *   Whether to consider the listing amount in the paragraph or to return all
   *   the node ids returned by the query.
   *
   * @return array
   *   An array containing two arrays: the first member is the node id list and
   *   the second member is the list of the content types selected in the
   *   paragraph.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getNodeIdsForCurrentListingParagraph(ParagraphInterface $translated_paragraph, $current_language_id, $use_paragraph_listing_limit = TRUE): array {
    $current_listing_content_sources_field_values = $translated_paragraph->get('field_content_sources')->getValue();
    $selected_content_sources = $this->helperFunctions->getListFieldValues($current_listing_content_sources_field_values);

    if (in_array("display_minisite_content", $selected_content_sources, TRUE) && !in_array("display_mainsite_content", $selected_content_sources, TRUE)) {
      $group_filter = TRUE;
    }
    elseif (!in_array("display_minisite_content", $selected_content_sources, TRUE) && in_array("display_mainsite_content", $selected_content_sources, TRUE)) {
      $group_filter = FALSE;
    }
    else {
      $group_filter = NULL;
    }

    $current_listing_content_types_field_values = $translated_paragraph->get('field_current_content_types')->getValue();
    $selected_content_types = $this->helperFunctions->getListFieldValues($current_listing_content_types_field_values);

    $selected_shared_taxonomy_values = $this->helperFunctions->getParagraphTaxonomyTerms($translated_paragraph, self::AVAILABLE_SHARED_TAXONOMY_VOCABULARIES);
    $selected_content_type_specific_taxonomy_values = $this->helperFunctions->getParagraphTaxonomyTerms($translated_paragraph, array_keys(self::CONTENT_TYPE_SPECIFIC_TAXONOMY_VOCABULARIES));

    if ($use_paragraph_listing_limit) {
      $selected_listing_liftup_amount = $translated_paragraph->get('field_liftup_amount')->getString();
    }
    else {
      $selected_listing_liftup_amount = 0;
    }

    $omitted_node_ids = [];
    if (!$translated_paragraph->get('field_highlighted_content')->isEmpty()) {
      $highlighted_content_field_referenced_entities = $translated_paragraph->get('field_highlighted_content')->referencedEntities();
      $highlighted_node = reset($highlighted_content_field_referenced_entities);

      if ($highlighted_node instanceof NodeInterface) {
        /** @var \Drupal\node\NodeInterface $highlighted_node */
        $omitted_node_ids[] = $highlighted_node->id();
      }
    }

    if (!$translated_paragraph->get('field_omitted_nodes')->isEmpty()) {
      $omitted_content_field_referenced_entities = $translated_paragraph->get('field_omitted_nodes')->referencedEntities();
      foreach ($omitted_content_field_referenced_entities as $omitted_referenced_entity) {
        if ($omitted_referenced_entity instanceof NodeInterface) {
          /** @var \Drupal\node\NodeInterface $omitted_referenced_entity */
          $omitted_node_ids[] = $omitted_referenced_entity->id();
        }
      }
    }

    $current_listing_node_ids = $this->getCurrentListingNodeIds(
      $current_language_id,
      $selected_content_types,
      $selected_shared_taxonomy_values,
      $selected_content_type_specific_taxonomy_values,
      $selected_listing_liftup_amount,
      $group_filter,
      $omitted_node_ids
    );

    return [$current_listing_node_ids, $selected_content_types];
  }

  /**
   * Returns node IDs that match the taxonomy terms given as parameter.
   *
   * @param string $current_language_id
   *   The ID for the currently active user interface language.
   * @param array $content_types
   *   The content types to include in the results.
   * @param array $shared_taxonomy_values
   *   An array of shared taxonomy terms key'd by taxonomy vocabulary.
   * @param array $content_type_specific_taxonomy_values
   *   An array of content type specific taxonomy terms key'd by taxonomy
   *   vocabulary. These will be added to the query as an OR group as one
   *   node cannot contain more than one of these terms, but the listing
   *   allows selecting multiple different terms from the same taxonomy.
   * @param int $node_amount
   *   The amount of node IDs to return.
   * @param bool|null $minisite_group_content_inclusion
   *   Boolean if minisite group content should be included or excluded,
   *   optional.
   * @param array $nids_to_ignore
   *   The IDs for nodes that should not be included in the results. Optional.
   *
   * @return array|null
   *   An array of node IDs if successful. Null otherwise.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getCurrentListingNodeIds(
    $current_language_id,
    array $content_types,
    array $shared_taxonomy_values,
    array $content_type_specific_taxonomy_values,
    $node_amount,
    $minisite_group_content_inclusion,
    array $nids_to_ignore = [],
  ): ?array {
    $node_query = $this->entityTypeManager->getStorage('node')->getQuery()->accessCheck(TRUE);
    $node_query
      ->condition('type', $content_types, 'IN')
      ->condition('status', 1)
      ->condition('langcode', $current_language_id)
      ->sort('published_at', 'DESC', $current_language_id);

    // This is a temporary fix.
    // The current version of the 'Publication Date' module saves the default
    // publication date (2147483647) in the database for drafts.
    // This sometimes results in nodes showing up in the listing with
    // this date, and we want to prevent them from ever showing up.
    $node_query->condition('published_at', 2147464800, '<');

    if ($node_amount > 0) {
      $node_query->range(0, $node_amount);
    }

    if (!empty($nids_to_ignore)) {
      $node_query->condition('nid', $nids_to_ignore, 'NOT IN');
    }

    foreach ($shared_taxonomy_values as $taxonomy => $terms) {
      if (!empty($terms)) {
        foreach ($terms as $term) {
          $node_query->condition($node_query->andConditionGroup()
            ->condition("field_{$taxonomy}", $term, '=', $current_language_id));
        }
      }
    }

    foreach ($content_type_specific_taxonomy_values as $taxonomy => $terms) {
      if (!empty($terms)) {
        $orCondition = $node_query->orConditionGroup();
        $orCondition->condition('type', self::CONTENT_TYPE_SPECIFIC_TAXONOMY_VOCABULARIES[$taxonomy], '<>');
        foreach ($terms as $term) {
          if ($taxonomy == 'notice_types') {
            $orCondition->condition("field_notice_type", $term, '=', $current_language_id);
          }
          else {
            $orCondition->condition("field_blog", $term, '=', $current_language_id);
          }
        }
        $node_query->condition($orCondition);
      }
    }

    if (!is_null($minisite_group_content_inclusion)) {
      $minisites = $this->entityTypeManager->getStorage('group')->loadByProperties(['type' => 'minisite']);

      $minisite_nids = [];
      /** @var \Drupal\group\Entity\GroupInterface $minisite */
      foreach ($minisites as $minisite) {
        $nodes = $minisite->getRelatedEntities();
        foreach ($nodes as $node) {
          if (in_array($node->bundle(), self::GROUP_FILTER_CONTENT_TYPES, TRUE)) {
            $minisite_nids[] = $node->id();
          }
        }
      }
      if ($minisite_group_content_inclusion === TRUE) {
        if (!empty($minisite_nids)) {
          $node_query->condition('nid', $minisite_nids, 'IN');
        }
        else {
          return NULL;
        }
      }

      if ($minisite_group_content_inclusion === FALSE) {
        if (!empty($minisite_nids)) {
          $node_query->condition('nid', $minisite_nids, 'NOT IN');
        }
      }
    }

    return $node_query->execute();
  }

}
