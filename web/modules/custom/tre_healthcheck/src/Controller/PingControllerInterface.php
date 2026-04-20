<?php

namespace Drupal\tre_healthcheck\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Controller for responding to healthcheck ping requests.
 */
interface PingControllerInterface {

    /**
     * Responds to a healthcheck ping request.
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     *   A JSON-formatted response object.
     */
    public function response() : JsonResponse;

}
