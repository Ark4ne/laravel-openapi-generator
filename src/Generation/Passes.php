<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Generation;

use Ark4ne\OpenApi\Configuration\Configuration;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\DocumentDescription;
use Ark4ne\OpenApi\Exceptions\InvalidConfigurationException;

/**
 * The passes, in their explicit order: exception responses → security → error responses → tags and groups →
 * operationId → components → path parameters → cleanup.
 */
final class Passes
{
    /** @var list<Pass> */
    private readonly array $passes;

    public function __construct(Pass ...$passes)
    {
        $this->passes = array_values($passes);
    }

    /**
     * @return list<Pass> in order
     */
    public function all(): array
    {
        return $this->passes;
    }

    public function process(DocumentDescription $document, GenerationContext $context): DocumentDescription
    {
        foreach ($this->passes as $pass) {
            $document = $pass->process($document, $context);
        }

        return $document;
    }

    /**
     * The built-in chain; `middlewares` of the configuration replace the mappers of their middleware.
     */
    public static function default(?Configuration $configuration = null, ErrorBodies $errors = new LaravelErrorBodies()): self
    {
        $mappers = MiddlewareMappers::default($errors);

        foreach ($configuration->middlewares ?? [] as $middleware => $classes) {
            $mappers = $mappers->with($middleware, ...array_map(static fn(string $class): MiddlewareMapper => self::mapper($class), $classes));
        }

        return new self(
            new Passes\WebhookPass(),
            new Passes\CallbackResolutionPass(),
            new Passes\ThrownExceptionResponsePass(),
            new Passes\MiddlewareSecurityPass($mappers),
            new Passes\AuthorizationResponsePass($errors),
            new Passes\NotFoundResponsePass($errors),
            new Passes\ValidationErrorResponsePass($errors),
            new Passes\ThrottleResponsePass($errors),
            new Passes\TagPass(),
            new Passes\TagGroupPass(),
            new Passes\OperationIdPass(),
            new Passes\ComponentExtractionPass(),
            new Passes\EquivalentPathsPass(),
            new Passes\PathParametersPass(),
            new Passes\CleanupPass(),
        );
    }

    /**
     * @throws InvalidConfigurationException
     */
    private static function mapper(string $class): MiddlewareMapper
    {
        $mapper = class_exists($class) ? new $class() : null;

        return $mapper instanceof MiddlewareMapper ? $mapper : throw InvalidConfigurationException::forKey('middlewares', 'classes implementing ' . MiddlewareMapper::class, $class);
    }
}
