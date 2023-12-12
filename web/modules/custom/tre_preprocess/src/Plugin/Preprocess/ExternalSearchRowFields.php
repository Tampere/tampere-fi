<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\tre_preprocess\TrePreProcessPluginBase;

/**
 * Preprocessing for a search result row's fields.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.views_views__search_ext_row_fields",
 *   hook = "views_view_fields__search_ext"
 * )
 */
class ExternalSearchRowFields extends TrePreProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {

    $fields = $variables['fields'];
    // $variables['fields'] contains all fields defined in views.view.search as
    // long as the fields are not marked 'hidden'. If they are, the fields are
    // not visible in $variables['fields'] at all and cannot be used for
    // checking or manipulating results.
    $external_url = reset($fields['external_doc_uri']->raw);
    $external_title = reset($fields['external_doc_title']->raw);
    // The 'breadcrumbs' section accepts arrays, so sending as-is.
    $breadcrumbs = $fields['sm_mapped_content_type']->raw;

    $elements['external_search_item'] = [
      '#type' => 'pattern',
      '#id' => 'listing_item',
      '#variant' => 'external-search',
      '#fields' => [
        'listing_item__type' => $this->t('External site'),
        'listing_item__heading' => $external_title,
        'listing_item__url' => $external_url,
        'listing_item__breadcrumbs' => $breadcrumbs,
      ],
    ];

    $variables['external_search_item_content'] = $this->renderer->render($elements);

    // Making the fields empty so the template won't try to output anything in
    // the regular manner.
    $variables['fields'] = [];

    return $variables;
  }

}
