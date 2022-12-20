<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\paragraphs\ParagraphInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;
use Drupal\media\MediaInterface;
use Drupal\file\FileInterface;

/**
 * Feedback section paragraph preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.paragraph__downloadable_material",
 *   hook = "paragraph__downloadable_material"
 * )
 */
class DownloadableMaterial extends TrePreProcessPluginBase {


  /**
   * The view mode to use for images when displayed as a thumbnail.
   */
  const DOWNLOADABLE_MATERIAL_VIEW_MODE = 'downloadable_material_view_mode';

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $paragraph = $variables['paragraph'];

    if ($paragraph instanceof ParagraphInterface) {
      /** @var \Drupal\paragraphs\ParagraphInterface $translated_downloadable_material */
      $translated_downloadable_material = $this->entityRepository->getTranslationFromContext($paragraph);

      $media_entity = $translated_downloadable_material->get('field_media')->entity;

      if ($media_entity instanceof MediaInterface) {
        /** @var \Drupal\media\MediaInterface $translated_media_entity */
        $translated_media_entity = $this->entityRepository->getTranslationFromContext($media_entity);
        
        $media_name = $translated_media_entity->label();
        $variables['material_name'] = $media_name;
        $media_bundle = $translated_media_entity->bundle();

        if ($media_bundle == 'file') {
          $file_entity = $translated_media_entity->get('field_media_file')->entity;
          
          if ($file_entity instanceof FileInterface) {
            $file_uri = $file_entity->getFileUri();
            $file_url = $this->fileUrlGenerator->generateAbsoluteString($file_uri);
            $variables['material_url'] = $file_url;
          }
        }
        elseif ($media_bundle == 'image') {
          $file_entity = $translated_media_entity->get('field_media_image')->entity;
          
          if ($file_entity instanceof FileInterface) {
            $file_uri = $file_entity->getFileUri();
            $file_url = $this->fileUrlGenerator->generateAbsoluteString($file_uri);
            $variables['material_url'] = $file_url;
          }
        }
        elseif ($media_bundle == 'internal_video') {
          $file_entity = $translated_media_entity->get('field_media_video_file')->entity;
          
          if ($file_entity instanceof FileInterface) {
            $file_uri = $file_entity->getFileUri();
            $file_url = $this->fileUrlGenerator->generateAbsoluteString($file_uri);
            $variables['material_url'] = $file_url;
          }
        }
      }

      $variables['material_image'] = $translated_downloadable_material->get('field_mini_image')->view(self::DOWNLOADABLE_MATERIAL_VIEW_MODE);

    }

    return $variables;
  }

}
