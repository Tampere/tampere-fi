<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\Component\Utility\Html;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;
use Drupal\tre_preprocess_utility_functions\Utils\HelperFunctionsInterface;

/**
 * PTV service liftup paragraph preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.paragraph__ptv_service_liftup",
 *   hook = "paragraph__ptv_service_liftup"
 * )
 */
class PtvServiceLiftup extends TrePreProcessPluginBase {

  /**
   * The view mode to use for Service node fields in the paragraph liftup.
   */
  const LIFTUP_VIEW_MODE = 'service_liftup';

  /**
   * The view mode to use for 'E-Service channel' nodes in the liftup.
   */
  const ESERVICE_CHANNEL_LIFTUP_VIEW_MODE = 'eservice_channel_liftup_view_mode';

  /**
   * The view mode to use for the Service node in the liftup inside accordion.
   *
   * This is used for the title of the Service liftup when it is placed
   * inside an accordion paragraph. It is used to select the correct heading
   * level in the field--node--title.html.twig template.
   */
  const INSIDE_ACCORDION_VIEW_MODE = 'inside-accordion';

  /**
   * The paragraph to display.
   *
   * @var \Drupal\paragraphs\ParagraphInterface
   */
  protected ParagraphInterface $paragraph;

  /**
   * The ptv_service node to display using this paragraph.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected NodeInterface $service;

  /**
   * The collator for Finnish alphabetical sorting.
   *
   * @var \Collator|null
   */
  private ?\Collator $collatorFi = NULL;

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $new_content = [];
    $paragraph = $variables['paragraph'];

    if (!($paragraph instanceof ParagraphInterface)) {
      return $variables;
    }

    /** @var \Drupal\paragraphs\ParagraphInterface $translated_service_liftup */
    $translated_service_liftup = $this->entityRepository->getTranslationFromContext($paragraph);

    // Set up instance variable as soon as we're certain it is the correct one
    // and of the correct type, in this case it is actually a ParagraphInterface
    // instance.
    $this->paragraph = $translated_service_liftup;

    if (!$translated_service_liftup->hasField('field_service') || $translated_service_liftup->get('field_service')->isEmpty()) {
      $variables['content'] = $new_content;
      return $variables;
    }

    $referenced_entities = $translated_service_liftup->get('field_service')->referencedEntities();
    $service_node = reset($referenced_entities);

    // While field_service is required in the paragraph, it is possible that the
    // service node is deleted after the paragraph has been created, so we need
    // to handle the situation gracefully by not displaying anything.
    if (!($service_node instanceof NodeInterface)) {
      $variables['content'] = $new_content;
      return $variables;
    }

    /** @var \Drupal\node\NodeInterface $translated_service_node */
    $translated_service_node = $this->entityRepository->getTranslationFromContext($service_node);

    $node_id = $translated_service_node->id();
    $variables['#cache']['tags'][] = "node:{$node_id}";

    $node_is_published = $translated_service_node->isPublished();
    if (!$node_is_published) {
      $variables['content'] = $new_content;
      return $variables;
    }

    // Set up instance variable as soon as we're certain it is the correct one
    // and of the correct type, in this case it is actually a NodeInterface
    // instance.
    $this->service = $translated_service_node;

    $displayed_fields_field_values = $translated_service_liftup->get('field_service_displayed_fields')->getValue();
    $service_fields_selected_for_display = $this->helperFunctions->getListFieldValues($displayed_fields_field_values);

    $field_map = $this->getFieldRenderMap($this->paragraph, $this->service);

