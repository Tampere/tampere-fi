<?php

namespace Drupal\tre_content_listing\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;

/**
 * Returns JSON array of 'Listing Content' nodes by taxonomy term.
 */
class ContentListingController extends ControllerBase {
  /**
   * The cid to use for caching responses.
   */
  const TRE_CONTENT_LISTING_CID = 'tre_content_listing';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The cache backend interface.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;


  /**
   * The file URL generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  public function __construct(EntityTypeManagerInterface $entity_type_manager, RequestStack $request_stack, CacheBackendInterface $cache, FileUrlGeneratorInterface $file_url_generator) {
    $this->entityTypeManager = $entity_type_manager;
    $this->requestStack = $request_stack;
    $this->cache = $cache;
    $this->fileUrlGenerator = $file_url_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('request_stack'),
      $container->get('cache.data'),
      $container->get('file_url_generator')
    );
  }

  /**
   * Returns sub-terms for the parent term that match query filters.
   */
  public function getSubTerms(int $term_id): JsonResponse {
    // Get the request.
    $request = $this->requestStack->getCurrentRequest();

    // Get current page, default to 1.
    $page = $request->query->get('page', 1);
    if (!ctype_digit($page) || $page < 1 || $page > 100) {
      $page = 1;
    }
    $limit = 25;
    $offset = ($page - 1) * $limit;

    // Get request params for filters and create cache id.
    $params = $this->parseRequestParams($request);
    $cid = $this->createCid('subterms', $term_id, $request, $params, $page);

    // Return cachecd response if it already exists.
    if ($cache = $this->cache->get($cid)) {
      return new JsonResponse($cache->data);
    }

    // Load the parent term.
    $parent_term = $this->entityTypeManager
      ->getStorage('taxonomy_term')
      ->load($term_id);

    if (!$parent_term || !$parent_term instanceof TermInterface) {
      return new JsonResponse(['error' => 'Invalid term ID'], 404);
    }

    // Load direct children.
    $child_terms = $this->entityTypeManager
      ->getStorage('taxonomy_term')
      ->loadTree('content_list_keywords', $term_id, 1, FALSE);

    // If filter is set to alphabetical, apply filters to direct child terms.
    if ($params['filter_type'] === 'alphabetical') {
      $child_terms = $this->filterTermsAlphabetically($child_terms, $params['letters']);
    }

    // Build an array of sub-term data, including node counts.
    $all_filtered_sub_terms = [];
    $total_node_count = 0;
    foreach ($child_terms as $child_term) {
      // Count how many nodes match for this child term.
      $query = $this->entityTypeManager->getStorage('node')->getQuery()
        ->condition('type', 'listing_content')
        ->condition('field_listing_keyword', $child_term->tid, 'IN')
        ->accessCheck(TRUE);

      // Apply hierarchical filters to nodes under the sub-term, if needed.
      if ($params['filter_type'] === 'hierarchical') {
        $this->applyHierarchicalFilters($query, $request);
      }
      $count = $query->count()->execute();

      // Only include terms that have matching nodes.
      if ($count > 0) {
        $all_filtered_sub_terms[] = [
          'term_name'      => $child_term->name,
          'term_id'        => $child_term->tid,
          'matching_items' => $count,
        ];
        $total_node_count = $total_node_count + $count;
      }
    }

    // Sort subterm list alphabetically.
    usort($all_filtered_sub_terms, function ($a, $b) {
      return strcmp($a['term_name'], $b['term_name']);
    });

    // Count the total number of matching terms.
    $total_terms = count($all_filtered_sub_terms);
    // Paginate terms and add pager data for the response.
    $paginated_sub_terms = array_slice($all_filtered_sub_terms, $offset, $limit);
    $total_pages = ceil($total_terms / $limit);

    $response_data = [
      'parent_term' => $parent_term->label(),
      'parent_tid'  => $term_id,
      'sub_terms'   => array_values($paginated_sub_terms),
      'total_count' => $total_node_count,
      'pagination'  => [
        'current_page' => $page,
        'per_page'     => $limit,
        'total_terms'  => $total_terms,
        'total_pages'  => $total_pages,
      ],
    ];

    // Cache the result with tags to make sure if
    // parent term / nodes change, cache gets invalidated.
    $cache_tags = [
      'taxonomy_term:' . $parent_term->id(),
      'node_list',
    ];
    $this->cache->set(
      $cid,
      $response_data,
      CacheBackendInterface::CACHE_PERMANENT,
      $cache_tags
    );

    return new JsonResponse($response_data);
  }

