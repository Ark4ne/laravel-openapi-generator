<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Integrations\JsonApiV2;

use Ark4ne\JsonApi\Configuration\JsonApiObject;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApiWriter\Objects\Schema;

/**
 * The top-level `jsonapi` member the package adds to its documents (`document.jsonapi`): the component `JsonApi`,
 * `version`, and `ext`, `profile` and `meta` when configured.
 */
final class JsonApiObjects
{
    public const COMPONENT = 'JsonApi';

    /**
     * `null` when the documents have no `jsonapi` member.
     */
    public static function schema(?JsonApiObject $jsonapi, GenerationContext $context): ?Schema
    {
        if ($jsonapi === null) {
            return null;
        }

        $members = ['version' => Schema::string()->const($jsonapi->version)];

        if ($jsonapi->ext !== []) {
            $members['ext'] = Schema::array()->items(Schema::string()->format('uri'));
        }

        if ($jsonapi->profile !== []) {
            $members['profile'] = Schema::array()->items(Schema::string()->format('uri'));
        }

        if ($jsonapi->meta !== []) {
            $members['meta'] = Schema::object();
        }

        $properties = [];

        foreach ($members as $name => $member) {
            $properties[] = $member->objectId($name);
        }

        $schema = Schema::object()->properties(...$properties)->required(...array_keys($members));
        $name = $context->components->nameOf(self::class) ?? $context->components->register(self::class, self::COMPONENT, $schema);

        return $context->document->useReferences ? Schema::ref($name) : $schema;
    }
}