    // As the field field_service_displayed_fields also has the ordering
    // information for the fields to display, the $delta becomes important to
    // preserve.
    // Some selections share one renderer (for example online and form
    // channels), so track those groups to avoid rendering the same accordion
    // more than once when both selections are enabled.
    $rendered_callback_groups = [];
    foreach ($service_fields_selected_for_display as $delta => $field_to_display) {
      if (!array_key_exists($field_to_display, $field_map)) {
        continue;
      }

      $render_group = $field_map[$field_to_display]['render_group'] ?? NULL;
      if ($render_group !== NULL && isset($rendered_callback_groups[$render_group])) {
        continue;
      }

      if ($render_group !== NULL) {
        $rendered_callback_groups[$render_group] = TRUE;
      }

      if (isset($field_map[$field_to_display]['required_service_field'])) {
        $required_service_field = $field_map[$field_to_display]['required_service_field'];
        if (!$this->service->hasField($required_service_field) || $this->service->get($required_service_field)->isEmpty()) {
          continue;
        }
      }

      if ($field_map[$field_to_display]['type'] == 'entity_field_view') {
        $new_content[$delta] = $field_map[$field_to_display]['entity']->get($field_map[$field_to_display]['field'])->view(self::LIFTUP_VIEW_MODE);

        $parent = $paragraph->getParentEntity();
        if ($parent instanceof ParagraphInterface) {
          $bundle = $parent->bundle();

          if ($bundle == 'accordion_item' || $bundle == 'process_accordion_item') {
            $new_content[$delta] = $field_map[$field_to_display]['entity']->get($field_map[$field_to_display]['field'])->view(self::INSIDE_ACCORDION_VIEW_MODE);
          }
        }
      }

      if ($field_map[$field_to_display]['type'] == 'callable') {
        if (isset($field_map[$field_to_display]['service_channel_type'])) {
          $new_content[$delta] = call_user_func($field_map[$field_to_display]['callable'], $field_map[$field_to_display]['service_channel_type']);
        }
        else {
          $new_content[$delta] = call_user_func($field_map[$field_to_display]['callable']);
        }
      }

      $new_content[$delta]['#weight'] = $delta;
    }

    $variables['content'] = $new_content;