  /**
   * Returns the nodes (content list items) for a single sub-term.
   */
  public function getSubTermItems(int $term_id): JsonResponse {
    $request = $this->requestStack->getCurrentRequest();
    $params = $this->parseRequestParams($request);

    // Build the cid for the request.
    $cid = $this->createCid('subtermitems', $term_id, $request, $params);

    // Return cachced response if it exists.
    if ($cache = $this->cache->get($cid)) {
      return new JsonResponse($cache->data);
    }

    $child_term = $this->entityTypeManager
      ->getStorage('taxonomy_term')
      ->load($term_id);

    if (!$child_term || !$child_term instanceof TermInterface) {
      return new JsonResponse(['error' => 'Invalid term ID'], 404);
    }

    // Fetch nodes for the given sub-term.
    $node_storage = $this->entityTypeManager->getStorage('node');
    $query = $node_storage->getQuery()
      ->condition('type', 'listing_content')
      ->condition('field_listing_keyword', $term_id, 'IN')
      ->accessCheck(TRUE);

    // Only apply hierarchical filters to to node titles if needed.
    if ($params['filter_type'] === 'hierarchical') {
      $this->applyHierarchicalFilters($query, $request);
    }

    $nids = $query->execute();
    $nodes = $node_storage->loadMultiple($nids);

    $items = [];
    foreach ($nodes as $node) {

      $node_links = [];
      if ($node->hasField('field_item_links') && !$node->get('field_item_links')->isEmpty()) {
        // Go through each added link item and set values.
        foreach ($node->get('field_item_links') as $link_item) {
          $url = $link_item->getUrl();
          $link_title = $link_item->getTitle();

          // Define the link type, so React app knows which icon to render.
          $node_links[] = [
            'type' => $url->isExternal() ? 'external' : 'internal',
            'url' => $url->toString(),
            'title' => $link_title,
          ];
        }
      }

      // Get the text body field and strip it from HTML tags.
      $text_body = '';
      if ($node->hasField('field_text_body') && !$node->get('field_text_body')->isEmpty()) {
        $text_body = $node->get('field_text_body')->value;
      }

      // Get the attachment file URL and file name.
      $attachment_file = '';
      $attachment_file_name = '';
      if ($node->hasField('field_attachment_file') && !$node->get('field_attachment_file')->isEmpty()) {
        $media = $node->get('field_attachment_file')->entity;
        if ($media && $media->hasField('field_media_file') && !$media->get('field_media_file')->isEmpty()) {
          $file = $media->get('field_media_file')->entity;
          if ($file) {
            $attachment_file = $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri());
            $attachment_file_name = $media->getName();
          }
        }
      }

      // Get the listing image URL.
      $listing_image = '';
      if ($node->hasField('field_listing_image') && !$node->get('field_listing_image')->isEmpty()) {
        $media = $node->get('field_listing_image')->entity;
        if ($media && $media->hasField('field_media_image') && !$media->get('field_media_image')->isEmpty()) {
          $file = $media->get('field_media_image')->entity;
          if ($file) {
            $listing_image = $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri());
          }
        }
      }

