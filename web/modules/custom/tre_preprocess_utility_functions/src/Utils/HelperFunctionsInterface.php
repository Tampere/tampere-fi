<?php

namespace Drupal\tre_preprocess_utility_functions\Utils;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Provides a helper functions interface.
 */
interface HelperFunctionsInterface {

  /**
  * Defines the value for boolean fields when they are true.
  */
  const BOOLEAN_FIELD_TRUE = '1';

  /**
   * The base for the different colorful content liftup variations.
   */
  const COLORFUL_CONTENT_LIFTUP_VARIATION_BASE = 'colorful';

  /**
   * The basic palette class when no other has been selected.
   */
  const DEFAULT_PALETTE_CLASS = 'palette-1';

  /**
   * The default modifiers to use for the colorful content liftups.
   */
  const DEFAULT_COLORFUL_CONTENT_LIFTUP_MODIFIERS = [
    self::COLORFUL_CONTENT_LIFTUP_VARIATION_BASE,
    self::COLORFUL_CONTENT_LIFTUP_VARIATION_BASE . '-1',
  ];

  /**
   * The timezone to use for dates.
   */
  const TIMEZONE = 'Europe/Helsinki';

  /**
   * The format to use for dates in their full form.
   */
  const FULL_DATE_FORMAT = 'j.n.Y G.i';

  /**
   * The format to use for dates when displaying the date only.
   */
  const DATE_ONLY_FORMAT = 'j.n.Y';

  /**
   * The format to use for dates when displaying the time only.
   */
  const TIME_ONLY_FORMAT = 'G.i';

  /**
   * The machine name for the field containing a location's latitude.
   */
  const LATITUDE_FIELD_NAME = 'field_epsg_3067_northing';

  /**
   * The machine name for the field containing a location's longitude.
   */
  const LONGITUDE_FIELD_NAME = 'field_epsg_3067_easting';

  /**
   * Returns the breadcrumbs for the given node as a render array.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to build the breadcrumbs for.
   *
   * @return array|null
   *   The breadcrumbs as a render array if successful. Null otherwise.
   */
  public function getBreadcrumbs(NodeInterface $node);

  /**
   * Returns color field allowed values from form display as an array.
   *
   * @param string $entity_name
   *   The machine name for the entity.
   * @param string $bundle_name
   *   The machine name for the bundle.
   * @param string $form_mode
   *   The machine name for the form mode. Assigned 'default' by default.
   * @param string $field_name
   *   The machine name for the field. Assigned 'field_color' by default.
   *
   * @return array|null
   *   The allowed color values as an array if successful. Null otherwise.
   */
  public function getColorFieldAllowedValues($entity_name, $bundle_name, $form_mode = 'default', $field_name = 'field_color');

  /**
   * Returns colorful content liftup modifiers matching selected color.
   *
   * @param string $selected_color
   *   The selected color field value.
   *
   * @return array
   *   The modifiers as an array.
   */
  public function getColorfulContentLiftupModifiers($selected_color);

  /**
   * Returns the name, source URL, and ID for a media entity inside paragraph.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph entity containing the media entity reference.
   * @param string $media_field_name
   *   The name of the field containing the media entity reference in the
   *   paragraph. Defaults to 'field_media'.
   *
   * @return array|null
   *   An array containing the media entity name, source URL, and
   *   ID if successful. Null if unsuccessful.
   */
  public function getImageInformation(ParagraphInterface $paragraph, $media_field_name = 'field_media'): ?array;

  /**
   * Get the currently active group.
   *
   * @return \Drupal\group\Entity\GroupInterface|null
   *   The currently active group. Otherwise null.
   */
  public function getCurrentGroup(): ?GroupInterface;

  /**
   * Get the currently active group.
   *
   * @param \Drupal\node\NodeInterface $current_node
   *   The node to get the group for.
   *
   * @return \Drupal\group\Entity\GroupInterface|null
   *   The currently active group. Otherwise null.
   */
  public function getNodeGroup(NodeInterface $current_node): ?GroupInterface;

  /**
   * Returns location coordinates for the given node as an array.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to get the locations for.
   *
   * @return array
   *   The location coordinates as an array when they exist,
   *   empty array otherwise.
   */
  public function getNodeLiftupLocation(NodeInterface $node): array;

  /**
   * Returns entity reference field values as an array.
   *
   * @param array $entity_reference_field_values
   *   An entity reference field's raw value array.
   *
   * @return array
   *   An array containing the field values.
   */
  public function getEntityReferenceFieldValues(array $entity_reference_field_values);

  /**
   * Extracts the URL from an external link field.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $link_field
   *   The link field whose URL is to be extracted.
   *
   * @return string|null
   *   The URL as a string if successful. Null if unsuccessful.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function getExternalLinkFieldUrl(FieldItemListInterface $link_field): ?string;

  /**
   * Extracts the title from an external link field.
   *
   * @param \Drupal\Core\Field\FieldItemList $link_field
   *   The link field whose title is to be extracted.
   *
   * @return string|null
   *   The title as a string if successful. Null if unsuccessful.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function getExternalLinkFieldTitle(FieldItemList $link_field);

  /**
   * Extracts the URL and link title from an external link paragraph.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $link_paragraph
   *   The link paragraph whose contents are to be extracted.
   *
   * @return array|null
   *   The URL and title in an array. Null if unsuccessful.
   */
  public function getExternalLinkParagraphContents(ParagraphInterface $link_paragraph);

