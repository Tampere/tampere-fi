<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\group\Entity\Storage\GroupRelationshipStorageInterface;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;
use Drupal\tre_preprocess_utility_functions\Utils\HelperFunctionsInterface;
use Drupal\views\Views;

/**
 * Portfolio content listing paragraph preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.paragraph__portfolio_content_listing",
 *   hook = "paragraph__portfolio_content_listing"
 * )
 */
class PortfolioContentListing extends TrePreProcessPluginBase {

  /**
   * Display ID for block displaying liftups with image and date.
   */
  const LIFTUPS_DISPLAY_ID = 'liftups_with_image_and_date';

  /**
   * Display ID for block displaying liftups with image without date.
   */
  const LIFTUPS_WO_DATE_DISPLAY_ID = 'liftups_with_image_without_date';

  /**
   * Display ID for block displaying liftups without image with date.
   */
  const LIFTUPS_WO_IMAGE_DISPLAY_ID = 'liftups_without_image_with_date';

  /**
   * Display ID for block displaying liftups without image and date.
   */
  const LIFTUPS_WO_IMAGE_AND_DATE_DIPLAY_ID = 'liftups_without_image_and_date';

  /**
   * The available taxonomy vocabularies for the listing.
   */
  const AVAILABLE_TAXONOMY_VOCABULARIES = [
    'topics',
    'keywords',
    'life_situations',
    'geographical_areas',
    'minisites',
  ];

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $paragraph = $variables['paragraph'];

    if (!($paragraph instanceof ParagraphInterface)) {
      return $variables;
    }

    /** @var \Drupal\paragraphs\ParagraphInterface $translated_paragraph */
    $translated_paragraph = $this->entityRepository->getTranslationFromContext($paragraph);

    $display_dates = $this->helperFunctions->getFieldValueString(
        $paragraph, 'field_display_publish_dates') === HelperFunctionsInterface::BOOLEAN_FIELD_TRUE;

    $variables['highlighted_liftup'] = $translated_paragraph->get('field_highlighted_content')->view(
      $display_dates ? 'default' : 'without_date_view_mode'
    );

    $portfolio_content_listing_block = $this->getRenderedView($translated_paragraph, $display_dates);

    if (empty($portfolio_content_listing_block)) {
      return $variables;
    }

    $variables['portfolio_content_listing_block'] = $portfolio_content_listing_block;

    $hide_previous_content_link_field_value = $this->helperFunctions->getFieldValueString($translated_paragraph, 'field_hide_previous_content_link');
    $display_previous_content_link = $hide_previous_content_link_field_value !== HelperFunctionsInterface::BOOLEAN_FIELD_TRUE;

    if ($display_previous_content_link) {
      $listing_node = $this->getPortfolioListingNodeForParagraph($translated_paragraph);

      if (!($listing_node instanceof NodeInterface)) {
        return $variables;
      }

      $previous_content_link = [
        'text' => $this->t('All contents in this section', [], ['context' => 'Portfolio content listing paragraph']),
        'url' => $listing_node->toUrl()->toString(TRUE)->getGeneratedUrl(),
      ];

      $variables['listing_links'][] = $previous_content_link;
    }

