<?php

namespace Drupal\tre_preprocess_utility_functions\Utils;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\easy_breadcrumb\EasyBreadcrumbBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Language\LanguageManager;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\file\Entity\File;
use Drupal\group\Entity\GroupContent;
use Drupal\group\Entity\GroupContentInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use proj4php\Proj4php;
use proj4php\Proj;
use proj4php\Point;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Routing\RouteProvider;
use Drupal\Core\Site\Settings;

/**
 * Provides internal helper methods.
 */
class HelperFunctions implements HelperFunctionsInterface {

  use StringTranslationTrait;

  /**
   * The Entity Repository service.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  private $entityRepository;

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
   * The Current Route Match service.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $routeMatch;

  /**
   * The Route Provider service.
   *
   * @var \Drupal\Core\Routing\RouteProvider
   */
  protected $routeProvider;

  /**
   * The Easy Breadcrumb Builder service.
   *
   * @var \Drupal\easy_breadcrumb\EasyBreadcrumbBuilder
   */
  protected $easyBreadcrumb;

  /**
   * Constructs the class instance.
   */
  public function __construct(
    EntityRepositoryInterface $entity_repository,
    EntityTypeManagerInterface $entity_type_manager,
    CurrentRouteMatch $route_match,
    LanguageManager $language_manager,
    RouteProvider $routeProvider,
    EasyBreadcrumbBuilder $easyBreadcrumb
  ) {
    $this->entityRepository = $entity_repository;
    $this->entityTypeManager = $entity_type_manager;
    $this->routeMatch = $route_match;
    $this->languageManager = $language_manager;
    $this->routeProvider = $routeProvider;
    $this->easyBreadcrumb = $easyBreadcrumb;
  }

