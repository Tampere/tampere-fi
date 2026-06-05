<?php

namespace Drupal\tre_generate_menu_from_groups\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Component\Transliteration\TransliterationInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\node\NodeInterface;
use Drupal\system\Entity\Menu;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for generating a menu structure from Group hierarchy.
 *
 * Supported hierarchies:
 * [Top Level] > Theme > Subsite
 * [Top Level] > Subsites
 * Top levels are either Topic or Life Style - Groups types - each Top level (and its translation) has its own menu hierarcy in Drupal
 */
class TreGenerateMenuFromGroupsCommands extends DrushCommands {

  /**
   * The transliteration service.
   */
  protected TransliterationInterface $transliteration;

  /**
   * Menu machine names that must never be overwritten by this generator.
   */
  protected const BANNED_MENU_IDS = [
    'main-menu-fi',
    'main-menu-en',
    'life-situation-menu-en',
    'life-situation-menu-fi',
  ];

  /**
   * Menu labels that must never be used by this generator.
   */
  protected const BANNED_MENU_LABELS = [
    'Päävalikko',
    'Main navigation',
    'Life situation menu',
    'Elämäntilanne-valikko',
  ];

  /**
   * Defines the reference fields used to link child groups to their parents.
   * Maps the top-level bundle to the correct reference fields for its children.
   */
  protected const PARENT_REFERENCE_FIELDS = [
    'topic' => [
      'theme' => 'field_theme_topic',
      'subsite' => 'field_subsite_topic',
    ],
    'life_situation' => [
      'theme' => 'field_theme_life_situation',
      'subsite' => 'field_subsite_life_situation',
    ],
  ];

  /**
   * Subsite -> theme reference field machine name.
   * (This is identical across both hierarchy trees)
   */
  protected const SUBSITE_THEME_FIELD = 'field_subsite_theme';

  /**
   * Group front page field machine name.
   */
  protected const GROUP_FRONT_PAGE_FIELD = 'field_front_page';

  /**
   * Node bundles that must not be added to the generated menu.
   */
  protected const SKIPPED_NODE_BUNDLES = [
    'small_news_item',
    'news_item',
    'rich_article',
    'blog_article',
    'notice',
    'organization',
    'project',
    'zoning_information',
    'comprehensive_plan',
    'city_planning_and_constructions',
    'involvement_opportunity',
    'place'
  ];

  /**
   * Group bundles that must not be added to the generated menu.
   */
  protected const SKIPPED_GROUP_BUNDLES = [
    'minisite',
  ];

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The language manager.
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * Counts created menu links by node bundle machine name.
   *
   * @var array<string, int>
   */
  protected array $nodeTypeCounts = [];

  /**
   * Machine name of the generated menu (generated dynamically).
   */
  protected string $menuId;

  /**
   * Human-readable name of the generated menu (passed via argument).
   */
  protected string $menuLabel;

  /**
   * Group language to include in the generated menu (passed via argument).
   */
  protected string $langcode;

  /**
   * The top-level group bundle ('topic' or 'life_situation').
   */
  protected string $topLevelBundle;

