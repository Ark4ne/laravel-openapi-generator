<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Integrations;

use Ark4ne\OpenApi\Generation\Integration;
use Ark4ne\OpenApi\Integrations\JsonApi\JsonApiIntegration;
use Ark4ne\OpenApi\Integrations\JsonApiV2\JsonApiV2Integration;
use Ark4ne\OpenApi\Integrations\JsonApiV3\JsonApiV3Integration;
use Illuminate\Contracts\Container\Container;

/**
 * The integrations shipped with the generator; the generator keeps those whose package is installed: one of the
 * JSON:API integrations, for `ark4ne/laravel-json-api` 1.x, 2.x or 3.x.
 */
final class BuiltInIntegrations
{
    /**
     * @param bool $jsonApiErrors the error responses are JSON:API error documents
     * @param Container|null $container the application's, for what an integration resolves from it (the check of the
     *                                  resources of `ark4ne/laravel-json-api` 3.x)
     *
     * @return list<Integration>
     */
    public static function all(bool $jsonApiErrors = false, ?Container $container = null): array
    {
        return [new JsonApiIntegration($jsonApiErrors), new JsonApiV2Integration($jsonApiErrors), new JsonApiV3Integration($jsonApiErrors, $container)];
    }
}
