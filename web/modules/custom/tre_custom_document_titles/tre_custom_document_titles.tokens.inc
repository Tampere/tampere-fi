<?php

/**
 * @file
 * Provides custom token for 'TRE Custom Document Titles'.
 */

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\node\NodeInterface;
use Drupal\Core\Routing\RouteMatch;

/**
 * Implements hook_token_info().
 */
function tre_custom_document_titles_token_info() {
  $info = [];

  $info['tokens']['tre'] = [
    'node_document_title_breadcrumb' => [
      'name' => t('Node document title breadcrumb'),
      'description' => t('The breadcrumb item names formatted for use in node document titles'),
    ],
  ];

  return $info;
}

/**
 * Implements hook_tokens().
 */
function tre_custom_document_titles_tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
  $replacements = [];

  if ($type == 'tre') {
    foreach ($tokens as $name => $original) {
      switch ($name) {
        case 'node_document_title_breadcrumb':
          $node = $data['node'];

          if (!($node instanceof NodeInterface)) {
            return $replacements;
          }

          $node_url_info = $node->toUrl();
          $routeName = $node_url_info->getRouteName();
          $route = \Drupal::service('router.route_provider')->getRouteByName($routeName);
          $routeParameters = $node_url_info->getRouteParameters();
          $routeMatch = new RouteMatch($routeName, $route, $routeParameters, $routeParameters);
          $breadcrumb = \Drupal::service('easy_breadcrumb.breadcrumb')->build($routeMatch);
          $build = $breadcrumb->toRenderable();
          $breadcrumb->addCacheableDependency($bubbleable_metadata);
          $links =& $build['#links'];

          // Remove last link pointing to node itself.
          array_pop($links);

          $document_title_token = '';
          foreach ($links as $key => $link) {
            // array_shift() is O(n) so not removing the first item from
            // the array like was done for the last link.
            if ($key === array_key_first($links)) {
              $document_title_token = t('City of Tampere', [], ['context' => 'Replacement for first breadcrumb link text in custom document title']);
            }
            else {
              $document_title_token = "{$document_title_token} - {$link->getText()}";
            }
          }

          $replacements[$original] = $document_title_token;
          break;

        default:
          break;

      }
    }
  }

  return $replacements;
}
