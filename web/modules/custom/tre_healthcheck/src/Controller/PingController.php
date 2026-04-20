<?php

namespace Drupal\tre_healthcheck\Controller;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Healthcheck ping reply controller.
 *
 * Responds to ping requests.
 */
class PingController extends ControllerBase implements PingControllerInterface {

  /** 
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * The cache backend service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected CacheBackendInterface $cache;

  /**
   * The tre_healthcheck.settings configuration object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $settings;

  /**
   * Constructs a new PingController instance.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Drupal's configuration factory service.
   */
  public function __construct(RequestStack $request_stack, CacheBackendInterface $cache_backend, ConfigFactoryInterface $config_factory) {
    $this->requestStack = $request_stack;
    $this->cache = $cache_backend;
    $this->settings = $config_factory->get('tre_healthcheck.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('cache.default'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function response() : JsonResponse {
    $enabled = $this->settings->get('enabled') ?? FALSE;
    $valid_tokens = $this->getAccessTokens();
    $request = $this->requestStack->getCurrentRequest();
    $token = $request->headers->get('Token') ?? $request->query->get('token') ?? NULL;
    // N.B. Drupal needs to be properly configured for Varnish for this to work
    // with the X-Forwarded-For header.
    $ip = $request->getClientIp();
    $cache_key = 'tre_healthcheck:flood:' . $ip;
    $fail_count = $this->cache->get($cache_key) ? $this->cache->get($cache_key)->data : 0;

    if (!$enabled) {
      $response = new JsonResponse(
        [
          'error' => 'Healthcheck service unavailable.',
        ],
        503
      );
      $this->preventCaching($response);
      return $response;
    }

    // If the access token is missing or invalid, abort early. If the client
    // continues to send requests without a token (or with an invalid one),
    // block them for 10 minutes.
    if (empty($token) || !in_array($token, $valid_tokens, TRUE)) {
      $this->cache->set($cache_key, $fail_count + 1, time() + 600);

      if ($fail_count >= 5) {
        $response = new JsonResponse(
          [
            'error' => 'Request blocked. Too many unauthorized requests.',
          ],
          403
        );
        $this->preventCaching($response);
        return $response;
      }

      $response = new JsonResponse(
        [
          'error' => 'Unauthorized. Access token missing or invalid.',
        ],
        401
      );
      $this->preventCaching($response);
      return $response;
    }

    // Authentication succeeded. Clear any flood control data for this IP.
    if ($fail_count > 0) {
      $this->cache->delete($cache_key);
    }

    $response = new JsonResponse(
      [
        'status' => 'OK',
      ],
      200
    );
    $this->preventCaching($response);
    return $response;
  }

  /**
   * Helper function for retrieving access tokens defined for this instance.
   *
   * @return array
   *   Array of valid access tokens.
   */
  private function getAccessTokens() : array {
    $tokens = $this->settings->get('ping_auth_tokens') ?? [];

    return array_filter($tokens);
  }

  /**
   * Prevents caching of the response.
   *
   * @param \Symfony\Component\HttpFoundation\JsonResponse $response
   *   The response object to configure.
   */
  private function preventCaching(JsonResponse $response) : void {
    $response->setMaxAge(0);
    $response->setSharedMaxAge(0);
    $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
    $response->headers->set('Pragma', 'no-cache');
    $response->headers->set('Expires', '0');
  }

}
