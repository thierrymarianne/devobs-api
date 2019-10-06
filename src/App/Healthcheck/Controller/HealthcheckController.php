<?php

namespace App\Healthcheck\Controller;

use App\Security\Cors\CorsHeadersAwareTrait;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class HealthcheckController
{
    use CorsHeadersAwareTrait;

    public function areServicesHealthy(Request $request)
    {
        if ($request->isMethod('OPTIONS')) {
            return $this->getCorsOptionsResponse(
                $this->environment,
                $this->allowedOrigin
            );
        }

        return new JsonResponse(
            [],
            200,
            $this->getAccessControlOriginHeaders($this->environment, $this->allowedOrigin)
        );

    }
}