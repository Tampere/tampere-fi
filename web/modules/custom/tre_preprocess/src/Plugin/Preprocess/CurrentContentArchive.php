<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\tre_preprocess\TrePreProcessPluginBase;
use Drupal\Core\Url;

/**
 * Current content archive view preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.views_view__current_content_archive",
 *   hook = "views_view__current_content_archive"
 * )
 */
class CurrentContentArchive extends TrePreProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $current_language_id = $this->languageManager->getCurrentLanguage()->getId();
    $variables['news_and_article_archive_url'] = Url::fromRoute("view.news_and_article_archive.archive_{$current_language_id}");

    return $variables;
  }

}
