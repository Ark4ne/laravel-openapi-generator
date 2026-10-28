<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Exceptions;

use Ark4ne\OpenApi\Attributes\Response;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\ResponseDescription;
use Ark4ne\OpenApi\Extraction\Responses\DeclaredResponses;
use ReflectionClass;

/**
 * `#[Response]` on the exception class or on a parent class, read as on an action (resource, type, headers, summary,
 * example); without body, the error body.
 */
final readonly class AttributeExceptionMapper implements ExceptionMapper
{
    public const PRIORITY = 500;

    public function __construct(
        private DeclaredResponses $declared,
    ) {}

    public function priority(): int
    {
        return self::PRIORITY;
    }

    public function supports(string $exception, GenerationContext $context): bool
    {
        return self::attribute($exception) !== null;
    }

    public function map(string $exception, GenerationContext $context): ?ResponseDescription
    {
        $found = self::attribute($exception);

        if ($found === null) {
            return null;
        }

        [$attribute, $class] = $found;

        if (! is_int($attribute->status)) {
            return null;
        }

        $response = $this->declared->response($attribute, $class, $context);

        return $response->contents === [] ? $response->content('application/json', ErrorSchemas::error($context)) : $response;
    }

    /**
     * @return array{Response, ReflectionClass<object>}|null
     */
    private static function attribute(string $exception): ?array
    {
        if (! class_exists($exception)) {
            return null;
        }

        for ($class = new ReflectionClass($exception); $class !== false; $class = $class->getParentClass()) {
            $attribute = $class->getAttributes(Response::class)[0] ?? null;

            if ($attribute !== null) {
                return [$attribute->newInstance(), $class];
            }
        }

        return null;
    }
}
