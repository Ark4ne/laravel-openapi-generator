<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Integrations\JsonApiV2;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Extraction\Exceptions\ErrorBodies;
use Ark4ne\OpenApi\Extraction\Responses\Paginations;
use Ark4ne\OpenApiWriter\Objects\Schema;

/**
 * The error documents the package renders (`JsonApi::renderErrors()`, `Http\Errors`): the component `JsonApiErrors`,
 * `jsonapi` as configured, `errors` a list of error objects whose members are all optional (`id`, `links`, `status`
 * as a string, `code`, `title`, `detail`, `source`, `meta`), and `meta`. A validation error points at its member with
 * `source.pointer`, or at its query parameter with `source.parameter`. The renderers of the application
 * (`errors.renderers`) are not read: their bodies are free.
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
            Schema::object()->properties(Schema::string()->objectId('about'), Schema::string()->objectId('type'))->objectId('links'),
            Schema::string()->objectId('status'),
            Schema::string()->objectId('code'),
            Schema::string()->objectId('title'),
            Schema::string()->objectId('detail'),
            Schema::object()->properties(Schema::string()->objectId('pointer'), Schema::string()->objectId('parameter'), Schema::string()->objectId('header'))->objectId('source'),
            Schema::object()->objectId('meta'),
        );
        $jsonapi = JsonApiObjects::schema(ResourceDefinitions::jsonApiObject(), $context);
        $errors = Paginations::object($jsonapi === null ? ['errors' => Schema::array()->items($error)] : ['jsonapi' => $jsonapi, 'errors' => Schema::array()->items($error)])
            ->addProperties(Schema::object()->objectId('meta'));
        $name = $context->components->nameOf(self::class) ?? $context->components->register(self::class, self::ERRORS, $errors);

        return $context->document->useReferences ? Schema::ref($name) : $errors;
    }

    public function validation(GenerationContext $context): Schema
    {
        return $this->error($context);
    }
}
