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
 * through another component (a `$ref` or a discriminator mapping), removed: those of the operations a pass removed.
 * Runs after every other pass but the cleanup, so that the references an application's pass adds count.
 */
final readonly class UnusedComponentsPass implements Pass
{
    public function process(DocumentDescription $document, GenerationContext $context): DocumentDescription
    {
        if ($document->schemas === []) {
            return $document;
        }

        /** @var array<string, true> $used */
        $used = [];
        $pending = [];
        $collect = static function (SchemaContract $schema) use (&$used, &$pending): void {
            foreach ([SchemaWalker::component($schema), ...SchemaWalker::discriminated($schema)] as $name) {
                if ($name !== null && ! isset($used[$name])) {
                    $used[$name] = true;
                    $pending[] = $name;
                }
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
