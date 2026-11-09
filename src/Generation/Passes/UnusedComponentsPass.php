<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Generation\Passes;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\DocumentDescription;
use Ark4ne\OpenApi\Generation\Pass;
use Ark4ne\OpenApi\Generation\SchemaWalker;
use Ark4ne\OpenApiWriter\Contracts\SchemaContract;

/**
 * The component schemas no operation, webhook, path parameter or shared response references any more, directly or
 * through another component, removed: those of the operations a variant does not have. Runs after every other pass but
 * the cleanup, so that the references an application's pass adds count. Only in a document with `variants`: a document
 * without keeps every component, as in 1.1.
 */
final readonly class UnusedComponentsPass implements Pass
{
    public function process(DocumentDescription $document, GenerationContext $context): DocumentDescription
    {
        if ($context->document->variants === null || $document->schemas === []) {
            return $document;
        }

        /** @var array<string, true> $used */
        $used = [];
        $pending = [];
        $collect = static function (SchemaContract $schema) use (&$used, &$pending): void {
            $name = SchemaWalker::component($schema);

            if ($name !== null && ! isset($used[$name])) {
                $used[$name] = true;
                $pending[] = $name;
            }
        };

        SchemaWalker::visit($document->schemas([]), $collect);

        while (($name = array_pop($pending)) !== null) {
            if (isset($document->schemas[$name])) {
                SchemaWalker::visitSchema($document->schemas[$name], $collect);
            }
        }

        return $document->schemas(array_intersect_key($document->schemas, $used));
    }
}