  /**
   * Constructs the command service.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    LanguageManagerInterface $languageManager,
    TransliterationInterface $transliteration,
  ) {
    parent::__construct();
    $this->entityTypeManager = $entityTypeManager;
    $this->languageManager = $languageManager;
    $this->transliteration = $transliteration;
  }

  /**
   * Generates the menu from Group hierarchy data based on top-level group type.
   *
   * @command tre_generate_menu_from_groups:generate-menu
   * @aliases tgmfg-generate-menu
   * @param string $top_level_bundle
   * The top-level group bundle (topic, life_situation).
   * @param string $langcode
   * The language code for the groups to fetch (fi, en).
   * @param string $menu_label
   * The human-readable name of the menu to be generated (e.g., "Main Menu Generated").
   *
   * @usage drush tre_generate_menu_from_groups:generate-menu topic fi "Päävalikko Generated"
   * Generates a Finnish menu named "Päävalikko Generated" using the 'topic' hierarchy.
   * @usage drush tre_generate_menu_from_groups:generate-menu topic en "Main Menu Generated"
   * Generates an English menu named "Main Menu Generated" using the 'topic' hierarchy.
   * @usage drush tre_generate_menu_from_groups:generate-menu life_situation fi "Elämäntilanteet Generated"
   * Generates a Finnish menu named "Elämäntilanteet Generated" using the 'life_situation' hierarchy.
   * @usage drush tre_generate_menu_from_groups:generate-menu life_situation en "Life Situations Generated"
   * Generates an English menu named "Life Situations Generated" using the 'life_situation' hierarchy.
   */
  public function generateMenu(string $top_level_bundle, string $langcode, string $menu_label): void {
    if (!isset(self::PARENT_REFERENCE_FIELDS[$top_level_bundle])) {
      $this->logger()->error(
        'Unsupported top level bundle: "{top_level_bundle}". Allowed values are: {allowed_references}.', [
          'top_level_bundle' => $top_level_bundle,
          'allowed_references' => implode(', ', array_keys(self::PARENT_REFERENCE_FIELDS))
        ]);
      return;
    }

    $this->topLevelBundle = $top_level_bundle;
    $this->langcode = $langcode;
    $this->menuLabel = $menu_label;
    
    // Menu ID must be provided in the Menu::create() -> Generate machine name (menuId) from the provided menu label.
    $transliterated = $this->transliteration->transliterate($this->menuLabel, 'en', '-');
    $this->menuId = strtolower(preg_replace('/[^a-zA-Z0-9_-]+/', '-', $transliterated));
    $this->menuId = trim($this->menuId, '-');

    // Terminate the command if trying to update existing production menus
    if ($this->isBannedMenuName($this->menuLabel, $this->menuId)) {
      $this->logger()->error('Menu generation aborted. The requested menu "{menu_label}" with machine name "{menu_id}" is on the banned menu names list and must not be overwritten. Banned machine names: {banned_menu_ids}. Banned menu labels: {banned_menu_labels}.', [
        'menu_label' => $this->menuLabel,
        'menu_id' => $this->menuId,
        'banned_menu_ids' => implode(', ', self::BANNED_MENU_IDS),
        'banned_menu_labels' => implode(', ', self::BANNED_MENU_LABELS),
      ]);
      return;
    }

    $this->logger()->notice('Using top-level group: {top_level_bundle}', [
      'top_level_bundle' => $this->topLevelBundle,
    ]);
    $this->logger()->notice('Using language: {langcode}', ['langcode' => $this->langcode]);
    $this->logger()->notice('Generating menu: {menu_label} (Machine name: {machine_name})', [
      'menu_label' => $this->menuLabel,
      'machine_name' => $this->menuId
    ]);
    // This is for debugging purposes - we want to log what type of (content type) pages and how many we have added to the menu structure
    $this->nodeTypeCounts = [];
    // Load Topics or Life Situations (based on the given argument)
    $topLevelGroups = $this->loadTopLevelGroups();

    if (empty($topLevelGroups)) {
      $this->logger()->warning('No "{top_level_bundle}" groups found for language "{langcode}". Nothing was generated.', [
        'top_level_bundle' => $this->topLevelBundle,
        'langcode' => $this->langcode
      ]);
      return;
    }

    // Check if the given menu exists - if not -> Create the menu -> If yes -> Delete the existing links "start from fresh"
    $this->ensureMenuExists();
    $this->deleteExistingMenuLinks();

    $createdCount = 0;
    $weight = 0;

    foreach ($topLevelGroups as $group) {
      $createdCount += $this->handleTopLevelGroup($group, $weight++);
    }
    $this->logger()->notice('Menu generation complete. Created {created_count} menu links in "{menu_label}".', [
      'created_count' => $createdCount,
      'menu_label' => $this->menuLabel
    ]);
    $this->logNodeTypeSummary();
  }

  /**
   * Handles one top-level group and its child structure.
   */
  protected function handleTopLevelGroup(GroupInterface $group, int $weight): int {
    if ($this->shouldSkipGroup($group)) {
      return 0;
    }

    $frontPage = $this->getGroupFrontPageNode($group);

    if (!$this->isAllowedMenuNode($frontPage)) {
      return 0;
    }

    $createdCount = 0;

    $pluginId = $this->createMenuLinkFromNode(
      $frontPage,
      '',
      $weight,
      TRUE
    );
    $createdCount++;

    $childWeight = 0;

    // Load themes dynamically based on the top-level bundle map
    $themes = $this->loadChildGroupsForParent('theme', $group);
    foreach ($themes as $theme) {
      $createdCount += $this->handleTheme($theme, $pluginId, $childWeight++);
    }

    // Load direct subsites dynamically based on the top-level bundle map
    $directSubsites = $this->loadChildGroupsForParent('subsite', $group);
    foreach ($directSubsites as $subsite) {
      $createdCount += $this->handleSubsite($subsite, $pluginId, $childWeight++);
    }

    return $createdCount;
  }

