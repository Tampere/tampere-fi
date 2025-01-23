<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupRelationship;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\group_content_menu\Entity\GroupContentMenu;
use Drupal\node\NodeInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;

/**
 * Group content menu block preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.block__group_content_menu",
 *   hook = "block__group_content_menu"
 * )
 */
class GroupContentMenuBlock extends TrePreProcessPluginBase {

  /**
   * The names of the Group Content Menus for minisites keyed by language.
   */
  const MINISITE_GROUP_CONTENT_MENU_TYPES_BY_LANGUAGE = [
    'fi' => 'minisite_main_menu_fi',
    'en' => 'minisite_main_menu_en',
  ];

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $group = $this->helperFunctions->getCurrentGroup();

    if (!($group instanceof GroupInterface)) {
      return $variables;
    }

    $group_front_page_details = $this->helperFunctions->getGroupFrontPageDetails(NULL, $group);
    $current_node = $this->routeMatch->getParameter('node');

    if (isset($variables['content']['#attributes']['region'])) {
      $group_menu_block_region = $variables['content']['#attributes']['region'];
    }

    // Minisites use the parent menu link title as the sidebar heading instead
    // of the group's front page title.
    if ($group->bundle() === 'minisite' && isset($group_menu_block_region) && $group_menu_block_region === 'sidebar') {
      [$heading_title, $heading_url] = $this->getMinisiteMenuBlockHeadingLinkInformation($current_node);

      $variables['minisite_sidebar_heading_title'] = $heading_title;
      $variables['minisite_sidebar_heading_url'] = $heading_url;

      // Return early as minisite menu blocks don't currently
      // use any of the following variables.
      return $variables;
    }

    if ($group->bundle() === 'minisite' && $current_node instanceof NodeInterface) {
      $current_group_content_menu = $this->getCurrentGroupContentMenu($current_node);

      if (!empty($current_group_content_menu)) {
        // Invalidate cache when there is a change in the group content menu.
        $variables['#cache']['tags'][] = "config:system.menu.group_menu_link_content-{$current_group_content_menu->id()}";
      }
    }

    if (isset($variables['content']['#items'])) {
      $variables['group_sidebar__has_content'] = TRUE;
    }
    else {
      $variables['group_sidebar__has_content'] = FALSE;
    }

    if (isset($group_menu_block_region)) {
      $variables['group_menu_block_region'] = $group_menu_block_region;
    }

    if ($current_node instanceof NodeInterface) {
      $node_id = $current_node->id();

      $variables['#cache']['tags'][] = "node:{$node_id}";
    }

    if (!isset($group_front_page_details)) {
      return $variables;
    }

    [$front_page_url, $front_page_node_id, $front_page_title] = $group_front_page_details;

    if (isset($node_id) && $front_page_node_id === $node_id) {
      $variables['group_front_page_link_extra_classes'] = ['is-active'];
    }
    else {
      $variables['group_front_page_link_extra_classes'] = [];
    }

    $variables['group_front_page_url'] = $front_page_url;
    $variables['group_front_page_title'] = $front_page_title;
    $variables['#cache']['tags'][] = "node:{$front_page_node_id}";

    return $variables;
  }

  /**
   * Returns the title and URL for the menu heading link in an array.
   *
   * Uses the first menu item with children from the reversed current trail as
   * the title and URL source. This should be the parent menu item.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The current node to get the menu heading link details for.
   *
   * @return array|null
   *   An array containing the link title and URL as a string if successful.
   *   Otherwise null.
   */
  private function getMinisiteMenuBlockHeadingLinkInformation(NodeInterface $node): ?array {
    $current_group_content_menu = $this->getCurrentGroupContentMenu($node);

    if (empty($current_group_content_menu)) {
      return NULL;
    }

    $menu_name = GroupContentMenu::MENU_PREFIX . $current_group_content_menu->id();

    $active_trail_ids = $this->menuActiveTrail->getActiveTrailIds($menu_name);

    // Iterate over the trail IDs in reverse order since we're interested
    // in the parents.
    foreach (array_reverse($active_trail_ids) as $trail_id) {
      if (!$trail_id) {
        continue;
      }

      $menu_tree_parameters = new MenuTreeParameters();
      $menu_tree_parameters->setMaxDepth(1);
      $menu_tree_parameters->setRoot($trail_id);
      $menu_tree = $this->menuLinkTree->load($menu_name, $menu_tree_parameters);

      // Find the current menu link in the menu tree.
      $current_link = NULL;
      foreach ($menu_tree as $menu_link_tree_element) {
        $menu_link_content = $menu_link_tree_element->link;
        if ($menu_link_content->getMenuName() === $menu_name && $menu_link_content->getPluginId() === $trail_id) {
          $current_link = $menu_link_tree_element;
          break;
        }
      }

      // Check if the current menu link has children.
      if (!$current_link || !$current_link->hasChildren) {
        continue;
      }

      $parent_title = $current_link->link->getTitle();
      $parent_url = $current_link->link->getUrlObject()->toString();

      return [$parent_title, $parent_url];
    }

    return NULL;
  }

  /**
   * Returns the current group content menu.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The current node to get the current group content menu for.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   Return the current group content menu of exists.
   *   Otherwise null.
   */
  private function getCurrentGroupContentMenu(NodeInterface $node) {
    $group_contents_for_node = GroupRelationship::loadByEntity($node);
    $node_belongs_to_group = count($group_contents_for_node) > 0;

    if (!$node_belongs_to_group) {
      return NULL;
    }

    /** @var \Drupal\group\Entity\GroupRelationshipInterface $group_content */
    $group_content = reset($group_contents_for_node);

    if (!($group_content instanceof GroupRelationshipInterface)) {
      return NULL;
    }

    $group = $group_content->getGroup();

    if (!($group instanceof GroupInterface)) {
      return NULL;
    }

    $all_group_content_menus_for_group = array_map(static function (GroupRelationshipInterface $group_content) {
      return $group_content->getEntity();
    }, group_content_menu_get_menus_per_group($group));

    $current_language_code = $this->languageManager->getCurrentLanguage()->getId();
    $menu_type_for_current_language = self::MINISITE_GROUP_CONTENT_MENU_TYPES_BY_LANGUAGE[$current_language_code];

    $current_group_content_menu = NULL;
    foreach ($all_group_content_menus_for_group as $group_content_menu) {
      if ($group_content_menu->bundle() === $menu_type_for_current_language) {
        $current_group_content_menu = $group_content_menu;
        break;
      }
    }

    if (empty($current_group_content_menu)) {
      $current_group_content_menu = NULL;
    }

    return $current_group_content_menu;
  }

}