  /**
   * {@inheritdoc}
   */
  public function getBreadcrumbs($node) {
    if (!($node instanceof NodeInterface)) {
      return NULL;
    }

    $node_url = $node->toUrl();
    $route_name = $node_url->getRouteName();
    $route = $this->routeProvider->getRouteByName($route_name);
    $route_parameters = $node_url->getRouteParameters();
    $route_match = new RouteMatch($route_name, $route, $route_parameters, $route_parameters);
    $breadcrumb = $this->easyBreadcrumb->build($route_match);
    $build = $breadcrumb->toRenderable();

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getColorFieldAllowedValues($entity_name, $bundle_name, $form_mode = 'default', $field_name = 'field_color'): ?array {
    $form_display = $this->entityTypeManager
      ->getStorage('entity_form_display')
      ->load($entity_name . '.' . $bundle_name . '.' . $form_mode);

    if (!$form_display) {
      return NULL;
    }

    $field_widget = $form_display->getComponent($field_name);

    if ($field_widget['type'] != 'color_field_widget_box') {
      return NULL;
    }

    $available_colors_string = $field_widget['settings']['default_colors'];
    $available_colors = explode(',', $available_colors_string);

    return $available_colors;
  }

  /**
   * {@inheritdoc}
   */
  public function getColorfulContentLiftupModifiers($selected_color): array {
    $available_colors = $this->getColorFieldAllowedValues('paragraph', 'single_colorful_content_liftup');

    if (empty($available_colors) && !$selected_color) {
      return self::DEFAULT_COLORFUL_CONTENT_LIFTUP_MODIFIERS;
    }

    $selected_color_index = array_search($selected_color, $available_colors);
    $card_color_variation = $selected_color_index + 1;

    return [
      self::COLORFUL_CONTENT_LIFTUP_VARIATION_BASE,
      self::COLORFUL_CONTENT_LIFTUP_VARIATION_BASE . '-' . $card_color_variation,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getImageInformation(ParagraphInterface $paragraph, $media_field_name = 'field_media'): ?array {
    $referenced_media_entities = $paragraph->get($media_field_name)->referencedEntities();
    $media_entity = reset($referenced_media_entities);

    if (!($media_entity instanceof MediaInterface)) {
      return NULL;
    }

    /** @var \Drupal\media\MediaInterface $translated_media_entity */
    $translated_media_entity = $this->entityRepository->getTranslationFromContext($media_entity);

    $media_name = $translated_media_entity->label();
    $media_id = $translated_media_entity->id();

    $referenced_media_images = $translated_media_entity->get('field_media_image')->referencedEntities();
    $media_image = reset($referenced_media_images);

    if (!($media_image instanceof File)) {
      return NULL;
    }

    $file_url = $media_image->createFileUrl(FALSE);

    return [$media_name, $file_url, $media_id];
  }

  /**
   * {@inheritdoc}
   */
  public function getNodeGroup(NodeInterface $current_node): ?GroupInterface {
    $group = NULL;
    if ($current_node instanceof NodeInterface && !$current_node->isNew()) {
      $group_contents_for_node = GroupContent::loadByEntity($current_node);
      $node_belongs_to_group = count($group_contents_for_node) > 0;
      if ($node_belongs_to_group) {
        // The first group the node belongs to will be considered the current
        // group.
        /** @var \Drupal\group\Entity\GroupContentInterface $group_content */
        $group_content = reset($group_contents_for_node);
        if ($group_content instanceof GroupContentInterface) {
          $group = $group_content->getGroup();
        }
      }
    }

    return $group;
  }

  /**
   * {@inheritdoc}
   */
  public function getNodeLiftupLocation(NodeInterface $node): array {
    $location_coordinates = [];

    if (!($node instanceof NodeInterface)) {
      return $location_coordinates;
    }

    if (!$node->hasField(self::LONGITUDE_FIELD_NAME) || $node->get(self::LONGITUDE_FIELD_NAME)->isEmpty()) {
      return $location_coordinates;
    }

    if (!$node->hasField(self::LATITUDE_FIELD_NAME) || $node->get(self::LATITUDE_FIELD_NAME)->isEmpty()) {
      return $location_coordinates;
    }

    $longitude = $node->get(self::LONGITUDE_FIELD_NAME)->getString();
    $latitude = $node->get(self::LATITUDE_FIELD_NAME)->getString();

    $location_coordinates[] = [
      'latitude' => $latitude,
      'longitude' => $longitude,
    ];

    return $location_coordinates;
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentGroup(): ?GroupInterface {

    $current_group = $this->routeMatch->getParameter('group');
    if ($current_group instanceof GroupInterface) {
      return $current_group;
    }

    $group = NULL;
    $current_node = $this->routeMatch->getParameter('node');
    if ($current_node instanceof NodeInterface) {
      $group = $this->getNodeGroup($current_node);
    }

    return $group;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityReferenceFieldValues(array $entity_reference_field_values): array {
    $values = [];
    foreach ($entity_reference_field_values as $key => $value) {
      $values[$key] = $value['target_id'];
    }

    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function getExternalLinkFieldUrl(FieldItemListInterface $link_field): ?string {
    if ($link_field->isEmpty()) {
      return NULL;
    }

    /** @var \Drupal\link\Plugin\Field\FieldType\LinkItem $link_item */
    $link_item = $link_field->first();
    if ($link_item->isEmpty()) {
      return NULL;
    }

    if (!$link_item->isExternal()) {
      return NULL;
    }

    return $link_item->get('uri')->getValue();
  }

  /**
   * {@inheritdoc}
   */
  public function getExternalLinkFieldTitle(FieldItemList $link_field) {
    if ($link_field->isEmpty()) {
      return NULL;
    }

    /** @var \Drupal\link\Plugin\Field\FieldType\LinkItem $link_item */
    $link_item = $link_field->first();
    if ($link_item->isEmpty()) {
      return NULL;
    }

    if (!$link_item->isExternal()) {
      return NULL;
    }

    return $link_item->get('title')->getString();
  }

  /**
   * {@inheritdoc}
   */
  public function getExternalLinkParagraphContents(ParagraphInterface $link_paragraph): ?array {
    if (!($link_paragraph instanceof ParagraphInterface)) {
      return NULL;
    }

    $has_external_link_with_link_text = $link_paragraph->hasField('field_external_link_w_link_text');
    $has_external_link_field = $link_paragraph->hasField('field_external_link') || $has_external_link_with_link_text;
    if (!$has_external_link_field) {
      return NULL;
    }

    $link_text = '';
    if ($has_external_link_with_link_text) {
      /** @var \Drupal\Core\Field\FieldItemList $link_field */
      $link_field = $link_paragraph->get('field_external_link_w_link_text');
      $link_text = $this->getExternalLinkFieldTitle($link_field);
    }
    else {
      $link_field = $link_paragraph->get('field_external_link');
    }

    $link_url = $this->getExternalLinkFieldUrl($link_field);

    return [$link_url, $link_text];
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldValueString(EntityInterface $entity, $field_name): ?string {
    if (!$entity->hasField($field_name)) {
      return NULL;
    }

    if ($entity->get($field_name)->isEmpty()) {
      return NULL;
    }

    return $entity->get($field_name)->getString();
  }

  /**
   * {@inheritdoc}
   */
  public function getFormattedInvolvementOpportunityDate(DrupalDateTime $start_date, DrupalDateTime $end_date = NULL) {

    $translation_context = 'Involvement opportunity dates with date and time';
    $translation_options = ['context' => $translation_context];

    $start_date->setTimezone(new \DateTimeZone(self::TIMEZONE));

    if (empty($end_date)) {
      $start_date_format = $start_date->format(self::DATE_ONLY_FORMAT);
      $start_time_format = $start_date->format(self::TIME_ONLY_FORMAT);

      $translation_args = [
        '@date' => $start_date_format,
        '@time' => $start_time_format,
      ];
      return $this->t('On @date at @time', $translation_args, $translation_options);
    }

    $end_date->setTimezone(new \DateTimeZone(self::TIMEZONE));
    $full_start_date = $start_date->format(self::DATE_ONLY_FORMAT);
    $full_end_date = $end_date->format(self::DATE_ONLY_FORMAT);

    if ($full_start_date === $full_end_date) {
      $date = $start_date->format(self::DATE_ONLY_FORMAT);
      $start_time = $start_date->format(self::TIME_ONLY_FORMAT);
      $end_time = $end_date->format(self::TIME_ONLY_FORMAT);

      if ($start_time !== $end_time) {
        $translation_args = [
          '@date' => $date,
          '@start_time' => $start_time,
          '@end_time' => $end_time,
        ];
        return $this->t('On @date at @start_time-@end_time', $translation_args, $translation_options);
      }
      else {
        $translation_args = ['@date' => $date, '@time' => $start_time];
        return $this->t('On @date at @time', $translation_args, $translation_options);
      }
    }

    return $full_start_date . ' - ' . $full_end_date;
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupFrontPageDetails($group_id = NULL): ?array {
    $current_language_id = $this->languageManager->getCurrentLanguage()->getId();

    // Load group using group_id parameter, if exists.
    $group = NULL;
    if (isset($group_id)) {
      $group = $this->entityTypeManager->getStorage('group')->load($group_id);
    }

    /** @var \Drupal\group\Entity\GroupInterface|null $current_group */
    $current_group = $group ?? $this->getCurrentGroup();

    if (!isset($current_group) || !$current_group->hasTranslation($current_language_id)) {
      return NULL;
    }

    $translated_group = $current_group->getTranslation($current_language_id);
    if (!$translated_group->hasField('field_front_page') || $translated_group->get('field_front_page')->isEmpty()) {
      return NULL;
    }

    $group_front_page = $translated_group->get('field_front_page')->entity;
    if (!($group_front_page instanceof EntityInterface)) {
      return NULL;
    }

    $group_front_page_id = $group_front_page->id();

    /** @var \Drupal\node\NodeInterface $translated_front_page */
    $translated_front_page = $this->entityRepository->getTranslationFromContext($group_front_page);
    $group_front_page_title = $translated_front_page->getTitle();
    $group_front_page_url = $translated_front_page->toUrl()->toString();

    return [
      $group_front_page_url,
      $group_front_page_id,
      $group_front_page_title,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getInternalLinkParagraphContents(ParagraphInterface $link_paragraph): ?array {
    if (!($link_paragraph instanceof ParagraphInterface) || !$link_paragraph->hasField('field_internal_link')) {
      return NULL;
    }

    $link_text = NULL;
    if ($link_paragraph->hasField('field_link_text') && !$link_paragraph->get('field_link_text')->isEmpty()) {
      $link_text = $link_paragraph->get('field_link_text')->getString();
    }

    $referenced_entities = $link_paragraph->get('field_internal_link')->referencedEntities();
    $referenced_node = reset($referenced_entities);

    if (!($referenced_node instanceof NodeInterface)) {
      return NULL;
    }

    /** @var \Drupal\node\NodeInterface $translated_node */
    $translated_node = $this->entityRepository->getTranslationFromContext($referenced_node);

    $node_id = $translated_node->id();

    $node_is_published = $translated_node->isPublished();
    if (!$node_is_published) {
      // Return empty string in place of node URL when node is unpublished.
      return ['', $node_id, $link_text];
    }

    $node_url = $translated_node->toUrl()->toString();

    return [$node_url, $node_id, $link_text];
  }

  /**
   * {@inheritdoc}
   */
  public function getListFieldValues(array $list_field_raw_values): array {
    $values = [];
    foreach ($list_field_raw_values as $key => $value) {
      $values[$key] = $value['value'];
    }
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function getNodeFieldDate(NodeInterface $node, $field_name) {
    if (!($node instanceof EntityInterface)) {
      return NULL;
    }

    if (!$node->hasField($field_name)) {
      return NULL;
    }

    if ($node->get($field_name)->isEmpty()) {
      return NULL;
    }

    // @phpstan-ignore-next-line
    return $node->get($field_name)->date;
  }

  /**
   * {@inheritdoc}
   */
  public function getParagraphTaxonomyTerms(ParagraphInterface $paragraph, array $available_taxonomy_fields): array {
    $values = [];
    foreach ($available_taxonomy_fields as $taxonomy) {
      $field_name = "field_{$taxonomy}";
      if ($paragraph->hasField($field_name)) {
        $raw_field_value = $paragraph->get($field_name)->getValue();
        $values[$taxonomy] = $this->getEntityReferenceFieldValues($raw_field_value);
      }
    }

    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function isMinisite($group_id = NULL): bool {
    if ($group_id) {
      $current_group = $this->entityTypeManager->getStorage('group')->load($group_id);
    }
    else {
      $current_group = $this->getCurrentGroup();
    }

    if (!isset($current_group)) {
      return FALSE;
    }

    $current_group_type = $current_group->bundle();

    if ($current_group_type == 'minisite') {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isSubsite(): bool {
    $current_group = $this->getCurrentGroup();

    if (!isset($current_group)) {
      return FALSE;
    }

    $current_group_type = $current_group->bundle();

    if ($current_group_type == 'subsite') {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isInternalLinkParagraph(ParagraphInterface $link_paragraph): bool {
    if (!($link_paragraph instanceof ParagraphInterface)) {
      return FALSE;
    }

    if (!$link_paragraph->hasField('field_internal_link')) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getFileExtensionFromUrl($url): ?string {
    $path_parts = pathinfo($url);
    if (isset($path_parts['extension'])) {
      $extension = $path_parts['extension'];
    }
    else {
      $extension = NULL;
    }
    return $extension;
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupPaletteClass(GroupInterface $group): ?string {
    /** @var \Drupal\group\Entity\GroupInterface $translated_group */
    $translated_group = $this->entityRepository->getTranslationFromContext($group);

    $group_has_palette_field = $translated_group->bundle() === 'minisite' &&
      $translated_group->hasField('field_color_palette');

    if (!$group_has_palette_field) {
      // If the group is not a minisite, no palette should be applied.
      return NULL;
    }

    $group_has_palette = !$translated_group->get('field_color_palette')->isEmpty();
    if ($group_has_palette) {
      $color_palette_class = $translated_group->get('field_color_palette')->getString();

      return $color_palette_class;
    }

    return self::DEFAULT_PALETTE_CLASS;
  }

  /**
   * {@inheritdoc}
   */
  public function convertCoordinates(float $longitude, float $latitude): array {
    // Initialise Proj4.
    $proj4 = new Proj4php();

    // Create two different projections.
    $proj_WGS84 = new Proj('EPSG:4326', $proj4);
    $proj_ETRS_TM35FIN = new Proj('EPSG:3067', $proj4);

    // Create a point.
    $point_source = new Point($longitude, $latitude, $proj_WGS84);

    // Transform the point between datums.
    $point_destination = $proj4->transform($proj_ETRS_TM35FIN, $point_source);
    $converted_point = explode(" ", $point_destination->toShortString());

    return $converted_point;
  }

  /**
   * {@inheritdoc}
   */
  public function getNodeLiftupMap(NodeInterface $node, string $map_class, bool $use_lazyload = FALSE): array {
    $map = [];

    if (!($node instanceof NodeInterface)) {
      return $map;
    }

    $node_id = $node->id();
    $node_title = $node->getTitle();

    $iframe_url = Settings::get('mml_map_base_url');
    $map = [
      'frame' => [
        '#type' => 'inline_template',
        '#attached' => [
          'library' => [
            'tre_preprocess/liftup-map-rpc',
          ],
        ],
        '#template' => '<iframe class="{{ map_classes }}" data-node-id="{{ node_id }}" data-tampere-cookie-information="skip" title="{% trans %} Map: {{ title }} {% endtrans %}" {% if use_lazyload %}loading="lazy"{% endif %} {{ src_attr }}="{{ url }}" allow="geolocation" style="border: none; display: block; width: 100%; height: 410px;"></iframe>',
        '#context' => [
          'use_lazyload' => $use_lazyload,
          'map_classes' => $use_lazyload ? "{$map_class} lazyload" : $map_class,
          'node_id' => $node_id,
          'src_attr' => $use_lazyload ? 'data-src' : 'src',
          'url' => $iframe_url,
          'title' => $node_title,
        ],
      ],
    ];

    return $map;
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupIdFromSearchViewPath($path): ?string {
    $route_name = $this->routeMatch->getRouteName();

    if (empty($path) || ($route_name != 'view.search.page_1')) {
      return NULL;
    }

    $group_id = NULL;
    // Convert path to array and remove empty values, if any.
    $current_path_parts = array_filter(explode('/', $path));
    // Rule of thumb is, the group ID is the last element in the path array.
    $is_group_id_valid = (is_array($current_path_parts) && count($current_path_parts) === 2 && ctype_digit(end($current_path_parts)));

    if ($is_group_id_valid) {
      $group_id_from_path = end($current_path_parts);

      // Additionally, check if the group_id exists.
      $group = $this->entityTypeManager->getStorage('group')->load($group_id_from_path);
      if ($group instanceof GroupInterface) {
        $group_id = $group_id_from_path;
      }
    }

    return $group_id;
  }

}
