<?php

namespace Drupal\tre_portfolio_listing\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManager;
use Drupal\group\Entity\GroupContent;
use Drupal\group\Entity\GroupContentInterface;
use Drupal\node\NodeInterface;
use Drupal\tre_portfolio_listing\Event\PortfolioListingEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Handles 'portfolio_listing' node creation on 'portfolio' content insert.
 */
class PortfolioGroupContentInsertSubscriber implements EventSubscriberInterface {

  /**
   * The plugin ID for 'portfolio_listing' content.
   */
  const LISTING_PLUGIN_ID = 'group_node:portfolio_listing';

  /**
   * The 'portfolio_listing' node titles keyed by language.
   */
  const LISTING_NODE_TITLES_BY_LANGUAGE = [
    'fi' => 'Portfoliot',
    'en' => 'Portfolios',
  ];

  /**
   * The Entity Type Manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * The Language Manager service.
   *
   * @var \Drupal\Core\Language\LanguageManager
   */
  protected $languageManager;

  /**
   * Constructs the class instance.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    LanguageManager $language_manager
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->languageManager = $language_manager;
  }

  /**
   * Returns listing node title for given language.
   *
   * @param string $language_code
   *   The language code to get the node title for.
   *
   * @return string
   *   Listing node title in the given language if one exits.
   *   Otherwise default title.
   */
  private function getListingNodeTitle($language_code) {
    if (!isset(self::LISTING_NODE_TITLES_BY_LANGUAGE[$language_code])) {
      $titles_by_language = self::LISTING_NODE_TITLES_BY_LANGUAGE;
      return reset($titles_by_language);
    }

    return self::LISTING_NODE_TITLES_BY_LANGUAGE[$language_code];
  }

  /**
   * Adds 'portfolio_listing' node to groups on 'portfolio' content insert.
   *
   * Creates a 'portfolio_listing' node for a group when 'portfolio' group
   * content gets added if one does not already exist for the group. If a
   * listing node already exists, a translation will be added if one does not
   * exist for the inserted entity language.
   *
   * @param \Drupal\tre_portfolio_listing\Event\PortfolioListingEvent $event
   *   The event being acted on.
   */
  public function onPortfolioGroupContentInsert(PortfolioListingEvent $event) {
    $group_content = $event->getEntity();

    if (!($group_content instanceof GroupContentInterface)) {
      return;
    }

    $group_content_bundle = $group_content->bundle();
    $position = strrpos($group_content_bundle, 'group_node-portfolio');

    // Not 'portfolio' group content.
    if ($position === FALSE) {
      return;
    }

    /** @var \Drupal\group\Entity\GroupInterface $group */
    $group = $group_content->getGroup();

    $group_content_storage = $this->entityTypeManager->getStorage('group_content');

    // @phpstan-ignore-next-line
    $group_portfolio_listing_contents = $group_content_storage->loadByGroup($group, self::LISTING_PLUGIN_ID);

    /** @var \Drupal\node\NodeInterface $portfolio_node_being_inserted */
    $portfolio_node_being_inserted = $group_content->getEntity();
    $inserted_node_language_code = $portfolio_node_being_inserted->language()->getId();

    // If 'portfolio_listing' content already exists, check if it has an
    // existing translation for the inserted entity language.
    if (!empty($group_portfolio_listing_contents)) {
      $group_portfolio_listing_content = reset($group_portfolio_listing_contents);

      if (!($group_portfolio_listing_content instanceof GroupContentInterface)) {
        return;
      }

      $existing_listing_node = $group_portfolio_listing_content->getEntity();

      if (!($existing_listing_node instanceof NodeInterface) || $existing_listing_node->hasTranslation($inserted_node_language_code)) {
        return;
      }

      // Translate the existing listing node if no translation was found.
      $existing_listing_node->addTranslation(
        $inserted_node_language_code,
        ['title' => $this->getListingNodeTitle($inserted_node_language_code)]
      )->save();
    }
    else {
      // Add a new 'portfolio_listing' node in the group if no such content
      // exists.
      $listing_node = $this->entityTypeManager->getStorage('node')->create([
        'title' => $this->getListingNodeTitle($inserted_node_language_code),
        'type' => 'portfolio_listing',
        'langcode' => $inserted_node_language_code,
      ]);

      $listing_node->save();

      $group->addContent($listing_node, self::LISTING_PLUGIN_ID);
    }
  }

  /**
   * Sets custom title for 'portfolio_listing' content on presave.
   *
   * Content of type 'portfolio_listing' isn't meant to be created manually
   * but in case it is necessary to do that this will ensure the title
   * follows the same pattern as the automatically created ones.
   *
   * @param \Drupal\tre_portfolio_listing\Event\PortfolioListingEvent $event
   *   The event being acted on.
   */
  public function onPortfolioListingNodePresave(PortfolioListingEvent $event) {
    $node = $event->getEntity();

    if (!($node instanceof NodeInterface) || $node->bundle() !== 'portfolio_listing') {
      return;
    }

    $node_language_code = $node->language()->getId();

    $title = $this->getListingNodeTitle($node_language_code);
    $node->setTitle($title);
  }

  /**
   * Adds 'portfolio_listing' node translation on 'portfolio' node update.
   *
   * If the group for the updated 'portfolio' has existing 'portfolio_listing'
   * content, a translation will be added in the language of the updated node.
   *
   * @param \Drupal\tre_portfolio_listing\Event\PortfolioListingEvent $event
   *   The event being acted on.
   */
  public function onPortfolioNodeUpdate(PortfolioListingEvent $event) {
    $node = $event->getEntity();

    if (!($node instanceof NodeInterface) || $node->bundle() !== 'portfolio') {
      return;
    }

    $group_contents_for_entity = GroupContent::loadByEntity($node);
    if (count($group_contents_for_entity) < 1) {
      return;
    }

    /** @var \Drupal\group\Entity\GroupContentInterface $group_content */
    $group_content = reset($group_contents_for_entity);

    $group = $group_content->getGroup();

    $group_content_storage = $this->entityTypeManager->getStorage('group_content');

    // @phpstan-ignore-next-line
    $group_portfolio_listing_contents = $group_content_storage->loadByGroup($group, self::LISTING_PLUGIN_ID);

    // Do nothing if group has no 'portfolio_listing' content.
    if (empty($group_portfolio_listing_contents)) {
      return;
    }

    $group_portfolio_listing_content = reset($group_portfolio_listing_contents);

    if (!($group_portfolio_listing_content instanceof GroupContentInterface)) {
      return;
    }

    $existing_listing_node = $group_portfolio_listing_content->getEntity();

    if (!($existing_listing_node instanceof NodeInterface)) {
      return;
    }

    $languages = $this->languageManager->getLanguages();
    foreach ($languages as $language_code => $language) {
      // Add translation if the listing node doesn't yet have a translation
      // in the language AND the updated node has a translation in the
      // language.
      if ($existing_listing_node->hasTranslation($language_code) || !$node->hasTranslation($language_code)) {
        continue;
      }

      $existing_listing_node->addTranslation(
        $language_code,
        ['title' => $this->getListingNodeTitle($language_code)]
      )->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[PortfolioListingEvent::PORTFOLIO_LISTING_ENTITY_PRESAVE][] = ['onPortfolioListingNodePresave'];
    $events[PortfolioListingEvent::PORTFOLIO_GROUP_CONTENT_INSERT][] = ['onPortfolioGroupContentInsert'];
    $events[PortfolioListingEvent::PORTFOLIO_NODE_UPDATE][] = ['onPortfolioNodeUpdate'];
    return $events;
  }

}
