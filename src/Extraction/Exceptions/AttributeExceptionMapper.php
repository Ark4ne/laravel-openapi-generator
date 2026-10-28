<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Exceptions;

use Ark4ne\OpenApi\Attributes\Response;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\ResponseDescription;
use Ark4ne\OpenApi\Sampling\TypeSchemas;
use Ark4ne\OpenApi\Types\TypeResolver;
use ReflectionClass;

/**
 * `#[Response]` on the exception class or on a parent class: its status, its description, its `type` as body (the
 * error body otherwise).
 */
final readonly class AttributeExceptionMapper implements ExceptionMapper
{
    public const PRIORITY = 500;

    public function __construct(
        private TypeResolver $types,
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
        $status = $attribute->status;

        if (! is_int($status)) {
            return null;
        }

        $response = ErrorSchemas::response($status, $attribute->description, $context);

        return $attribute->type === null ? $response : $response->content($attribute->mediaType ?? 'application/json', TypeSchemas::of($this->types->parse($attribute->type, $class)));
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