  /**
   * Returns field value from the given entity as a string.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to get the field value from.
   * @param string $field_name
   *   The name of the field to retrieve.
   *
   * @return string|null
   *   The field value as string if it exists. Null otherwise.
   */
  public function getFieldValueString(ContentEntityInterface $entity, $field_name);

  /**
   * Returns the formatted date from start and end dates given as a parameter.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $start_date
   *   The start date for the Involvement Opportunity content.
   * @param \Drupal\Core\Datetime\DrupalDateTime $end_date
   *   The end date for the Involvement Opportunity content. Defaults to NULL.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string
   *   The dates combined as a TranslatableMarkup object or a string.
   */
  public function getFormattedInvolvementOpportunityDate(DrupalDateTime $start_date, DrupalDateTime $end_date = NULL);

  /**
   * Gets the front page URL, node ID, and title for the currently active group.
   *
   * @param int|null $group_id
   *   The group id to fetch the front page details. Defaults to NULL, which
   *   means to use the 'current group' instead.
   * @param \Drupal\group\Entity\GroupInterface|null $group
   *   Optionally the group entity to get the front page details for.
   *   Defaults to NULL.
   *
   * @return array|null
   *   An array containing the group's front page URL as a string, the front
   *   page node ID, and the front page title. Null otherwise.
   */
  public function getGroupFrontPageDetails($group_id = NULL, $group = NULL);

  /**
   * Gets the URL, entity ID, and link text from an internal link paragraph.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $link_paragraph
   *   The link paragraph whose URL is to be extracted.
   *
   * @return array|null
   *   An array containing the URL as a string and the referenced
   *   entity ID. Optionally also link text. Null if unsuccessful.
   */
  public function getInternalLinkParagraphContents(ParagraphInterface $link_paragraph);

  /**
   * Returns values from the given list field's raw values as an array.
   *
   * @param array $list_field_raw_values
   *   A link field's raw value array.
   *
   * @return array
   *   An array containing the field values.
   */
  public function getListFieldValues(array $list_field_raw_values);

  /**
   * Returns date field value from the given node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to get the date field value from.
   * @param string $field_name
   *   The name of the field to retrieve.
   *
   * @return \Drupal\Core\Datetime\DrupalDateTime|null
   *   The field value as string if it exists. Null otherwise.
   */
  public function getNodeFieldDate(NodeInterface $node, $field_name);

  /**
   * Returns taxonomy term values on a given paragraph as an array.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph entity to get the taxonomy terms from.
   * @param array $available_taxonomy_fields
   *   The machine names for the taxonomy reference fields without the 'field_'
   *   prefix.
   *
   * @return array
   *   Taxonomy term values as key (=taxonomy) value (=terms) pairs.
   */
  public function getParagraphTaxonomyTerms(ParagraphInterface $paragraph, array $available_taxonomy_fields);

  /**
   * Checks whether the current page or given group id is part of a Minisite.
   *
   * @param int|null $group_id
   *   The group id to check for minisite or not. Defaults to NULL, which
   *   means to use the 'current group' instead.
   *
   * @return bool
   *   True if on Minisite content. False otherwise.
   */
  public function isMinisite($group_id = NULL);

  /**
   * Checks whether the current page is part of a Subsite.
   *
   * @return bool
   *   True if on Subsite content. False otherwise.
   */
  public function isSubsite();

  /**
   * Checks whether paragraph given as parameter contains internal link.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $link_paragraph
   *   The link paragraph to check.
   *
   * @return bool
   *   True for internal link paragraphs. False otherwise.
   */
  public function isInternalLinkParagraph(ParagraphInterface $link_paragraph);

  /**
   * Returns the file extension for the URL given as a parameter.
   *
   * @param string $url
   *   The URL to get the extension from.
   *
   * @return string|null
   *   The extension as a string or null if there is no extension.
   *   An empty extension returns null.
   */
  public function getFileExtensionFromUrl(string $url);

  /**
   * Gets the appropriate palette class for the given group.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group to base the palette class choice on.
   *
   * @return string|null
   *   The appropriate class as a string, or self::DEFAULT_PALETTE_CLASS when
   *   the group given is a minisite with an empty palette field. Null when
   *   given a group that is not a minisite (or if the minisite group has no
   *   palette field).
   */
  public function getGroupPaletteClass(GroupInterface $group): ?string;

  /**
   * Converts given coordinates from WGS84 to ETRS-TM35FIN.
   *
   * @param float $longitude
   *   The longitude to convert.
   * @param float $latitude
   *   The latitude to convert.
   *
   * @return array
   *   The coordinates as an array with longitude
   *   preceding latitude.
   */
  public function convertCoordinates(float $longitude, float $latitude): array;

  /**
   * Returns a map as a render array for the given node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to display the map for.
   * @param string $map_class
   *   The class name of the iframe.
   * @param bool $use_lazyload
   *   Flag for whether or not to use lazyloading on the map iframe.
   *   Defaults to FALSE.
   *
   * @return array
   *   A map as a render array if successful. Empty array otherwise.
   */
  public function getNodeLiftupMap(NodeInterface $node, string $map_class, bool $use_lazyload = FALSE): array;

  /**
   * Returns the group ID from search view path string, null if not found.
   *
   * @param string $path
   *   The current path as string.
   *
   * @return int|null
   *   The group ID as string or null if not found|invalid.
   */
  public function getGroupIdFromSearchViewPath(string $path);

}
