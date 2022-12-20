<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\config_pages\Entity\ConfigPages;
use Drupal\entity_reference_revisions\EntityReferenceRevisionsFieldItemList;
use Drupal\tre_preprocess\TrePreProcessPluginBase;

/**
 * Footer region preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.region__footer",
 *   hook = "region__footer"
 * )
 */
class FooterRegion extends TrePreProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $current_path = $this->currentPath->getPath();
    $group_id_from_path = $this->helperFunctions->getGroupIdFromSearchViewPath($current_path);

    $current_language_id = $this->languageManager->getCurrentLanguage()->getId();
    $variables['group_footer_content'] = $this->getGroupFooterContent($current_language_id, $group_id_from_path);

    $variables['is_minisite'] = $this->helperFunctions->isMinisite($group_id_from_path);

    $footer_information_config_page = ConfigPages::config('footer_information');

    if (!is_null($footer_information_config_page)) {
      if (!$footer_information_config_page->get('field_first_text_column')->isEmpty()) {
        $variables['first_footer_text_column'] = $footer_information_config_page->get('field_first_text_column')->view('default');
      }

      if (!$footer_information_config_page->get('field_second_text_column')->isEmpty()) {
        $variables['second_footer_text_column'] = $footer_information_config_page->get('field_second_text_column')->view('default');
      }

      if (!$footer_information_config_page->get('field_social_media_links')->isEmpty()) {
        $footer_social_media_links = $footer_information_config_page->get('field_social_media_links');
        $all_social_media_items = $this->getSocialMediaItems($footer_social_media_links);
        $variables['social_media_items'] = $all_social_media_items;
      }

      if (!$footer_information_config_page->get('field_site_statement_links')->isEmpty()) {
        $footer_links = $footer_information_config_page->get('field_site_statement_links');
        $variables['site_statement_links'] = $footer_links;
      }

      if (!$footer_information_config_page->get('field_site_copyrights')->isEmpty()) {
        $variables['site_copyright_information'] = $footer_information_config_page->get('field_site_copyrights')->value;
      }
    }

    $this->renderer->addCacheableDependency($variables, $footer_information_config_page);

    return $variables;
  }

  /**
   * Returns the footer content for a group.
   *
   * @param string $language_id
   *   The ID for the current language.
   * @param int|null $group_id
   *   The group ID. Defaults to NULL.
   *
   * @return array|null
   *   Render array for the group's footer content field.
   *   Null if not applicable.
   */
  protected function getGroupFooterContent($language_id, $group_id = NULL) {
    $group = NULL;
    if (isset($group_id)) {
      /** @var \Drupal\group\Entity\GroupInterface $group */
      $group = $this->entityTypeManager->getStorage('group')->load($group_id);
    }

    $current_group = $group ?? $this->helperFunctions->getCurrentGroup();
    if ($current_group && $current_group->hasTranslation($language_id)) {
      $translation = $current_group->getTranslation($language_id);
      $has_footer_content_field = $translation->hasField('field_footer_content');
      if ($has_footer_content_field && !$translation->get('field_footer_content')->isEmpty()) {
        return $translation->get('field_footer_content')->view();
      }
    }

    return NULL;
  }

  /**
   * Returns an array of social media items.
   *
   * Each item includes the URL and icon name for the
   * associated social media platform.
   *
   * @param \Drupal\entity_reference_revisions\EntityReferenceRevisionsFieldItemList $social_media_links
   *   The social media links field.
   *
   * @return array
   *   The array of social media items.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  protected function getSocialMediaItems(EntityReferenceRevisionsFieldItemList $social_media_links) {

    $all_social_media_items = [];
    /** @var \Drupal\entity_reference_revisions\Plugin\Field\FieldType\EntityReferenceRevisionsItem $social_media_link */
    foreach ($social_media_links as $social_media_link) {
      /** @var \Drupal\paragraphs\ParagraphInterface $link_paragraph */
      $link_paragraph = $social_media_link->entity;
      $link_field = $link_paragraph->get('field_social_media_profile_url');
      $social_media_url = $this->helperFunctions->getExternalLinkFieldUrl($link_field);
      $social_media_platform = $link_paragraph->get('field_social_media')->getString();
      $social_media_link_alt = $link_paragraph->get('field_link_alt_text')->getString();

      $all_social_media_items[] = [
        'url' => $social_media_url,
        'icon' => $social_media_platform,
        'aria_label' => $social_media_link_alt,
      ];
    }

    return $all_social_media_items;
  }

}
