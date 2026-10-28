<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Integrations;

use Ark4ne\OpenApi\Generation\Integration;
use Ark4ne\OpenApi\Integrations\JsonApi\JsonApiIntegration;

/**
 * The integrations shipped with the generator, those whose package is installed.
 */
final class BuiltInIntegrations
{
    /**
     * @param bool $jsonApiErrors the error responses are JSON:API error documents
     *
     * @return list<Integration>
     */
    public static function available(bool $jsonApiErrors = false): array
    {
        $integrations = [];

        foreach ([new JsonApiIntegration($jsonApiErrors)] as $integration) {
            if ($integration->available()) {
                $integrations[] = $integration;
            }
        }

        return $integrations;
    }
}
