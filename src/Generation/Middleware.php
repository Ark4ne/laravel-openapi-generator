<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Generation;

/**
 * A middleware of a route, as the mappers read it: its alias (the classes of Laravel, Sanctum and Passport are
 * brought back to it) and its arguments (`throttle:60,1` → `throttle`, `['60', '1']`).
 */
final readonly class Middleware
{
    /**
     * Middleware classes and the alias Laravel registers for them.
     */
    public const ALIASES = [
        'Illuminate\Auth\Middleware\Authenticate' => 'auth',
        'Illuminate\Auth\Middleware\AuthenticateWithBasicAuth' => 'auth.basic',
        'Illuminate\Auth\Middleware\Authorize' => 'can',
        'Illuminate\Routing\Middleware\ThrottleRequests' => 'throttle',
        'Illuminate\Routing\Middleware\ThrottleRequestsWithRedis' => 'throttle',
        'Illuminate\Routing\Middleware\ValidateSignature' => 'signed',
        'Illuminate\Foundation\Http\Middleware\ValidateCsrfToken' => 'csrf',
        'Illuminate\Foundation\Http\Middleware\VerifyCsrfToken' => 'csrf',
        'Laravel\Sanctum\Http\Middleware\CheckAbilities' => 'abilities',
        'Laravel\Sanctum\Http\Middleware\CheckForAnyAbility' => 'ability',
        'Laravel\Passport\Http\Middleware\CheckScopes' => 'scopes',
        'Laravel\Passport\Http\Middleware\CheckForAnyScope' => 'scope',
        'Laravel\Passport\Http\Middleware\CheckToken' => 'scopes',
        'Laravel\Passport\Http\Middleware\CheckTokenForAnyScope' => 'scope',
    ];

    /**
     * @param list<string> $parameters
     */
    public function __construct(
        public string $name,
        public array $parameters = [],
        public string $declared = '',
    ) {}

    public static function parse(string $declared): self
    {
        $parts = explode(':', $declared, 2);
        $name = ltrim($parts[0], '\\');
        $parameters = isset($parts[1]) && $parts[1] !== '' ? explode(',', $parts[1]) : [];

        return new self(self::ALIASES[$name] ?? $name, $parameters, $declared);
    }
}