  /**
   * Handles one theme and its subsites.
   */
  protected function handleTheme(GroupInterface $theme, string $parentPluginId, int $weight): int {
    if ($this->shouldSkipGroup($theme)) {
      return 0;
    }

    $themeFrontPage = $this->getGroupFrontPageNode($theme);

    if (!$this->isAllowedMenuNode($themeFrontPage)) {
      return 0;
    }

    $createdCount = 0;

    $themePluginId = $this->createMenuLinkFromNode(
      $themeFrontPage,
      $parentPluginId,
      $weight,
      TRUE
    );
    $createdCount++;

    $childWeight = 0;

    // Load subsites for this theme using the common field
    $subsites = $this->loadSubsiteGroupsForTheme($theme);
    foreach ($subsites as $subsite) {
      $createdCount += $this->handleSubsite($subsite, $themePluginId, $childWeight++);
    }

    return $createdCount;
  }

  /**
   * Handles one subsite.
   */
  protected function handleSubsite(GroupInterface $subsite, string $parentPluginId, int $weight): int {
    if ($this->shouldSkipGroup($subsite)) {
      return 0;
    }

    $subsiteFrontPage = $this->getGroupFrontPageNode($subsite);

    if (!$this->isAllowedMenuNode($subsiteFrontPage)) {
      return 0;
    }

    $this->createMenuLinkFromNode(
      $subsiteFrontPage,
      $parentPluginId,
      $weight,
      FALSE
    );

    return 1;
  }

  /**
   * Loads all top-level groups that have the selected language translation.
   */
  protected function loadTopLevelGroups(): array {
    return $this->loadGroupsByType($this->topLevelBundle);
  }

  /**
   * Dynamically loads child groups (themes or subsites) for the top-level parent.
   */
  protected function loadChildGroupsForParent(string $childBundle, GroupInterface $parent): array {
    $referenceField = self::PARENT_REFERENCE_FIELDS[$this->topLevelBundle][$childBundle] ?? NULL;
    
    if (!$referenceField) {
      $this->logger()->error('Reference field missing for parent "{top_level_bundle}" to child "{child_bundle}".', [
        'top_level_bundle' => $this->topLevelBundle,
        'child_bundle' => $childBundle
      ]);
      return [];
    }

    return $this->loadGroupsByTypeAndReference($childBundle, $referenceField, (int) $parent->id());
  }

  /**
   * Loads all subsite groups for a theme (uses the same field regardless of top-level parent).
   */
  protected function loadSubsiteGroupsForTheme(GroupInterface $theme): array {
    return $this->loadGroupsByTypeAndReference(
      'subsite',
      self::SUBSITE_THEME_FIELD,
      (int) $theme->id()
    );
  }

  /**
   * Loads all groups by bundle and checks for required translation.
   */
  protected function loadGroupsByType(string $bundle): array {
    $groupStorage = $this->entityTypeManager->getStorage('group');

    $groupIds = $groupStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', $bundle)
      ->sort('id', 'ASC')
      ->execute();

    if (empty($groupIds)) {
      return [];
    }

    $groups = $groupStorage->loadMultiple($groupIds);
    $translatedGroups = [];

    foreach ($groups as $group) {
      if ($group instanceof GroupInterface && $group->hasTranslation($this->langcode)) {
        $translatedGroups[] = $group->getTranslation($this->langcode);
      }
    }

    return $translatedGroups;
  }

  /**
   * Loads all groups by bundle and entity reference field, checking for required translation.
   */
  protected function loadGroupsByTypeAndReference(string $bundle, string $referenceField, int $targetId): array {
    $groupStorage = $this->entityTypeManager->getStorage('group');

    $groupIds = $groupStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', $bundle)
      ->condition($referenceField, $targetId)
      ->sort('id', 'ASC')
      ->execute();

    if (empty($groupIds)) {
      return [];
    }

    $groups = $groupStorage->loadMultiple($groupIds);
    $translatedGroups = [];

    foreach ($groups as $group) {
      if ($group instanceof GroupInterface && $group->hasTranslation($this->langcode)) {
        $translatedGroups[] = $group->getTranslation($this->langcode);
      }
    }

    return $translatedGroups;
  }

