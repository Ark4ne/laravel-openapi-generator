<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Generation\Passes;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\DocumentDescription;
use Ark4ne\OpenApi\Description\OperationDescription;
use Ark4ne\OpenApi\Description\ParameterDescription;
use Ark4ne\OpenApi\Generation\Pass;
use Ark4ne\OpenApiWriter\Enums\ParameterLocation;
use Ark4ne\OpenApiWriter\Objects\Schema;

/**
 * The header that selects the variant (`documents.<name>.variants.header`), on every operation of a document with
 * variants: the name of its variant in a variant document (`const`, `enum` of one value in 3.0), every variant name in
 * the head. Never required: a client without the header gets the default variant of the application. A header of the
 * same name (case-insensitive) already on the operation is kept as declared. Webhooks and callbacks are requests the
 * application sends: they get none.
 */
final readonly class VariantHeaderPass implements Pass
{
    public function process(DocumentDescription $document, GenerationContext $context): DocumentDescription
    {
        $variants = $context->document->variants;
        $name = $variants?->header;

        if ($variants === null || $name === null) {
            return $document;
        }

        $schema = $context->variant === null
            ? Schema::string()->enum(...array_map(static fn($variant): string => $variant->name, $variants->values))
            : Schema::string()->const($context->variant->name)->example($context->variant->name);
        $header = ParameterDescription::header($name, $schema);

        return $document->operations(...array_map(static fn(OperationDescription $operation): OperationDescription => self::declares($operation, $name)
            ? $operation
            : $operation->request($operation->request->addParameters($header)), $document->operations));
    }

    private static function declares(OperationDescription $operation, string $name): bool
    {
        foreach ($operation->request->parameters as $parameter) {
            if ($parameter->in === ParameterLocation::Header && strcasecmp($parameter->name, $name) === 0) {
                return true;
            }
        }

        return false;
    }
}
