<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Integrations\JsonApi;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Extraction\Responses\Paginations;
use Ark4ne\OpenApi\Generation\ErrorBodies;
use Ark4ne\OpenApiWriter\Objects\Schema;

/**
 * JSON:API error documents: the component `JsonApiErrors`, `errors` a list of error objects (`status`, `title`,
 * `detail`…), a validation error pointing at its member with `source.pointer`.
 */
final readonly class JsonApiErrorBodies implements ErrorBodies
{
    public const ERRORS = 'JsonApiErrors';

    public function mediaType(): string
    {
        return JsonApiResourceFormat::MEDIA_TYPE;
    }

    public function error(GenerationContext $context): Schema
    {
        $error = Schema::object()->properties(
            Schema::string()->objectId('id'),
            Schema::string()->objectId('status'),
            Schema::string()->objectId('code'),
            Schema::string()->objectId('title'),
            Schema::string()->objectId('detail'),
            Schema::object()->properties(Schema::string()->objectId('pointer'), Schema::string()->objectId('parameter'), Schema::string()->objectId('header'))->objectId('source'),
            Schema::object()->objectId('meta'),
        );
        $errors = Paginations::object(['errors' => Schema::array()->items($error)]);
        $name = $context->components->nameOf(self::class) ?? $context->components->register(self::class, self::ERRORS, $errors);

        return $context->document->useReferences ? Schema::ref($name) : $errors;
    }

    public function validation(GenerationContext $context): Schema
    {
        return $this->error($context);
    }
}
