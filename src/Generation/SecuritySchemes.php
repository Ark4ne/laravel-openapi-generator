<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Generation;

use Ark4ne\OpenApiWriter\Objects\OAuthFlow;
use Ark4ne\OpenApiWriter\Objects\OAuthFlows;
use Ark4ne\OpenApiWriter\Objects\SecurityScheme;

/**
 * The security schemes of the configuration (`security_schemes`), written with the OpenAPI field names, as writer
 * objects.
 */
final class SecuritySchemes
{
    private const FLOWS = ['implicit', 'password', 'clientCredentials', 'authorizationCode', 'deviceAuthorization'];

    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(string $name, array $config): SecurityScheme
    {
        $scheme = match ($config['type'] ?? null) {
            'apiKey' => SecurityScheme::apiKey($name),
            'http' => SecurityScheme::http($name),
            'oauth2' => SecurityScheme::oauth2($name),
            'openIdConnect' => SecurityScheme::openIdConnect($name),
            default => SecurityScheme::mutualTLS($name),
        };

        $scheme = $scheme
            ->description(self::string($config, 'description'))
            ->name(self::string($config, 'name'))
            ->in(self::string($config, 'in'))
            ->scheme(self::string($config, 'scheme'))
            ->bearerFormat(self::string($config, 'bearerFormat'))
            ->openIdConnectUrl(self::string($config, 'openIdConnectUrl'))
            ->oauth2MetadataUrl(self::string($config, 'oauth2MetadataUrl'));

        if (($config['deprecated'] ?? null) === true) {
            $scheme = $scheme->deprecated();
        }

        $flows = $config['flows'] ?? null;

        return is_array($flows) ? $scheme->flows(self::flows($flows)) : $scheme;
    }

    /**
     * @param array<array-key, mixed> $config
     */
    private static function flows(array $config): OAuthFlows
    {
        $flows = OAuthFlows::create();

        foreach (self::FLOWS as $type) {
            $flow = $config[$type] ?? null;

            if (! is_array($flow)) {
                continue;
            }

            $scopes = [];

            foreach (is_array($flow['scopes'] ?? null) ? $flow['scopes'] : [] as $scope => $description) {
                $scopes[(string) $scope] = is_string($description) ? $description : (string) $scope;
            }

            $oauth = OAuthFlow::create()
                ->authorizationUrl(self::string($flow, 'authorizationUrl'))
                ->deviceAuthorizationUrl(self::string($flow, 'deviceAuthorizationUrl'))
                ->tokenUrl(self::string($flow, 'tokenUrl'))
                ->refreshUrl(self::string($flow, 'refreshUrl'))
                ->scopes($scopes);
            $flows = match ($type) {
                'implicit' => $flows->implicit($oauth),
                'password' => $flows->password($oauth),
                'clientCredentials' => $flows->clientCredentials($oauth),
                'authorizationCode' => $flows->authorizationCode($oauth),
                default => $flows->deviceAuthorization($oauth),
            };
        }

        return $flows;
    }

    /**
     * @param array<array-key, mixed> $config
     */
    private static function string(array $config, string $key): ?string
    {
        return is_string($config[$key] ?? null) ? $config[$key] : null;
    }
}
