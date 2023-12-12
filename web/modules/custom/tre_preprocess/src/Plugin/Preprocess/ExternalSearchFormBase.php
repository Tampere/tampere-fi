<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\tre_preprocess\TrePreProcessPluginBase;
use Drupal\views\Entity\View;

/**
 * Base class for all external search form blocks' preprocessing.
 */
class ExternalSearchFormBase extends TrePreProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $view = View::load('search_ext')->getExecutable();
    $view->setDisplay('page_1');
    $url = $view->getDisplay()->getUrl()->toString();
    $query = $view->getExposedInput();

    $variables['action'] = $url;
    $variables['query'] = $query;

    $variables['use_search_toggle'] = FALSE;

    return $variables;
  }

}
