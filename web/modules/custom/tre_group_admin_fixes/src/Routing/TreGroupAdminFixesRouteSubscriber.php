<?php

namespace Drupal\tre_group_admin_fixes\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * TRE Group Admin Fixes event subscriber.
 */
class TreGroupAdminFixesRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // Selecting the routes 1 by 1 as depicted in
    // https://www.drupal.org/docs/drupal-apis/routing-system/altering-existing-routes-and-adding-new-routes-based-on-dynamic-ones#s-altering-existing-routes
    // The routes are 'well-known' routes from the Group and Group Node modules.
    // However, since the first two are actually view pages, it is possible that
    // the configuration goes missing at some point and these routes will need
    // to be redefined here.
    if ($route = $collection->get('view.group_members.page_1')) {
      $route->setOption('_admin_route', TRUE);
    }

    if ($route = $collection->get('view.group_nodes.page_1')) {
      $route->setOption('_admin_route', TRUE);
    }

    if ($route = $collection->get('entity.group.version_history')) {
      $route->setOption('_admin_route', TRUE);
    }
  }

}
