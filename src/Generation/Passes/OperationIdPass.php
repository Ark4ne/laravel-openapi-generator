<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Generation\Passes;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Context\Stage;
use Ark4ne\OpenApi\Description\DocumentDescription;
use Ark4ne\OpenApi\Description\OperationDescription;
use Ark4ne\OpenApi\Extraction\Strategies\BuiltInOperationIdStrategy;
use Ark4ne\OpenApi\Generation\Pass;

/**
 * Unique operationIds: an operation without one gets its method and path (`getApiUsersByUser`); an identifier
 * already used gets a suffix (`users.index_2`) and a warning.
 */
final readonly class OperationIdPass implements Pass
{
    public function process(DocumentDescription $document, GenerationContext $context): DocumentDescription
    {
        $used = [];
        $rename = static function (OperationDescription $operation) use (&$used, $context): OperationDescription {
            $id = $operation->operationId ?? BuiltInOperationIdStrategy::methodPath($operation->method, $operation->path);
            $unique = $id;

            for ($suffix = 2; isset($used[$unique]); $suffix++) {
                $unique = "{$id}_{$suffix}";
            }

            if ($unique !== $id) {
                $context->diagnostics->warning(Stage::Pass, "operationId {$id} already used, renamed {$unique}", $operation->route);
            }

            $used[$unique] = true;

            return $operation->operationId($unique);
        };

        return $document->operations(...array_map($rename, $document->operations))->webhooks(...array_map($rename, $document->webhooks));
    }
}