    return $variables;
  }

  /**
   * Callback for rendering ptv_service node's service_channel nodes.
   *
   * @param string $type
   *   The type of the service channel to retrieve.
   *
   * @return array
   *   An accordion render array containing the service_channel nodes.
   */
  protected function getServiceChannelsRendered(string $type): array {
    $rendered = $this->getServiceChannelItemsRendered($type);

    if (!$rendered) {
      return [];
    }

    $heading = match ($type) {
      'phone_service' => $this->getAccordionHeadingOverride('field_heading__phone_service', count($rendered))
        ?? new TranslatableMarkup('Call (@count)', ['@count' => count($rendered)], ['context' => 'PTV service liftup accordion heading']),
      'web_page' => $this->getAccordionHeadingOverride('field_heading__web_page', count($rendered))
        ?? new TranslatableMarkup('Read more (@count)', ['@count' => count($rendered)], ['context' => 'PTV service liftup accordion heading']),
      default => throw new \InvalidArgumentException(sprintf('Unsupported service channel type: %s', $type)),
    };

    return $this->buildAccordion(
      $heading,
      $rendered,
      $type
    );
  }

  /**
   * Renders the electronic service and form channels in one accordion.
   *
   * @return array
   *   An accordion render array containing the electronic service channels.
   */
  protected function getElectronicServiceChannelsRendered(): array {
    // Online and form channels are separate selectable fields in the
    // paragraph, but are presented together in one electronic-services
    // accordion.
    $combined = [];

    $online_fields = $this->getChannelDisplayedFields('eservice');
    if ($online_fields) {
      foreach ($this->getServiceChannelNodes('eservice') as $node) {
        $combined[] = ['node' => $node, 'type' => 'eservice', 'fields' => $online_fields];
      }
    }

    $form_fields = $this->getChannelDisplayedFields('form_service');
    if ($form_fields) {
      foreach ($this->getServiceChannelNodes('form_service') as $node) {
        $combined[] = ['node' => $node, 'type' => 'form_service', 'fields' => $form_fields];
      }
    }

    if (!$combined) {
      return [];
    }

    // Present the combined online and form channels in one alphabetical list,
    // ordered by the service channel title.
    $collator = $this->getCollator();
    usort($combined, function ($a, $b) use ($collator) {
      return $collator->compare($a['node']->getTitle(), $b['node']->getTitle());
    });

    $rendered = [];
    foreach ($combined as $item) {
      $rendered[] = $this->renderServiceChannelNode($item['node'], $item['type'], $item['fields']);
    }

    return $this->buildAccordion(
      $this->getAccordionHeadingOverride('field_heading__e_service', count($rendered))
        ?? new TranslatableMarkup('Use online services (@count)', ['@count' => count($rendered)], ['context' => 'PTV service liftup accordion heading']),
      $rendered,
      'electronic-service'
    );
  }

  /**
   * Builds the render arrays for a PTV service's service channel nodes.
   *
   * The channels are ordered alphabetically by their title.
   *
   * @param string $type
   *   The type of service channel to retrieve.
   *
   * @return array
   *   Channel render arrays.
   */
  private function getServiceChannelItemsRendered(string $type): array {
    $fields_selected_for_display = $this->getChannelDisplayedFields($type);
    if (!$fields_selected_for_display) {
      return [];
    }

    $nodes = $this->getServiceChannelNodes($type);
    $this->sortNodesByTitle($nodes);

    $rendered = [];
    foreach ($nodes as $node) {
      $rendered[] = $this->renderServiceChannelNode($node, $type, $fields_selected_for_display);
    }

    return $rendered;
  }

  /**
   * Gets the display field keys selected for a service channel type.
   *
   * @param string $type
   *   The type of service channel.
   *
   * @return string[]
   *   The selected display field keys, or an empty array when nothing is
   *   selected for the type.
   */
  private function getChannelDisplayedFields(string $type): array {
    $ptv_service_liftup_display_field_names_by_type = [
      'phone_service' => 'field_phone_displayed_fields',
      'eservice' => 'field_online_displayed_fields',
      'web_page' => 'field_web_displayed_fields',
      'form_service' => 'field_form_displayed_fields',
    ];

    $fields_to_display_field_name = $ptv_service_liftup_display_field_names_by_type[$type] ?? NULL;

    if (!$this->paragraph->hasField($fields_to_display_field_name) ||
      $this->paragraph->get($fields_to_display_field_name)->isEmpty()) {
      return [];
    }

    $service_channel_fields_to_display_value = $this->paragraph->get($fields_to_display_field_name)->getValue();

    return $this->helperFunctions->getListFieldValues($service_channel_fields_to_display_value);
  }

  /**
   * Gets the referenced service channel nodes for a channel type.
   *
   * @param string $type
   *   The type of service channel.
   *
   * @return \Drupal\node\NodeInterface[]
   *   The referenced service channel nodes, excluding any that have been
   *   deleted.
   */
  private function getServiceChannelNodes(string $type): array {
    $nodes = [];

    $service_channel_field = "field_{$type}_channels";
    if (!$this->service->hasField($service_channel_field) ||
      $this->service->get($service_channel_field)->isEmpty()) {
      return $nodes;
    }

    foreach ($this->service->get($service_channel_field)->referencedEntities() as $referenced_service_channel) {
      // If the service_channel node has been deleted, we don't want to try to
      // render it.
      if ($referenced_service_channel instanceof NodeInterface) {
        $nodes[] = $referenced_service_channel;
      }
    }

    return $nodes;
  }

  /**
   * Renders a single service channel node for the liftup.
   *
   * @param \Drupal\node\NodeInterface $referenced_service_channel
   *   The service channel node to render.
   * @param string $type
   *   The type of service channel, used to pick the view mode.
   * @param string[] $fields_selected_for_display
   *   The display field keys selected for the channel type.
   *
   * @return array
   *   The render array for the service channel.
   */
  private function renderServiceChannelNode(NodeInterface $referenced_service_channel, string $type, array $fields_selected_for_display): array {
    $view_builder = $this->entityTypeManager->getViewBuilder('node');

    // Each channel type has its own paragraph field for selecting which of its
    // PTV fields to show. Values here map those selection keys to the actual
    // fields on the referenced service-channel node.
    // Names for all fields that should be displayed in the content region
    // of the component except for boolean fields that are handled
    // separately at the end. Keyed by their corresponding display field keys.
    $content_field_names_by_display_field_keys = [
      'attachments' => 'field_service_attachments',
      'areas' => 'field_area_text',
      'accessibility' => [
        'field_accessibility_and_services',
        'field_accessibility_links',
      ],
      'description' => 'field_body_md',
      'forms' => 'field_form_links',
      'form_delivery_details' => [
        'field_form_receiver',
        'field_address_postal',
        'field_delivery_details',
      ],
      'languages' => 'field_available_languages',
      'link' => 'field_links',
      'organization' => 'field_organization',
      'phone_numbers' => 'field_additional_phones',
      'services' => 'field_services',
      'service_hours' => [
        'field_service_hours_daily',
        'field_exception_hours',
      ],
      'service_hours_normal_daily' => 'field_service_hours_daily',
      'service_hours_normal_overnight' => 'field_service_hours_overnight',
      'service_hours_exceptions' => 'field_exception_hours',
      'summary' => 'field_summary',
      'support' => [
        'field_support_phones',
        'field_support_emails',
      ],
    ];

    $fields_to_render = array_filter($content_field_names_by_display_field_keys, function ($key) use ($fields_selected_for_display) {
      return in_array($key, $fields_selected_for_display, TRUE);
    }, ARRAY_FILTER_USE_KEY);

    $fields_with_values = [];
    foreach ($fields_to_render as $field_name) {
      if (is_array($field_name)) {
        foreach ($field_name as $field_name_group_item) {
          if ($referenced_service_channel->hasField($field_name_group_item)) {
            $fields_with_values[$field_name_group_item] = $referenced_service_channel->get($field_name_group_item)->getValue();
          }
        }
      }
      elseif ($referenced_service_channel->hasField($field_name)) {
        $fields_with_values[$field_name] = $referenced_service_channel->get($field_name)->getValue();
      }
    }

    $service_channel_title = $referenced_service_channel->getTitle();
    $show_title = in_array('name', $fields_selected_for_display, TRUE);
    // Build a temporary node with only the selected source values so the
    // existing service-channel view modes can format the liftup consistently.
    $node_information = [
      'type' => 'service_channel',
      'title' => $service_channel_title,
      'field_channel_display_name' => $show_title ? $service_channel_title : NULL,
    ];

    $node_configuration = array_merge($node_information, $fields_with_values);
    $service_channel_liftup_node = Node::create($node_configuration);

    $content_boolean_fields_by_display_field_key = [
      'electronic_id_required' => 'field_electronic_id_required',
      'electronic_signature_required' => 'field_electronic_signature_rqd',
    ];

    // Remove boolean fields that have not been selected for display from the
    // newly created node as the boolean fields will otherwise always be
    // displayed.
    foreach ($content_boolean_fields_by_display_field_key as $key => $boolean_field) {
      if ($referenced_service_channel->hasField($boolean_field)) {
        $field_not_selected_for_display = !in_array($key, $fields_selected_for_display, TRUE);
        if ($field_not_selected_for_display) {
          unset($service_channel_liftup_node->$boolean_field);
        }
      }
    }

    $service_channel_liftup_node->addCacheTags($referenced_service_channel->getCacheTags());
    $service_channel_liftup_node->addCacheContexts($referenced_service_channel->getCacheContexts());

    $view_mode = $type === 'eservice' ? self::ESERVICE_CHANNEL_LIFTUP_VIEW_MODE : 'default';

    return $view_builder->view($service_channel_liftup_node, $view_mode);
  }

  /**
   * Sorts an array of nodes alphabetically by title using Finnish rules.
   *
   * @param \Drupal\node\NodeInterface[] $nodes
   *   The nodes to sort, by reference.
   */
  private function sortNodesByTitle(array &$nodes): void {
    $collator = $this->getCollator();
    usort($nodes, function ($a, $b) use ($collator) {
      return $collator->compare($a->getTitle(), $b->getTitle());
    });
  }

  /**
   * Gets the Finnish collator used for alphabetical sorting.
   *
   * @return \Collator
   *   The Finnish collator, lazily initialized.
   */
  private function getCollator(): \Collator {
    if ($this->collatorFi === NULL) {
      $this->collatorFi = new \Collator('fi_FI');
    }
    return $this->collatorFi;
  }

  /**
   * Callback for rendering a ptv_service node's place_of_business nodes.
   *
   * This methods leverages the output already implemented for place_of_business
   * liftup paragraphs where the field used for selecting the node fields to
   * display is the same as the one used in this paragraph.
   *
   * @return array
   *   An accordion render array containing the place_of_business nodes.
   */
  protected function getPlacesOfBusinessRendered() {
    $rendered = [];

    // Do not render the accordion if no fields are selected for display.
    if (!$this->paragraph->hasField('field_pob_displayed_fields') ||
      $this->paragraph->get('field_pob_displayed_fields')->isEmpty()) {
      return $rendered;
    }

    if (!$this->service->hasField('field_places_of_business') ||
      $this->service->get('field_places_of_business')->isEmpty()) {
      return $rendered;
    }

    $place_of_business_fields_to_display_value = $this->paragraph->get('field_pob_displayed_fields');
    $view_builder = $this->entityTypeManager->getViewBuilder('paragraph');

    // Collect the referenced places of business, skipping any that have been
    // deleted, so they can be sorted alphabetically by name.
    $places_of_business = [];
    foreach ($this->service->get('field_places_of_business')->referencedEntities() as $place_of_business) {
      if ($place_of_business instanceof NodeInterface) {
        $places_of_business[] = $place_of_business;
      }
    }

    $this->sortNodesByTitle($places_of_business);

    foreach ($places_of_business as $place_of_business) {
      // The 'id' property is needed in order to render an anchor link for the
      // artificial paragraph. So we create an artificial ID based on the node.
      $place_liftup = Paragraph::create([
        'id' => 'pob-' . $place_of_business->id(),
        'type' => 'place_of_business_liftup',
        'field_pob_displayed_fields' => $place_of_business_fields_to_display_value,
        'field_place_of_business' => $place_of_business->id(),
      ]);

      $rendered[] = $view_builder->view($place_liftup, PlaceOfBusinessLiftup::LIFTUP_VIEW_MODE);
    }

    if (!$rendered) {
      return [];
    }

    return $this->buildAccordion(
      $this->getAccordionHeadingOverride('field_heading__pob', count($rendered))
        ?? new TranslatableMarkup('Visit in person (@count)', ['@count' => count($rendered)], ['context' => 'PTV service liftup accordion heading']),
      $rendered,
      'places-of-business'
    );
  }

  /**
   * Gets the override accordion heading, if one is set on the paragraph.
   *
   * The item count is always appended to the override label.
   *
   * @param string $override_field
   *   The machine name of the heading override field on the paragraph.
   * @param int $count
   *   The number of items in the accordion.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|null
   *   The override heading, or NULL when no override is set.
   */
  private function getAccordionHeadingOverride(string $override_field, int $count): ?TranslatableMarkup {
    $override_text = $this->getHeadingOverrideText($override_field);
    if ($override_text === '') {
      return NULL;
    }

    return new TranslatableMarkup('@label (@count)', [
      '@label' => $override_text,
      '@count' => $count,
    ], ['context' => 'PTV service liftup accordion heading']);
  }

  /**
   * Gets the override heading text for a field, if set.
   *
   * @param string $field_name
   *   The machine name of the heading override field.
   *
   * @return string
   *   The override heading text, or an empty string when not set.
   */
  private function getHeadingOverrideText(string $field_name): string {
    if (!$this->paragraph->hasField($field_name)) {
      return '';
    }

    $field = $this->paragraph->get($field_name);
    if ($field->isEmpty()) {
      return '';
    }

    return trim((string) $field->first()->get('text')->getValue());
  }

  /**
   * Wraps a group of service liftup items in the theme's accordion component.
   *
   * @param string|\Drupal\Core\StringTranslation\TranslatableMarkup $heading
   *   The translated accordion heading.
   * @param array $content
   *   Render arrays for the accordion content.
   * @param string $suffix
   *   A unique suffix for the accordion ID within this paragraph.
   *
   * @return array
   *   The accordion render array.
   */
  private function buildAccordion(
    string|TranslatableMarkup $heading,
    array $content,
    string $suffix,
  ): array {
    // Include both the paragraph ID and group suffix so multiple accordions
    // from the same paragraph have unique control/content IDs.
    return [
      '#theme' => 'tre_preprocess_ptv_service_accordion',
      '#heading' => $heading,
      '#content' => $content,
      '#accordion_id' => 'ptv-service-liftup-' . $this->paragraph->id() . '-' . $suffix,
      '#cache' => [
        'contexts' => ['languages:language_interface'],
      ],
    ];
  }

  /**
   * Callback for rendering a ptv_service node's chargeability information.
   *
   * @todo Still missing 'base description' since the location of this in the
   * API is still unknown.
   *
   * @return array[]
   *   Render array for the chargeability information.
   */
  protected function getChargeTypeInfoRendered() {
    // Only render the heading when at least one of the fields has a value.
    if ($this->service->get('field_service_charge_type')->isEmpty() && $this->service->get('field_chargeability_info_md')->isEmpty()) {
      return [];
    }

    $chargeability_heading_field = $this->service->get('field_chargeability_heading');
    $chargeability_heading_text = $chargeability_heading_field->isEmpty()
      ? ''
      : Html::decodeEntities(strip_tags($chargeability_heading_field->first()->get('markup')->getString()));
    $chargeability_heading = $chargeability_heading_text === ''
      ? []
      : [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $chargeability_heading_text,
      ];

    $rendered = [
      'heading' => $chargeability_heading,
      'chargeability' => $this->service->get('field_service_charge_type')->view(self::LIFTUP_VIEW_MODE),
      'chargeability_info' => $this->service->get('field_chargeability_info_md')->view(self::LIFTUP_VIEW_MODE),
    ];

    return $rendered;
  }

  /**
   * Callback for rendering a ptv_service node's service voucher information.
   *
   * @return array[]
   *   Render array for the service voucher information.
   */
  protected function getServiceVoucherInfoRendered() {
    // Only render the heading (and other info) when the service indicates that
    // the vouchers are in use for it.
    $vouchers_in_use = $this->helperFunctions->getFieldValueString($this->service, 'field_service_vouchers_in_use');
    if ($vouchers_in_use !== HelperFunctionsInterface::BOOLEAN_FIELD_TRUE) {
      return [];
    }

    $rendered = [
      'voucher_heading' => $this->service->get('field_service_voucher_heading')->view(self::LIFTUP_VIEW_MODE),
      'vouchers_enabled' => $this->service->get('field_service_vouchers_in_use')->view(self::LIFTUP_VIEW_MODE),
      'voucher_links' => $this->service->get('field_service_voucher_links')->view(self::LIFTUP_VIEW_MODE),
    ];

    return $rendered;
  }

  /**
   * Callback for rendering a ptv_service node's production information.
   *
   * @return array[]
   *   Render array for the production information.
   */
  protected function getProductionInfoRendered(): array {
    $rendered = [
      'service_producer' => $this->service->get('field_service_producer')->view(self::LIFTUP_VIEW_MODE),
      'service_other_responsible' => $this->service->get('field_service_other_responsible')->view(self::LIFTUP_VIEW_MODE),
      'service_responsible' => $this->service->get('field_service_responsible')->view(self::LIFTUP_VIEW_MODE),
    ];

    return $rendered;
  }

  /**
   * Defines the rendering info for each field available for display.
   *
   * The format of the array is:
   * [
   *   'type' => 'entity_field_view' OR 'callable',
   *   'entity' => the entity whose field to view when using entity_field_view,
   *   'field' => the name of the field to view  when using entity_field_view,
   *   'callable' => the PHP callable to call when using callable
   * ]
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph to use in entity_field_view.
   * @param \Drupal\node\NodeInterface $service_node
   *   The ptv_service node to use in entity field view.
   *
   * @return array[]
   *   Structured array keyed by field available for display in
   *   field_service_displayed_fields containing the rendering information for
   *   each field.
   */
  private function getFieldRenderMap(ParagraphInterface $paragraph, NodeInterface $service_node): array {
    $field_map = [
      // These configurable section headings belong to the paragraph, while
      // the service details below are rendered from the referenced PTV node.
      'heading__basic_info' => [
        'type' => 'entity_field_view',
        'entity' => $paragraph,
        'field' => 'field_heading__basic_info',
      ],
      'heading__instruction' => [
        'type' => 'entity_field_view',
        'entity' => $paragraph,
        'field' => 'field_heading__instruction',
        'required_service_field' => 'field_user_instruction_md',
      ],
      'heading__requirements' => [
        'type' => 'entity_field_view',
        'entity' => $paragraph,
        'field' => 'field_heading__requirements',
        'required_service_field' => 'field_requirements_md',
      ],
      'name' => [
        'type' => 'entity_field_view',
        'entity' => $service_node,
        'field' => 'title',
      ],
      'summary' => [
        'type' => 'entity_field_view',
        'entity' => $service_node,
        'field' => 'field_summary',
      ],
      'description' => [
        'type' => 'entity_field_view',
        'entity' => $service_node,
        'field' => 'field_body_md',
      ],
      'places_of_business' => [
        'type' => 'callable',
        'callable' => [
          $this,
          'getPlacesOfBusinessRendered',
        ],
      ],
      'user_instruction' => [
        'type' => 'entity_field_view',
        'entity' => $service_node,
        'field' => 'field_user_instruction_md',
      ],
      'requirements' => [
        'type' => 'entity_field_view',
        'entity' => $service_node,
        'field' => 'field_requirements_md',
      ],
      'charge_type' => [
        'type' => 'callable',
        'callable' => [
          $this,
          'getChargeTypeInfoRendered',
        ],
      ],
      'service_vouchers' => [
        'type' => 'callable',
        'callable' => [
          $this,
          'getServiceVoucherInfoRendered',
        ],
      ],
      'languages' => [
        'type' => 'entity_field_view',
        'entity' => $service_node,
        'field' => 'field_available_languages',
      ],
      'areas' => [
        'type' => 'entity_field_view',
        'entity' => $service_node,
        'field' => 'field_area_text',
      ],
      'production_info' => [
        'type' => 'callable',
        'callable' => [
          $this,
          'getProductionInfoRendered',
        ],
      ],
      'online_channels' => [
        'type' => 'callable',
        'callable' => [
          $this,
          'getElectronicServiceChannelsRendered',
        ],
        // Both selectable channel groups contribute to the same accordion.
        'render_group' => 'electronic_channels',
      ],
      'web_channels' => [
        'type' => 'callable',
        'callable' => [
          $this,
          'getServiceChannelsRendered',
        ],
        'service_channel_type' => 'web_page',
      ],
      'phone_channels' => [
        'type' => 'callable',
        'callable' => [
          $this,
          'getServiceChannelsRendered',
        ],
        'service_channel_type' => 'phone_service',
      ],
      'form_channels' => [
        'type' => 'callable',
        'callable' => [
          $this,
          'getElectronicServiceChannelsRendered',
        ],
        'render_group' => 'electronic_channels',
      ],
    ];
    return $field_map;
  }

}
