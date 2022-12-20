<?php

namespace Drupal\tre_openid_connect_windows_aad_tampere_opetus\Controller;

use Drupal\Core\Controller\ControllerBase;
use Psr\Log\LoggerInterface;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Drupal\openid_connect\OpenIDConnectAuthmap;

/**
 * Controller routines for Azure AD single sign out user routes.
 */
class WindowsAadSSOController extends ControllerBase {

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /*
   * @param \Drupal\openid_connect\OpenIDConnectAuthmap $authmap
   *   The authmap storage.
   */
  protected $authmap;

  /**
   * Constructs a WindowsAadSSOController object.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   */
  public function __construct(LoggerInterface $logger, OpenIDConnectAuthmap $authmap) {
    $this->logger = $logger;
    $this->authmap = $authmap;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('logger.factory')->get('tre_openid_connect_windows_aad_tampere_opetus'),
      $container->get('openid_connect.authmap')
    );
  }

  /**
   * Single Sign Out callback to log the current user out.
   *
   * Called by Windows Azure AD when a user logs out of their SSO session from
   * another application such as Office 365.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Either a 200 or 403 response without any content.
   */
  public function signout() {
    $configuration = $this->config('openid_connect.settings.tampere_opetus');
    $settings = $configuration->get('settings');
    $enabled = $configuration->get('enabled');
    // Check that the Windows AAD client is enabled and so is SSOut.
    if ($enabled && isset($settings['enable_single_sign_out']) && $settings['enable_single_sign_out']) {
      // Ensure the user has a connected account.
      $user = \Drupal::currentUser();
      $connected_accounts = $this->authmap->getConnectedAccounts($user);
      $connected = ($connected_accounts && isset($connected_accounts['tampere_opetus']));
      $logged_in = $user->isAuthenticated();
      // Only log the user out if they are logged in and have a connected
      // account. Return a 200 OK in any case since all is good.
      if ($logged_in && $connected) {
        user_logout();
      }
      return new Response('', Response::HTTP_OK);
    }
    // Likely a misconfiguration since SSOut attempts should not be made to the
    // logout uri unless it has been configured in Azure AD; if you had
    // configured it in Azure AD then you should have also enabled SSOut in the
    // OpenID Connect settings. Also, a possible malicious CSRF attempt. Log a
    // warning either way.
    $this->logger->warning('Windows AAD Single Sign Out attempt, but SSOut has not been enabled in the OpenID Connect Windows AAD configuration.');
    return new Response('', Response::HTTP_FORBIDDEN);
  }

  /**
   * Logs the current user out. Overrides UserController::logout().
   *
   * If Single Sign out has been enabled in OpenID Connect Windows AAD config
   * then redirect the user when they try to log out of the app to the Windows
   * single sign out endpoint. They will be logged out of their other SSO apps.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirection to either the home page or to Azure AD Single Sign out.
   */
  public function logout() {
    $connected = FALSE;
    $configuration = $this->config('openid_connect.settings.tampere_opetus');
    $settings = $configuration->get('settings');
    // Check that the Windows AAD client is enabled and so is SSOut.
    $enabled = (($configuration->get('enabled')) && isset($settings['enable_single_sign_out']) && $settings['enable_single_sign_out']);

    // Check for a connected account before we log the Drupal user out.
    if ($enabled) {
      // Ensure the user has a connected account.
      $user = \Drupal::currentUser();
      $connected_accounts = $this->authmap->getConnectedAccounts($user);
      $connected = ($connected_accounts && isset($connected_accounts['tampere_opetus']));
    }

    user_logout();
    if ($connected) {
      // Redirect back to the home page once signed out.
      $redirect_uri = Url::fromRoute('<front>', [], ['absolute' => TRUE])->toString(TRUE)->getGeneratedUrl();
      $query_parameters = [
        'post_logout_redirect_uri' => $redirect_uri,
      ];
      $query = UrlHelper::buildQuery($query_parameters);

      $response = new TrustedRedirectResponse('https://login.microsoftonline.com/common/oauth2/v2.0/logout?' . $query);
      // We can't cache the response, since we need the user to get logged out
      // prior to being redirected. The kill switch will prevent the page
      // getting cached when page cache is active.
      \Drupal::service('page_cache_kill_switch')->trigger();
      return $response;
    }
    // No SSOut so do the usual thing and redirect to the front page.
    return $this->redirect('<front>');
  }
}
