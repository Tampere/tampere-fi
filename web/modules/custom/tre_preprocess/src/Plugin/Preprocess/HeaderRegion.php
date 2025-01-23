<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\group_content_menu\Entity\GroupContentMenu;
use Drupal\node\NodeInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;

/**
 * Header region preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.region__header",
 *   hook = "region__header"
 * )
 */
class HeaderRegion extends TrePreProcessPluginBase {
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
    $current_path = $this->currentPath->getPath();
    $group_id_from_path = $this->helperFunctions->getGroupIdFromSearchViewPath($current_path);

    $is_minisite_group = $this->helperFunctions->isMinisite($group_id_from_path);

    if (!$is_minisite_group) {
      return $variables;
    }

    if ($group_id_from_path) {
      $current_group = $this->entityTypeManager->getStorage('group')->load($group_id_from_path);
    }
    else {
      $current_group = $this->helperFunctions->getCurrentGroup();
    }

    $group_front_page_details = $this->helperFunctions->getGroupFrontPageDetails($group_id_from_path);
    $variables['is_minisite_group'] = $is_minisite_group;

    if (isset($group_front_page_details)) {
      [$front_page_url, $front_page_node_id] = $group_front_page_details;

      $variables['group_front_page_url'] = $front_page_url;
      $variables['#cache']['tags'][] = "node:{$front_page_node_id}";
    }

    $node = $this->routeMatch->getParameter('node');

    if ($node instanceof NodeInterface) {
      $variables['#cache']['tags'][] = "node:{$node->id()}";
    }

    if (!($current_group instanceof GroupInterface)) {
      return $variables;
    }

    if ($this->isAnyActiveItemInMiniSiteMenu($current_group)) {
      $variables['has_minisite_menu_with_items'] = TRUE;
    }

    /** @var \Drupal\group\Entity\GroupInterface $translated_group */
    $translated_group = $this->entityRepository->getTranslationFromContext($current_group);
    if ($translated_group->hasField('field_minisite_heading') && !$translated_group->get('field_minisite_heading')->isEmpty()) {
      $variables['group_main_heading'] = $translated_group->get('field_minisite_heading')->getString();
    }

    if ($translated_group->hasField('field_svg_logo') && !$translated_group->get('field_svg_logo')->isEmpty()) {
      $variables['group_logo'] = $translated_group->get('field_svg_logo')->view('default');
    }

    $variables['group_name'] = $translated_group->label();

    return $variables;
  }

  /**
   * Check if there is any active item in the group content menu.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group to check group content menu for.
   *
   * @return bool
   *   Return True if there is any active item, otherwise, return False.
   */
  private function isAnyActiveItemInMiniSiteMenu(GroupInterface $group): bool {
    $current_language_code = $this->languageManager->getCurrentLanguage()->getId();
    $cid = 'has_active_menu_item_' . $current_language_code . ':' . $group->id();
    $menu_cache = $this->cache->get($cid);

    if ($menu_cache) {
      return $menu_cache->data;
    }

    $node = $this->routeMatch->getParameter('node');
    if (!$node instanceof NodeInterface) {
      return FALSE;
    }

    $all_group_content_menus_for_group = array_map(static function (GroupRelationshipInterface $group_content) {
      return $group_content->getEntity();
    }, group_content_menu_get_menus_per_group($group));

    $menu_type_for_current_language = self::MINISITE_GROUP_CONTENT_MENU_TYPES_BY_LANGUAGE[$current_language_code];

    $current_group_content_menu = NULL;
    foreach ($all_group_content_menus_for_group as $group_content_menu) {
      if ($group_content_menu->bundle() === $menu_type_for_current_language) {
        $current_group_content_menu = $group_content_menu;
        break;
      }
    }

    if (empty($current_group_content_menu)) {
      return FALSE;
    }

    $menu_name = GroupContentMenu::MENU_PREFIX . $current_group_content_menu->id();
    $menu_tree_parameters = new MenuTreeParameters();
    $menu_tree_parameters->onlyEnabledLinks();
    $menu_tree = $this->menuLinkTree->load($menu_name, $menu_tree_parameters);

    $result = !empty($menu_tree);

    $this->cache->set($cid, $result, CacheBackendInterface::CACHE_PERMANENT, [
      "config:system.menu.group_menu_link_content-{$current_group_content_menu->id()}",
    ]);

    return $result;
  }

}
