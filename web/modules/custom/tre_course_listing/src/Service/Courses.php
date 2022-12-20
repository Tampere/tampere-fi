<?php

namespace Drupal\tre_course_listing\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Language\LanguageManager;
use Drupal\Core\Site\Settings;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Header;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Courses service for Hellewi API courses.
 */
class Courses {

  /**
   * The available API domains keyed by paragraph type.
   */
  const HELLEWI_API_DOMAINS = [
    'course_listing' => 'opistopalvelut.fi',
    'course_listing_sports' => 'kuntapalvelut.fi',
  ];

  /**
   * The API tenant.
   */
  const HELLEWI_API_TENANT = 'tampere';

  /**
   * The API version.
   */
  const HELLEWI_API_VERSION = 'v1';

  /**
   * The subject for the JWT payload.
   */
  const HELLEWI_API_JWT_SUB = 'hellewi-api-' . self::HELLEWI_API_VERSION;

  /**
   * The cid to use for caching Hellewi responses.
   */
  const TRE_COURSE_LISTING_RESPONSE_CACHE_ID = 'tre_course_listing_response';

  /**
   * The HTTP client service.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * The Language Manager service.
   *
   * @var \Drupal\Core\Language\LanguageManager
   */
  protected $languageManager;

  /**
   * The Datetime Time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $timeService;

  /**
   * Cache for courses.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected CacheBackendInterface $cache;

  /**
   * Courses constructor.
   */
  final public function __construct(
    LanguageManager $language_manager,
    TimeInterface $time_service,
    Client $http_client,
    CacheBackendInterface $course_cache
  ) {
    $this->languageManager = $language_manager;
    $this->timeService = $time_service;
    $this->httpClient = $http_client;
    $this->cache = $course_cache;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('language_manager'),
      $container->get('datetime.time'),
      $container->get('http_client'),
      $container->get('cache.tre_course_listing_courses'),
    );
  }

  /**
   * Gets course details from API.
   *
   * @return array
   *   Course items as an array.
   *
   * @throws \Exception
   */
  public function getCourses($paragraph_id, $paragraph_bundle): array {
    $lang_code = $this->languageManager->getCurrentLanguage()->getId();

    // The response data varies by bundle and language but no other data of the
    // paragraph affects the response.
    $response_cid = self::TRE_COURSE_LISTING_RESPONSE_CACHE_ID . '__' . $paragraph_bundle . '__' . $lang_code;
    $cached = $this->cache->get($response_cid);

    if (isset($cached->data)) {
      return $cached->data;
    }

    $secret = Settings::get('hellewi_api_secret');
    $kid = Settings::get('hellewi_api_kid');

    $header = json_encode([
      'alg' => 'HS256',
      'typ' => 'JWT',
      'kid' => $kid,
    ]);

    // Creating a random string for the "jti" (JWT ID) claim. This does not
    // need to be cryptographically secure, but there should be a negligible
    // probability that the same value will be accidentally assigned to a
    // different data object.
    $current_time = $this->timeService->getCurrentTime();
    $random_bytes = bin2hex(random_bytes(10));
    $jti = "{$paragraph_id}{$current_time}{$random_bytes}";

    $token_lifetime_minutes = Settings::get('tre_course_listing_token_lifetime_minutes', 5);
    $token_duration_in_seconds = $token_lifetime_minutes * 60;
    $payload = json_encode([
      'iat' => $current_time,
      'exp' => $current_time + $token_duration_in_seconds,
      'sub' => self::HELLEWI_API_JWT_SUB,
      'tenant' => self::getTenantIdentifier($paragraph_bundle),
      'jti' => $jti,
    ]);

    // Encode header and payload to Base64Url string.
    $base64UrlHeader = self::encodeBase64Url($header);
    $base64UrlPayload = self::encodeBase64Url($payload);

    // Create signature hash.
    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $secret, TRUE);

    // Encode signature to Base64Url string.
    $base64UrlSignature = self::encodeBase64Url($signature);

    $jwt = $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;

    $client_options = [
      'method' => 'GET',
      'headers' => [
        'Content-Type' => 'application/json',
        'Authorization' => 'Bearer ' . $jwt,
      ],
    ];

    $url_base = self::getUrl($paragraph_bundle, $lang_code, 'courses');
    $page_parameter = '?page=1';
    $results_per_page_parameter = '&limit=100';
    $url = $url_base . $page_parameter . $results_per_page_parameter;

    try {
      $response = $this->httpClient->get($url, $client_options);
      $response_data = (string) $response->getBody();
      $first_page_results = json_decode($response_data, TRUE);
      $all_course_data[] = $first_page_results;

      // '$response->hasHeader('Link')' was returning TRUE
      // even when '$response->getHeader('Link')' would return an empty array.
      if ($header_links = $response->getHeader('Link')) {
        $parsed_links = Header::parse($header_links);
        $last_link = end($parsed_links);

        if ($last_link !== FALSE) {
          $last_page_link = $last_link[0];

          $matched = preg_match('/page=(\d*)/', $last_page_link, $matches);
          // preg_match() returns 1 if the pattern matches, 0 if it does not,
          // or false if an error occurred.
          if ($matched === 1) {
            $last_page_number = $matches[1];

            for ($page_to_fetch = 2; $page_to_fetch <= $last_page_number; $page_to_fetch++) {
              $url = $url_base . '?page=' . $page_to_fetch . $results_per_page_parameter;
              $paged_response = $this->httpClient->get($url, $client_options);
              $paged_response_data = (string) $paged_response->getBody();
              $all_course_data[] = json_decode($paged_response_data, TRUE);
            }
          }
        }
      }

      $tidy_course_data = array_merge(...$all_course_data);

      // Collect course IDs, and add course page URL to course data.
      $course_ids = [];
      foreach ($tidy_course_data as $key => $course_data) {
        $course_id = $course_data['id'];
        $course_ids[] = $course_id;
        $tidy_course_data[$key]['courseUrl'] = self::getCoursePageUrl($paragraph_bundle, $lang_code, $course_id);
      }

      // Have to access the 'course-participants' end point to get information
      // on the course space availability.
      $course_participants_url_base = self::getUrl($paragraph_bundle, $lang_code, 'course-participants') . '?ids=';

      $course_participation_data = [];
      // The maximum length of a URL (recommendation is 2000 characters) can
      // become a limitation here. Multiple requests used for fetching the
      // participation information.
      $course_id_chunks = array_chunk($course_ids, 100);
      foreach ($course_id_chunks as $course_id_chunk) {
        $url = $course_participants_url_base . implode('&ids=', $course_id_chunk);
        $response = $this->httpClient->get($url, $client_options);
        $response_data = (string) $response->getBody();
        $result = json_decode($response_data, TRUE);
        $course_participation_data[] = $result;
      }

      $tidy_course_participation_data = array_merge(...$course_participation_data);

      $course_data_with_participation_data = [];
      // Go through the merged general course data and course participation
      // data, and create a union for data in rows related by their ID.
      // See https://stackoverflow.com/a/50388449
      foreach (array_merge($tidy_course_data, $tidy_course_participation_data) as $row) {
        $row_id = $row['id'];
        $course_data_with_participation_data[$row_id] = ($course_data_with_participation_data[$row_id] ?? []) + $row;
      }

      $data = [...$course_data_with_participation_data];
      // We cache the response for the token duration to try to ensure that we
      // don't hit the rate limit in the API.
      $cache_expiry_coefficient = Settings::get('tre_course_listing_cache_expiry_in_token_lifetimes', 1);
      $cache_expiry_time = $current_time + $token_duration_in_seconds * $cache_expiry_coefficient;
      $this->cache->set($response_cid, $data, $cache_expiry_time);
      return $data;
    }
    catch (RequestException $e) {
      watchdog_exception('tre_course_listing', $e);
    }

    return [];
  }

  /**
   * Encodes the given string with base64Url.
   *
   * @param string $string
   *   The string to encode.
   *
   * @return string
   *   The encoded data as a string.
   */
  private function encodeBase64Url($string): string {
    return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($string));
  }

  /**
   * Returns a URL for the given course ID as a string.
   *
   * @param string $paragraph_bundle
   *   The machine name for the listing paragraph.
   * @param string $lang_code
   *   The language code to use. Supports fi/en/sv.
   * @param string $id
   *   The ID for the course.
   *
   * @return string
   *   The course page URL as a string.
   */
  private function getCoursePageUrl($paragraph_bundle, $lang_code, $id): string {
    $domain = self::HELLEWI_API_DOMAINS[$paragraph_bundle];
    $tenant = self::HELLEWI_API_TENANT;

    return "https://uusi.{$domain}/{$tenant}/{$lang_code}/course/{$id}";
  }

  /**
   * Returns a tenant identifier for the JWT payload as a string.
   *
   * This is in format <tenant>.<domain>, e.g. for 'opistopalvelut.fi/demo'
   * the tenant is 'demo.opistopalvelut.fi'.
   *
   * @param string $paragraph_bundle
   *   The machine name for the listing paragraph.
   *
   * @return string
   *   The tenant identifier as a string.
   */
  private function getTenantIdentifier($paragraph_bundle) {
    return self::HELLEWI_API_TENANT . "." . self::HELLEWI_API_DOMAINS[$paragraph_bundle];
  }

  /**
   * Returns a URL for the API request as a string.
   *
   * @param string $paragraph_bundle
   *   The machine name for the listing paragraph.
   * @param string $lang_code
   *   The language code to use. Supports fi/en/sv.
   * @param string $endpoint
   *   The API endpoint.
   *
   * @return string
   *   The URL for the API request as a string.
   */
  private function getUrl($paragraph_bundle, $lang_code, $endpoint): string {
    $domain = self::HELLEWI_API_DOMAINS[$paragraph_bundle];
    $version = self::HELLEWI_API_VERSION;
    $tenant = self::HELLEWI_API_TENANT;

    return "https://api.{$domain}/$version/$tenant/$lang_code/$endpoint";
  }

}
