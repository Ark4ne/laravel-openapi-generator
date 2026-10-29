<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Exceptions;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\ResponseDescription;
use Ark4ne\OpenApi\Extraction\Responses\DeclaredResponses;
use Ark4ne\OpenApi\Extraction\Responses\Paginations;
use Ark4ne\OpenApiWriter\Objects\Schema;
use Illuminate\Validation\ValidationException;

/**
 * The JSON bodies of Laravel's errors: `Error` (`message`) and `ValidationError` (`message`, `errors`), shared
 * components, the bodies of LaravelErrorBodies.
 */
final class ErrorSchemas
{
    public const ERROR = 'Error';

    public const VALIDATION = 'ValidationError';

    /**
     * A response of this status with the error body, or the validation body (`ValidationException` only).
     */
    public static function response(ErrorBodies $errors, int $status, ?string $description, GenerationContext $context, bool $validation = false): ResponseDescription
    {
        return ResponseDescription::create($status)
            ->description($description ?? DeclaredResponses::phrase($status))
            ->content($errors->mediaType(), $validation ? $errors->validation($context) : $errors->error($context));
    }

    public static function error(GenerationContext $context): Schema
    {
        return self::component(self::class, self::ERROR, Paginations::object(['message' => Schema::string()]), $context);
    }

    public static function validation(GenerationContext $context): Schema
    {
        $errors = Schema::object()->additionalProperties(Schema::array()->items(Schema::string()));

        return self::component(ValidationException::class, self::VALIDATION, Paginations::object(['message' => Schema::string(), 'errors' => $errors]), $context);
    }

    /**
     * @param class-string $source
     */
    private static function component(string $source, string $name, Schema $schema, GenerationContext $context): Schema
    {
        $name = $context->components->nameOf($source) ?? $context->components->register($source, $name, $schema);

        return $context->document->useReferences ? Schema::ref($name) : $schema;
    }
}