    return $variables;
  }

  /**
   * Returns the block display ID that matches the given selections.
   *
   * @param string $content_display_style
   *   The key for the selected content display style.
   * @param bool $display_dates
   *   Whether to display dates in the listing.
   *
   * @return string
   *   The block display ID for the given display style.
   */
  protected function getBlockDisplayId(string $content_display_style, bool $display_dates): string {
    if ($content_display_style === 'images') {
      return $display_dates ? self::LIFTUPS_DISPLAY_ID : self::LIFTUPS_WO_DATE_DISPLAY_ID;
    }

    return $display_dates ? self::LIFTUPS_WO_IMAGE_DISPLAY_ID : self::LIFTUPS_WO_IMAGE_AND_DATE_DIPLAY_ID;
  }

  /**
   * Returns listing node belonging to the same group with a given paragraph.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph entity to get the associated listing node for.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The listing node entity if succesful. Otherwise null.
   */
  protected function getPortfolioListingNodeForParagraph(ParagraphInterface $paragraph) {
    $paragraph_parent_entity = $paragraph->getParentEntity();

    if (!($paragraph_parent_entity instanceof NodeInterface)) {
      return NULL;
    }

    $paragraph_parent_group = $this->helperFunctions->getNodeGroup($paragraph_parent_entity);

    if (!($paragraph_parent_group instanceof GroupInterface)) {
      return NULL;
    }

    $group_content_storage = $this->entityTypeManager->getStorage('group_content');

    if (!($group_content_storage instanceof GroupRelationshipStorageInterface)) {
      return NULL;
    }

    $group_portfolio_listing_contents = $group_content_storage->loadByGroup($paragraph_parent_group, 'group_node:portfolio_listing');

    if (empty($group_portfolio_listing_contents)) {
      return NULL;
    }

    $group_portfolio_listing_content = reset($group_portfolio_listing_contents);

    if (!($group_portfolio_listing_content instanceof GroupRelationshipInterface)) {
      return NULL;
    }

    $listing_node = $group_portfolio_listing_content->getEntity();

    if (!($listing_node instanceof NodeInterface)) {
      return NULL;
    }

    /** @var \Drupal\node\NodeInterface $translated_listing_node */
    $translated_listing_node = $this->entityRepository->getTranslationFromContext($listing_node);

    return $translated_listing_node;
  }

  /**
   * Renders a certain view based on paragraph values.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph entity to base the view on.
   * @param bool $display_dates
   *   Whether to display dates in the listing.
   *
   * @return array|null
   *   The view as a renderable array if successful. Null if unsuccessful.
   */
  protected function getRenderedView(ParagraphInterface $paragraph, bool $display_dates) {
    $view = Views::getView('portfolio_content_listing');

    $selected_content_display_style = $paragraph->get('field_liftup_display')->getString();

    $block_machine_name = $this->getBlockDisplayId($selected_content_display_style, $display_dates);

    if (empty($view) || !$view->access($block_machine_name)) {
      return NULL;
    }

    $view->setDisplay($block_machine_name);

    $selected_taxonomy_values = $this->helperFunctions->getParagraphTaxonomyTerms($paragraph, self::AVAILABLE_TAXONOMY_VOCABULARIES);

    $combined_taxonomy_values = [];
    foreach ($selected_taxonomy_values as $list) {
      foreach ($list as $tid) {
        $combined_taxonomy_values[$tid] = $tid;
      }
    }

    $taxonomy_argument = empty($combined_taxonomy_values) ? 'all' : implode(',', $combined_taxonomy_values);

    if ($paragraph->hasField('field_omitted_nodes') && !$paragraph->get('field_omitted_nodes')->isEmpty()) {
      $omitted_node_ids = $paragraph->get('field_omitted_nodes')->getString();
    }

    if ($paragraph->hasField('field_highlighted_content') && !$paragraph->get('field_highlighted_content')->isEmpty()) {
      $highlighted_node_id = $paragraph->get('field_highlighted_content')->getString();
      $omitted_node_ids = empty($omitted_node_ids) ? $highlighted_node_id : "{$omitted_node_ids},{$highlighted_node_id}";
    }

    // Skip omitting by setting the argument value to null.
    $omitted_nodes_argument = empty($omitted_node_ids) ? NULL : $omitted_node_ids;

    $view->setArguments([$taxonomy_argument, $omitted_nodes_argument]);

    $node_amount = $paragraph->get('field_portfolio_liftup_amount')->getString();

    if ($node_amount !== 'all') {
      $view->setItemsPerPage(intval($node_amount));
    }

    $view->preExecute();
    $view->execute();
    $view->postExecute();

    if ($node_amount !== 'all') {
      unset($view->pager);
    }

    $renderable = $view->buildRenderable();

    if ($renderable) {
      $this->renderer->addCacheableDependency($renderable, $view);
    }

    return $renderable;
  }

}