  /**
   * Creates one menu link from a node using the node's active translation language.
   */
  protected function createMenuLinkFromNode(
    NodeInterface $node,
    string $parentPluginId,
    int $weight,
    bool $expanded
  ): string {
    $language = $this->languageManager->getLanguage($this->langcode);

    $menuLink = MenuLinkContent::create([
      'title' => $node->label(),
      'link' => [
        'uri' => 'entity:node/' . $node->id(),
        'options' => [
          'language' => $language,
        ],
      ],
      'menu_name' => $this->menuId,
      'parent' => $parentPluginId,
      'weight' => $weight,
      'expanded' => $expanded,
      'enabled' => TRUE,
      'langcode' => $this->langcode,
    ]);

    $menuLink->save();
    $this->incrementNodeTypeCount($node);

    return 'menu_link_content:' . $menuLink->uuid();
  }

  /**
   * Ensures the requested menu exists.
   */
  protected function ensureMenuExists(): void {
    $menu = Menu::load($this->menuId);

    if ($menu instanceof Menu) {
      $this->logger()->notice('Menu "{menu_label}" already exists.', ['menu_label' => $this->menuLabel]);
      return;
    }

    $menu = Menu::create([
      'id' => $this->menuId,
      'label' => $this->menuLabel,
      'description' => 'Generated menu created from Group hierarchy data.',
    ]);
    $menu->save();
    $this->logger()->notice('Created menu "{menu_label}" with machine name "{machine_name}".', [
      'menu_label' => $this->menuLabel,
      'machine_name' => $this->menuId
    ]);
  }

  /**
   * Deletes all existing links from the generated menu.
   */
  protected function deleteExistingMenuLinks(): void {
    $storage = $this->entityTypeManager->getStorage('menu_link_content');

    $linkIds = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('menu_name', $this->menuId)
      ->execute();

    if (empty($linkIds)) {
      $this->logger()->notice('No existing generated menu links found.');
      return;
    }

    $links = $storage->loadMultiple($linkIds);
    $storage->delete($links);

    $this->logger()->notice(
      'Deleted {link_count} existing menu links from "{menu_label}".', [
        'link_count' => count($links),
        'menu_label' => $this->menuLabel
      ]);
  }

  /**
   * Gets the front page node from the group front page field in correct translation.
   */
  protected function getGroupFrontPageNode(GroupInterface $group): ?NodeInterface {
    if (!$group->hasField(self::GROUP_FRONT_PAGE_FIELD)) {
      return NULL;
    }

    $entity = $group->get(self::GROUP_FRONT_PAGE_FIELD)->entity ?? NULL;

    if ($entity instanceof NodeInterface) {
      if ($entity->hasTranslation($this->langcode)) {
        return $entity->getTranslation($this->langcode);
      }
    }

    return NULL;
  }

  /**
   * Checks whether a group must be skipped.
   */
  protected function shouldSkipGroup(GroupInterface $group): bool {
    return in_array($group->bundle(), self::SKIPPED_GROUP_BUNDLES, TRUE);
  }

  /**
   * Checks whether a node is allowed to become a menu link.
   */
  protected function isAllowedMenuNode(?NodeInterface $node): bool {
    if (!$node instanceof NodeInterface || !$node->isPublished()) {
      return FALSE;
    }

    return !in_array($node->bundle(), self::SKIPPED_NODE_BUNDLES, TRUE);
  }

  /**
   * Increments the created menu link count for a node bundle.
   * This is only used for debugging purposes so that we can track what kind of content is added (and how many) to the menu
   */
  protected function incrementNodeTypeCount(NodeInterface $node): void {
    $bundle = $node->bundle();

    if (!isset($this->nodeTypeCounts[$bundle])) {
      $this->nodeTypeCounts[$bundle] = 0;
    }

    $this->nodeTypeCounts[$bundle]++;
  }

  /**
   * Logs a summary of created menu links by node bundle machine name.
   */
  protected function logNodeTypeSummary(): void {
    if (empty($this->nodeTypeCounts)) {
      $this->logger()->notice('No node type summary available.');
      return;
    }

    ksort($this->nodeTypeCounts);

    $this->logger()->notice('Menu node type summary:');

    foreach ($this->nodeTypeCounts as $bundle => $count) {
      $this->logger()->notice('{bundle}: {count}', [
        'bundle' => $bundle,
        'count' => $count
      ]);
    }
  }

  /**
   * Checks whether the requested menu is protected and must not be modified.
   */
  protected function isBannedMenuName(string $menuLabel, string $menuId): bool {
    if (in_array($menuId, self::BANNED_MENU_IDS, TRUE)) {
      return TRUE;
    }

    return in_array($menuLabel, self::BANNED_MENU_LABELS, TRUE);
  }
}