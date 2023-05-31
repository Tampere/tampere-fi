<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\paragraphs\ParagraphInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;

/**
 * Social media link list paragraph preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.paragraph__social_media_link_list",
 *   hook = "paragraph__social_media_link_list"
 * )
 */
class SocialMediaLinkList extends TrePreProcessPluginBase {

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

    if (!$translated_paragraph->hasField('field_some_link_list_items') ||
         $translated_paragraph->get('field_some_link_list_items')->isEmpty()) {
      return $variables;
    }

    $list_item_paragraphs = $translated_paragraph->get('field_some_link_list_items')->referencedEntities();

    $list_items = [];
    foreach ($list_item_paragraphs as $list_item_paragraph) {
      if (!($list_item_paragraph instanceof ParagraphInterface)) {
        continue;
      }

      /** @var \Drupal\paragraphs\ParagraphInterface $translated_list_item_paragraph */
      $translated_list_item_paragraph = $this->entityRepository->getTranslationFromContext($list_item_paragraph);

      if (!$translated_list_item_paragraph->hasField('field_external_link_w_link_text') ||
           $translated_list_item_paragraph->get('field_external_link_w_link_text')->isEmpty()) {
        continue;
      }

      /** @var \Drupal\Core\Field\FieldItemList $list_item_link */
      $list_item_link = $translated_list_item_paragraph->get('field_external_link_w_link_text');

      $list_item_link_url = $this->helperFunctions->getExternalLinkFieldUrl($list_item_link);
      $list_item_link_title = $this->helperFunctions->getExternalLinkFieldTitle($list_item_link);

      if (!$translated_list_item_paragraph->hasField('field_social_media_for_link_list') ||
           $translated_list_item_paragraph->get('field_social_media_for_link_list')->isEmpty()) {
        continue;
      }

      $social_media_platform_field = $translated_list_item_paragraph->get('field_social_media_for_link_list');
      $social_media_platform_field_key = $social_media_platform_field->getString();
      $social_media_platform_field_value = $social_media_platform_field->getSetting('allowed_values')[$social_media_platform_field_key];

      // Prepares list items for the 'attachment-list' component that uses the
      // '_attachment.twig' template for individual items.
      // See: web/themes/custom/tampere/components/02-molecules/attachment-list/_attachment.twig.
      $list_items[] = [
        'link_url' => $list_item_link_url,
        'name' => $list_item_link_title,
        'additional_icon_name' => $social_media_platform_field_key,
        'additional_icon_aria_label' => $social_media_platform_field_value,
        'icon_name' => 'external',
      ];
    }

    $variables['list_items'] = $list_items;

    return $variables;
  }

}
