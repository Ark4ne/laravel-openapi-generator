<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Generation\Passes;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Context\Stage;
use Ark4ne\OpenApi\Description\CallbackDescription;
use Ark4ne\OpenApi\Description\DocumentDescription;
use Ark4ne\OpenApi\Description\OperationDescription;
use Ark4ne\OpenApi\Discovery\RouteDefinition;
use Ark4ne\OpenApi\Generation\Pass;

/**
 * Fills the callbacks that reuse a webhook, once every route has been extracted: one request per method of the
 * webhook, with its payload and its responses, under the expression of the callback and without operationId (an
 * operationId is unique in the document). A webhook that does not exist leaves the callback out with a warning.
 *
 * A callback name, expression and method given twice keeps its first request, with a warning.
 */
final readonly class CallbackResolutionPass implements Pass
{
    public function process(DocumentDescription $document, GenerationContext $context): DocumentDescription
    {
        /** @var array<string, list<OperationDescription>> $webhooks */
        $webhooks = [];

        foreach ($document->webhooks as $webhook) {
            $webhooks[$webhook->webhook ?? $webhook->path][] = $webhook;
        }

        $resolve = static fn(OperationDescription $operation): OperationDescription => self::resolve($operation, $webhooks, $context);

        return $document->operations(...array_map($resolve, $document->operations))->webhooks(...array_map($resolve, $document->webhooks));
    }

    /**
     * @param array<string, list<OperationDescription>> $webhooks
     */
    private static function resolve(OperationDescription $operation, array $webhooks, GenerationContext $context): OperationDescription
    {
        if ($operation->callbacks === []) {
            return $operation;
        }

        $callbacks = [];
        $seen = [];

        foreach ($operation->callbacks as $callback) {
            foreach (self::requests($callback, $webhooks, $operation, $context) as $request) {
                $key = "{$request->name} " . RouteDefinition::labelOf($request->operation->method ?? '', $request->expression);

                if (isset($seen[$key])) {
                    $context->diagnostics->warning(Stage::Pass, "the callback {$key} is declared twice, first one kept", $operation->route);

                    continue;
                }

                $seen[$key] = true;
                $callbacks[] = $request;
            }
        }

        return $operation->callbacks(...$callbacks);
    }

    /**
     * @param array<string, list<OperationDescription>> $webhooks
     *
     * @return list<CallbackDescription>
     */
    private static function requests(CallbackDescription $callback, array $webhooks, OperationDescription $operation, GenerationContext $context): array
    {
        if ($callback->webhook === null || $callback->operation !== null) {
            return [$callback];
        }

        if (! isset($webhooks[$callback->webhook])) {
            $context->diagnostics->warning(Stage::Pass, "the callback {$callback->name} reuses the webhook {$callback->webhook}, which does not exist: callback left out", $operation->route);

            return [];
        }

        return array_map(
            static fn(OperationDescription $webhook): CallbackDescription => $callback->operation($webhook->path($callback->expression)->webhook(null)->operationId(null)),
            $webhooks[$callback->webhook],
        );
    }
}
