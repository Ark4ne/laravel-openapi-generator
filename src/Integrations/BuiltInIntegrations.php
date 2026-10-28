<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Integrations;

use Ark4ne\OpenApi\Generation\Integration;
use Ark4ne\OpenApi\Integrations\JsonApi\JsonApiIntegration;

/**
 * The integrations shipped with the generator; the generator keeps those whose package is installed.
 */
final class BuiltInIntegrations
{
    /**
     * @param bool $jsonApiErrors the error responses are JSON:API error documents
     *
     * @return list<Integration>
     */
    public static function all(bool $jsonApiErrors = false): array
    {
        return [new JsonApiIntegration($jsonApiErrors)];
    }
}
