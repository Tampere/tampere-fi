<?php

namespace Drupal\tre_contact_search\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for the Contact Search page.
 */
class ContactSearchController extends ControllerBase {

  /**
   * Builds the response.
   */
  public function build() {
    $build = [];

    $build['wrapper'] = [
      '#type' => 'html_tag',
      '#tag' => 'article',
      '#attributes' => [
        'class' => ['collection-page-content'],
      ],
    ];

    // The Header Wrapper (for the H1 and Intro Text)
    $build['wrapper']['header'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['collection-page-content__header', 'search-container-header'],
      ],
      'page_title' => [
        '#type' => 'html_tag',
        '#tag' => 'h1',
        '#value' => $this->t('Contact search'),
        '#attributes' => [
          'class' => ['hero__heading', 'h1', 'margin-bottom-large'],
        ],
      ],
      'intro_text' => [
        '#type' => 'container',
        'p1' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->t('Are you looking for contact details for the City of Tampere’s service locations or staff? Use our search tools.'),
        ],
        'p2' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->t('The service location search helps you find service locations for residents and other users of the city’s services, such as libraries, employment services offices and sports facilities. The person search provides contact details for City employees, within the scope defined by our service groups.'),
        ],
      ],
    ];

    // The Main Content Wrapper (for the search blocks)
    $build['wrapper']['main_content'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['collection-page-content__main-content'],
      ],
    ];

    // Embed the Locations Block into the main content area.
    $build['wrapper']['main_content']['locations_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['search-container-locations', 'margin-bottom-large']],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Service locations'),
      ],
      'view' => [
        '#type' => 'view',
        '#name' => 'contact_search',
        '#display_id' => 'block_locations',
      ],
    ];

    // Divider between the two search blocks.
    $build['wrapper']['main_content']['divider'] = [
      '#type' => 'html_tag',
      '#tag' => 'hr',
      '#attributes' => [
        'class' => ['search-blocks-divider'],
      ],
    ];

    // Embed the Persons Block into the main content area.
    $build['wrapper']['main_content']['persons_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['search-container-persons']],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Persons'),
      ],
      'view' => [
        '#type' => 'view',
        '#name' => 'contact_search',
        '#display_id' => 'block_persons',
      ],
    ];

    // Ensure the page cache is invalidated if the view changes.
    $build['#cache']['tags'] = ['config:views.view.contact_search'];

    return $build;
  }
}
