<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Generation;

use Ark4ne\OpenApi\Extraction\Exceptions\ErrorBodies;
use Ark4ne\OpenApi\Extraction\Exceptions\LaravelErrorBodies;
use Ark4ne\OpenApi\Generation\Middlewares\AuthMiddlewareMapper;
use Ark4ne\OpenApi\Generation\Middlewares\BasicAuthMiddlewareMapper;
use Ark4ne\OpenApi\Generation\Middlewares\CsrfMiddlewareMapper;
use Ark4ne\OpenApi\Generation\Middlewares\PassportScopesMiddlewareMapper;
use Ark4ne\OpenApi\Generation\Middlewares\SanctumAbilitiesMiddlewareMapper;
use Ark4ne\OpenApi\Generation\Middlewares\SignedMiddlewareMapper;

/**
 * The mappers of each middleware alias. `middlewares` of the configuration replaces the mappers of an alias.
 */
final class MiddlewareMappers
{
    /**
     * @param array<string, list<MiddlewareMapper>> $mappers alias → mappers, applied in order
     */
    private function __construct(
        private readonly array $mappers,
    ) {}

    public static function of(MiddlewareMapper ...$mappers): self
    {
        $indexed = [];

        foreach ($mappers as $mapper) {
            foreach ($mapper->handles() as $alias) {
                $indexed[$alias][] = $mapper;
            }
        }

        return new self($indexed);
    }

    public static function default(ErrorBodies $errors = new LaravelErrorBodies()): self
    {
        return self::of(
            new AuthMiddlewareMapper(),
            new BasicAuthMiddlewareMapper(),
            new CsrfMiddlewareMapper($errors),
            new SignedMiddlewareMapper($errors),
            new PassportScopesMiddlewareMapper(),
            new SanctumAbilitiesMiddlewareMapper(),
        );
    }

    /**
     * These mappers, the mappers of an alias (or a middleware class) replaced.
     */
    public function with(string $middleware, MiddlewareMapper ...$mappers): self
    {
        return new self([...$this->mappers, Middleware::parse($middleware)->name => array_values($mappers)]);
    }

    /**
     * @return list<MiddlewareMapper>
     */
    public function for(Middleware $middleware): array
    {
        return $this->mappers[$middleware->name] ?? [];
    }
}
