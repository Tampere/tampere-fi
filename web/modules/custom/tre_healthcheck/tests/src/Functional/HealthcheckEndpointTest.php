<?php

namespace Drupal\Tests\tre_healthcheck\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the healthcheck endpoint functionality.
 *
 * @group tre_healthcheck
 */
class HealthcheckEndpointTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['tre_healthcheck'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
  }

  /**
   * Tests that a valid token returns a successful response.
   */
  public function testValidTokenReturnsSuccess() {
    $token = 'test-valid-token-12345';
    $this->setHealthcheckConfig(TRUE, [$token]);

    $this->drupalGet("/healthcheck?token={$token}");
    $this->assertSession()->statusCodeEquals(200);
    
    $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertEquals('OK', $response['status']);
  }

  /**
   * Tests that an invalid token returns unauthorized.
   */
  public function testInvalidTokenReturnsUnauthorized() {
    $this->setHealthcheckConfig(TRUE, ['valid-token']);

    $this->drupalGet('/healthcheck?token=invalid-token');
    $this->assertSession()->statusCodeEquals(401);
    
    $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertStringContainsString('Unauthorized', $response['error']);
  }

  /**
   * Tests that a missing token returns unauthorized.
   */
  public function testMissingTokenReturnsUnauthorized() {
    $this->setHealthcheckConfig(TRUE, ['valid-token']);

    $this->drupalGet('/healthcheck');
    $this->assertSession()->statusCodeEquals(401);
    
    $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertStringContainsString('Unauthorized', $response['error']);
  }

  /**
   * Tests that multiple valid tokens all work.
   */
  public function testMultipleValidTokensWork() {
    $tokens = [
      'first-valid-token',
      'second-valid-token',
      'third-valid-token',
    ];
    $this->setHealthcheckConfig(TRUE, $tokens);

    foreach ($tokens as $token) {
      $this->drupalGet("/healthcheck?token={$token}");
      $this->assertSession()->statusCodeEquals(200);
      
      $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
      $this->assertEquals('OK', $response['status']);
    }
  }

  /**
   * Tests that empty tokens in configuration are filtered out.
   */
  public function testEmptyTokensFiltered() {
    // Configure with empty strings mixed in.
    $config = $this->config('tre_healthcheck.settings');
    $config->set('enabled', TRUE);
    $config->set('ping_auth_tokens', ['valid-token', '', '  ', 'another-valid']);
    $config->save();

    // Empty tokens should not grant access.
    $this->drupalGet('/healthcheck?token=');
    $this->assertSession()->statusCodeEquals(401);

    $this->drupalGet('/healthcheck?token=%20%20');
    $this->assertSession()->statusCodeEquals(401);

    // Valid tokens should work.
    $this->drupalGet('/healthcheck?token=valid-token');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests that flood protection tracks failed attempts.
   */
  public function testFloodProtectionTracksFailures() {
    $this->setHealthcheckConfig(TRUE, ['valid-token']);

    // Make 5 failed requests - all should return 401 (not blocked yet).
    for ($i = 0; $i < 5; $i++) {
      $this->drupalGet('/healthcheck?token=invalid');
      $this->assertSession()->statusCodeEquals(401);
    }
  }

  /**
   * Tests that a valid token works even after flood protection failures.
   */
  public function testValidTokenWorksAfterFloodFailures() {
    $valid_token = 'valid-token-12345';
    $this->setHealthcheckConfig(TRUE, [$valid_token]);

    // Make 5 failed requests to trigger flood tracking.
    for ($i = 0; $i < 5; $i++) {
      $this->drupalGet('/healthcheck?token=invalid');
      $this->assertSession()->statusCodeEquals(401);
    }

    // Valid token should still work (not blocked).
    $this->drupalGet("/healthcheck?token={$valid_token}");
    $this->assertSession()->statusCodeEquals(200);
    
    $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertEquals('OK', $response['status']);
  }

  /**
   * Tests that a successful request clears the flood counter.
   */
  public function testValidTokenClearsFloodCounter() {
    $valid_token = 'valid-token-12345';
    $this->setHealthcheckConfig(TRUE, [$valid_token]);

    // Make 3 failed requests.
    for ($i = 0; $i < 3; $i++) {
      $this->drupalGet('/healthcheck?token=invalid');
      $this->assertSession()->statusCodeEquals(401);
    }

    // Make a successful request (should clear counter).
    $this->drupalGet("/healthcheck?token={$valid_token}");
    $this->assertSession()->statusCodeEquals(200);

    // Make 3 more failed requests (counter was reset, so total is 3, not 6).
    for ($i = 0; $i < 3; $i++) {
      $this->drupalGet('/healthcheck?token=invalid');
      $this->assertSession()->statusCodeEquals(401);
    }

    // Should still not be blocked since counter was reset.
    $this->drupalGet("/healthcheck?token={$valid_token}");
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests that a disabled endpoint returns 503.
   */
  public function testDisabledEndpointReturns503() {
    $this->setHealthcheckConfig(FALSE, ['valid-token']);

    $this->drupalGet('/healthcheck?token=valid-token');
    $this->assertSession()->statusCodeEquals(503);
    
    $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertStringContainsString('unavailable', $response['error']);
  }

  /**
   * Tests that responses include proper no-cache headers.
   */
  public function testResponsesNotCached() {
    $this->setHealthcheckConfig(TRUE, ['valid-token']);

    $this->drupalGet('/healthcheck?token=valid-token');
    
    $session = $this->getSession();
    $headers = $session->getResponseHeaders();
    
    // Check for no-cache headers.
    $cache_control = $headers['Cache-Control'][0] ?? '';
    $this->assertStringContainsString('no-cache', $cache_control);
    $this->assertStringContainsString('no-store', $cache_control);
    $this->assertStringContainsString('must-revalidate', $cache_control);
    
    $this->assertArrayHasKey('Pragma', $headers);
    $this->assertEquals('no-cache', $headers['Pragma'][0]);
    
    $this->assertArrayHasKey('Expires', $headers);
    $this->assertEquals('0', $headers['Expires'][0]);
  }

  /**
   * Tests that responses are not cached even on error responses.
   */
  public function testErrorResponsesNotCached() {
    $this->setHealthcheckConfig(TRUE, ['valid-token']);

    // Test 401 response.
    $this->drupalGet('/healthcheck?token=invalid');
    $this->assertSession()->statusCodeEquals(401);
    
    $session = $this->getSession();
    $headers = $session->getResponseHeaders();
    $cache_control = $headers['Cache-Control'][0] ?? '';
    $this->assertStringContainsString('no-cache', $cache_control);

    // Test 503 response.
    $this->setHealthcheckConfig(FALSE, ['valid-token']);
    $this->drupalGet('/healthcheck?token=valid-token');
    $this->assertSession()->statusCodeEquals(503);
    
    $headers = $this->getSession()->getResponseHeaders();
    $cache_control = $headers['Cache-Control'][0] ?? '';
    $this->assertStringContainsString('no-cache', $cache_control);
  }

  /**
   * Tests that token can be provided via Token header.
   */
  public function testTokenViaHeader() {
    $token = 'header-token-12345';
    $this->setHealthcheckConfig(TRUE, [$token]);

    // Set the token in the request header.
    $this->getSession()->setRequestHeader('Token', $token);
    $this->drupalGet('/healthcheck');
    $this->assertSession()->statusCodeEquals(200);
    
    $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertEquals('OK', $response['status']);
  }

  /**
   * Tests that header token takes precedence over query parameter.
   */
  public function testHeaderTakesPrecedenceOverQuery() {
    $header_token = 'valid-header-token';
    $query_token = 'invalid-query-token';
    $this->setHealthcheckConfig(TRUE, [$header_token]);

    // Set valid token in header but invalid in query parameter.
    $this->getSession()->setRequestHeader('Token', $header_token);
    $this->drupalGet("/healthcheck?token={$query_token}");
    
    // Should succeed using the header token.
    $this->assertSession()->statusCodeEquals(200);
    
    $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertEquals('OK', $response['status']);
  }

  /**
   * Helper method to configure the healthcheck module.
   *
   * @param bool $enabled
   *   Whether the healthcheck endpoint is enabled.
   * @param array $tokens
   *   Array of valid access tokens.
   */
  protected function setHealthcheckConfig(bool $enabled, array $tokens): void {
    $this->config('tre_healthcheck.settings')
      ->set('enabled', $enabled)
      ->set('ping_auth_tokens', $tokens)
      ->save();
  }

}
