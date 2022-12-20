<?php

namespace Drupal\tre_news_archive\Commands;

use Drush\Commands\DrushCommands;
use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Drush commandfile for the tre_news_archive module.
 */
class TreNewsArchiveCommands extends DrushCommands {

  /**
   * Save news as pdf.
   *
   * @param string $year_string
   *   The year the news are saved to pdf archive file.
   *
   * @command tre_news_archive:save-news
   * @aliases save-news
   *
   * @usage tre_news_archive:save-news
   */
  public function saveNewsAsPdf(string $year_string = NULL) {

    if ($year_string) {
      $year = intval($year_string);
      $time = DrupalDateTime::createFromArray(['year' => $year], new \DateTimeZone('UTC'));

      _tre_news_archive_news_to_pdf($time, FALSE);
    }
    else {
      $now = new DrupalDateTime('now', new \DateTimeZone('UTC'));

      _tre_news_archive_news_to_pdf($now);
    }
  }

  /**
   * Archive news from the year before last.
   *
   * @command tre_news_archive:archive-news
   * @aliases archive-news
   *
   * @usage tre_news_archive:archive-news
   */
  public function archiveNews() {
    $now = new DrupalDateTime('now', new \DateTimeZone('UTC'));

    _tre_news_archive_archive_news($now);

  }

}
