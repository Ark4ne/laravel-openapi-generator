<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Generation\Middlewares;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\DocumentDescription;
use Ark4ne\OpenApi\Description\OperationDescription;
use Ark4ne\OpenApi\Description\SecurityDescription;
use Ark4ne\OpenApi\Generation\Middleware;
use Ark4ne\OpenApi\Generation\MiddlewareMapper;
use Ark4ne\OpenApiWriter\Objects\OAuthFlow;
use Ark4ne\OpenApiWriter\Objects\OAuthFlows;
use Ark4ne\OpenApiWriter\Objects\SecurityScheme;

/**
 * `auth` and its guards, each one an alternative requirement:
 *
 * - `sanctum`: a bearer token (`sanctum`), or the session cookie of a SPA with its XSRF header (`session` + `xsrf`);
 * - `api`: Passport (`passport`, OAuth2 authorization code on `/oauth/authorize` and `/oauth/token`);
 * - no guard: a bearer token (`bearer`); another guard: a bearer token named after it.
 *
 * `security_schemes` of the configuration replaces a scheme of the same name.
 */
final readonly class AuthMiddlewareMapper implements MiddlewareMapper
{
    public function handles(): array
    {
        return ['auth'];
    }

    public function map(Middleware $middleware, OperationDescription $operation, DocumentDescription $document, GenerationContext $context): array
    {
        foreach ($middleware->parameters === [] ? [null] : $middleware->parameters as $guard) {
            [$requirements, $schemes] = self::guard($guard);

            foreach ($schemes as $name => $scheme) {
                $document = isset($document->securitySchemes[$name]) ? $document : $document->securityScheme($name, $scheme);
            }

            $operation = $operation->addSecurity(...$requirements);
        }

        return [$operation, $document];
    }

    /**
     * @return array{list<SecurityDescription>, array<string, SecurityScheme>}
     */
    private static function guard(?string $guard): array
    {
        return match ($guard) {
            'sanctum' => [
                [SecurityDescription::create(['sanctum' => []]), SecurityDescription::create(['session' => [], 'xsrf' => []])],
                [
                    'sanctum' => SecurityScheme::http('sanctum')->scheme('bearer'),
                    'session' => SecurityScheme::apiKey('session')->name('laravel_session')->in('cookie'),
                    'xsrf' => SecurityScheme::apiKey('xsrf')->name('X-XSRF-TOKEN')->in('header'),
                ],
            ],
            'api' => [
                [SecurityDescription::create(['passport' => []])],
                ['passport' => SecurityScheme::oauth2('passport')->flows(OAuthFlows::create()->authorizationCode(OAuthFlow::create()->authorizationUrl('/oauth/authorize')->tokenUrl('/oauth/token')->scopes([])))],
            ],
            null => [[SecurityDescription::create(['bearer' => []])], ['bearer' => SecurityScheme::http('bearer')->scheme('bearer')]],
            default => [[SecurityDescription::create([$guard => []])], [$guard => SecurityScheme::http($guard)->scheme('bearer')]],
        };
    }
}
