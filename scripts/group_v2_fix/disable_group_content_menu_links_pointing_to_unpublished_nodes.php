<?php

/**
 * @file
 * Script file for one-time group menu link disabling.
 */

use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\group\Entity\GroupInterface;
use Drupal\group_content_menu\GroupContentMenuInterface;
use Drupal\node\NodeInterface;

/**
 * Cycles through the group menu links searching for unpublished targets.
 *
 * When the script finds a link that points to a node that is unpublished, it
 * reports the finding and optionally disables the link.
 *
 * @param bool $update_links
 *   Whether to actually update links (TRUE) or to just do a dry-run (FALSE).
 *
 * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
 * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
 */
function care_3654_menu_link_manipulator(bool $update_links) {
  $menu_tree_service = \Drupal::service('menu.link_tree');
  $menu_link_manager = \Drupal::service('plugin.manager.menu.link');
  $groups = \Drupal::entityTypeManager()
    ->getStorage('group')
    ->loadByProperties([]);
  $node_storage = \Drupal::entityTypeManager()->getStorage('node');
  $correct_groups = array_filter($groups, function ($group) {
    return $group->bundle() === 'subsite' || $group->bundle() === 'minisite';
  });
  foreach ($correct_groups as $group) {
    if (!($group instanceof GroupInterface)) {
      continue;
    }
    $group_menu_relations = group_content_menu_get_menus_per_group($group);
    foreach ($group_menu_relations as $menu_relation) {
      $menu = $menu_relation->getEntity();
      $menu_name = GroupContentMenuInterface::MENU_PREFIX . $menu->id();
      $parameters = new MenuTreeParameters();
      $menu_tree = $menu_tree_service->load($menu_name, $parameters);
      foreach ($menu_tree as $tree_link) {
        $url = $tree_link->link->getUrlObject();
        if ($url->isRouted() && $url->getRouteName() === 'entity.node.canonical') {
          $params = $url->getRouteParameters();
          $nid = $params['node'];
          $node = $node_storage->load($nid);
          if (!($node instanceof NodeInterface)) {
            continue;
          }
          if (!$node->isPublished() && $tree_link->link->isEnabled()) {
            echo "node ", $node->id(), " is not published", PHP_EOL;
            if ($tree_link->hasChildren) {
              echo "its menu link has children, not touching it: '", $tree_link->link->getTitle(), "' in ", $group->label(), " (", $group->toUrl()
                ->setAbsolute()
                ->toString(), ")", PHP_EOL;
            }
            else {
              echo "disabling node link '", $tree_link->link->getTitle(), "' in ", $group->label(), " (", $group->toUrl()
                ->setAbsolute()
                ->toString(), ")", PHP_EOL;
              if ($update_links) {
                $plugin_id = $tree_link->link->getPluginId();
                $update = ['enabled' => FALSE];
                $menu_link_manager->updateDefinition($plugin_id, $update);
              }
            }
          }
        }
      }
    }
  }
}

// Note that this is a dry-run. If you really want to manipulate the links,
// switch the parameter value to TRUE.
care_3654_menu_link_manipulator(FALSE);
