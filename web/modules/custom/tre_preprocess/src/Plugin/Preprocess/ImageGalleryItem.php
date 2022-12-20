<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\paragraphs\ParagraphInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;

/**
 * Image gallery item paragraph type preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.paragraph__image_gallery_item",
 *   hook = "paragraph__image_gallery_item"
 * )
 */
class ImageGalleryItem extends TrePreProcessPluginBase {

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

    $image_information = $this->helperFunctions->getImageInformation($translated_paragraph);

    if (empty($image_information)) {
      return $variables;
    }

    $image_name = $image_information[0];
    $image_id = $image_information[2];

    $variables['image_gallery_item_name'] = $image_name;
    $variables['#cache']['tags'][] = "media:{$image_id}";

    return $variables;
  }

}
