<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;
use Drupal\tre_preprocess_utility_functions\Utils\HelperFunctionsInterface;

/**
 * List of attachments paragraph preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.paragraph__list_of_attachments",
 *   hook = "paragraph__list_of_attachments"
 * )
 */
class AttachmentList extends TrePreProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $paragraph = $variables['paragraph'];

    if (!($paragraph instanceof ParagraphInterface)) {
      return $variables;
    }

    $lang = $this->languageManager->getCurrentLanguage()->getId();
    $variables['site_lang'] = $lang;
    $attachment_paragraphs = $paragraph->get('field_attachment')->referencedEntities();

    $attachments = [];
    foreach ($attachment_paragraphs as $attachment_paragraph) {
      if ($attachment_paragraph instanceof ParagraphInterface) {
        /** @var \Drupal\paragraphs\ParagraphInterface $translated_attachment_paragraph */
        $translated_attachment_paragraph = $this->entityRepository->getTranslationFromContext($attachment_paragraph);

        $media_entity = $translated_attachment_paragraph->get('field_media')->entity;

        if (!($media_entity instanceof MediaInterface)) {
          continue;
        }

        /** @var \Drupal\media\MediaInterface $translated_media_entity */
        $translated_media_entity = $this->entityRepository->getTranslationFromContext($media_entity);

        $media_name = $translated_media_entity->label();

        $file_entity = $translated_media_entity->get('field_media_file')->entity;

        if (!($file_entity instanceof FileInterface)) {
          continue;
        }

        $file_uri = $file_entity->getFileUri();
        $file_url = $this->fileUrlGenerator->generateAbsoluteString($file_uri);

        $file_extension = $this->helperFunctions->getFileExtensionFromUrl($file_url);
        $file_size = format_size($file_entity->getSize());

        $file_info = '';
        if ($file_extension) {
          $file_info = "({$file_extension})";
        }
        if ($file_size) {
          $file_info = $file_info . " ({$file_size})";
        }

        // Check if description from the media file entity is to be used
        // for this attachment.
        $use_media_description = $this->helperFunctions->getFieldValueString($translated_attachment_paragraph, 'field_generic_boolean_value');
        if ($use_media_description === HelperFunctionsInterface::BOOLEAN_FIELD_TRUE) {
          $attachment_description = $translated_media_entity->get('field_media_description')->getString();
        }
        else {
          $attachment_description = $translated_attachment_paragraph->get('field_description_text')->getString();
        }

        $attachments[] = [
          'name' => $media_name,
          'file_info' => $file_info,
          'link_url' => $file_url,
          'icon_name' => 'download',
          'summary' => $attachment_description,
        ];
      }
    }

    $variables['#cache']['tags'][] = 'media_list:file';

    $variables['attachments'] = $attachments;

    return $variables;
  }

}