      $items[] = [
        'id' => $node->id(),
        'title' => $node->label(),
        'text_body' => $text_body,
        'attachment_file' => $attachment_file,
        'attachment_file_name' => $attachment_file_name,
        'listing_image' => $listing_image,
        'links' => $node_links,
      ];
    }

    $response_data = [
      'term_id' => $child_term->id(),
      'term_name' => $child_term->label(),
      'filter_type' => $params['filter_type'],
      'items' => $items,
    ];

    // Cache the result with tags to make sure if subterm
    // or nodes change, cache gets invalidated.
    $cache_tags = [
      'taxonomy_term:' . $child_term->id(),
      'node_list',
    ];
    $this->cache->set(
      $cid,
      $response_data,
      CacheBackendInterface::CACHE_PERMANENT,
      $cache_tags
    );

    return new JsonResponse($response_data);
  }

  /**
   * Creates a cahce id with the given parameters.
   */
  private function createCid(string $type, int $term_id, Request $request, array $params, ?int $page = NULL): string {
    $cid = self::TRE_CONTENT_LISTING_CID . ':' . $type . ':' . $term_id . ':' . $params['filter_type'];

    // For alphabetical filters include letters in CID.
    if ($params['filter_type'] === 'alphabetical') {
      $cid .= ':' . implode('-', $params['letters']);
    }
    elseif ($params['filter_type'] === 'hierarchical') {
      // Get all query parameters.
      $query_params = $request->query->all();
      // Remove the keys already included in our cid.
      unset($query_params['filter_type'], $query_params['page'], $query_params['letter']);
      ksort($query_params);
      $hierarchical_string = http_build_query($query_params);
      $cid .= ':' . md5($hierarchical_string);
    }

    if ($page !== NULL) {
      $cid .= ':page:' . $page;
    }

    return $cid;
  }

  /**
   * Applies hierarchical filters to the node query.
   */
  private function applyHierarchicalFilters($query, Request $request): void {
    // Get all filter group parent terms.
    $filter_groups = $this->entityTypeManager
      ->getStorage('taxonomy_term')
      ->loadTree('filter_groups', 0, 1, TRUE);

    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $matching_nids = NULL;

    // Go through each filter group.
    foreach ($filter_groups as $filter_group) {
      $filter_name = $filter_group->label();
      // Convert filter group name to match the query param format.
      // eg. "Education Background" -> "education_background".
      $param_key = strtolower(str_replace(' ', '_', $filter_name));

      // Get all filter values matching the filter from URL.
      $filter_values = $request->query->all($param_key) ?? [];

      if (!empty($filter_values)) {
        // Load allowed child terms for this filter group.
        $allowed_terms = $term_storage->loadTree('filter_groups', $filter_group->id(), 1, FALSE);

        // Build a mapping of normalized term names to term IDs.
        $normalized_term_map = [];
        foreach ($allowed_terms as $term) {
          $normalized_name = strtolower(str_replace(' ', '_', $term->name));
          $normalized_term_map[$normalized_name] = $term->tid;
        }

        // Collect matching term IDs based on the query filter values.
        $matched_term_ids = [];
        foreach ($filter_values as $value) {
          if (isset($normalized_term_map[$value])) {
            $matched_term_ids[] = $normalized_term_map[$value];
          }
        }

        if (!empty($matched_term_ids)) {
          // Create a separate (OR) query for this filter group.
          // This query returns nodes that match at least one
          // of the filter options.
          $group_query = $this->entityTypeManager->getStorage('node')->getQuery();
          $group_query->condition('type', 'listing_content')
            ->condition('field_filter_options', $matched_term_ids, 'IN')
            ->accessCheck(TRUE);
          $group_nids = $group_query->execute();

          // If this is the first filter group, we initialize the matching
          // nid array with found OR group ids.
          if ($matching_nids === NULL) {
            $matching_nids = $group_nids;
          }
          else {
            // Intersect already matched nids with new filter group nids.
            // This filters out those nodes that dont have
            // both filter group values applied.
            $matching_nids = array_intersect($matching_nids, $group_nids);
          }
        }
      }
    }

    // If any filter groups were applied, restrict the main query
    // to the intersected node IDs.
    if (is_array($matching_nids)) {
      if (!empty($matching_nids)) {
        $query->condition('nid', array_values($matching_nids), 'IN');
      }
      else {
        // If the intersection is empty, no node can match all filters.
        // Return query as no results.
        $query->condition('nid', 0, '=');
      }
    }
  }

  /**
   * Filters an array of terms alphabetically by the first letter(s).
   */
  private function filterTermsAlphabetically(array $terms, array $letters): array {
    // If no letters are specified, we just return the original list.
    if (empty($letters)) {
      return $terms;
    }

    // Filter by first letter of each terms name.
    return array_filter($terms, function ($term) use ($letters) {
      // Get first letter as capital.
      $first_letter = strtoupper(substr($term->name, 0, 1));
      // Check and return if it matches those found in query params.
      return in_array($first_letter, $letters);
    });
  }

  /**
   * Parses query parameters from the request.
   */
  private function parseRequestParams(Request $request): array {
    // Whitelist of allowed filter types.
    $allowed_filter_types = ['alphabetical', 'hierarchical'];

    // Get query params from the request.
    $filter_type = $request->query->get('filter_type', 'alphabetical');

    // Check if the provided filter_type is allowed. Otherwise default
    // to alphabetical.
    if (!in_array($filter_type, $allowed_filter_types, TRUE)) {
      $filter_type = 'alphabetical';
    }

    // Get all letter query params and turn them to capital.
    $letters = $request->query->all('letter');
    $letters = array_map('strtoupper', array_map('trim', $letters));

    // Return only those letters that match a single uppercase letter A-Z.
    $letters = array_filter($letters, function ($value) {
      return preg_match('/^[A-ZÄÖÅ]$/u', $value);
    });

    return [
      'filter_type' => $filter_type,
      'letters' => $letters,
    ];
  }

}
