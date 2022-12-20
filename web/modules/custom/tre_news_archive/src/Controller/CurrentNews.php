<?php

namespace Drupal\tre_news_archive\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Controller routines for saving current news.
 */
class CurrentNews extends ControllerBase {

  /**
   * Save news as pdf.
   *
   * @command tre_news_archive:save-news
   * @aliases save-news
   *
   * @usage tre_news_archive:save-news
   */
  public function save() {
    $now = new DrupalDateTime();
    $now->setTimezone(new \DateTimeZone('UTC'));

    _tre_news_archive_news_to_pdf($now, FALSE);

    $messenger = $this->messenger();
    $messenger->addMessage($this->t('Current news are saved.'), MessengerInterface::TYPE_STATUS);

    $url = Url::fromUri('internal:/admin/content/news_archive');
    return new RedirectResponse($url->toString());
  }

}
