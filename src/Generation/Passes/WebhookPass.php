<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Generation\Passes;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Context\Stage;
use Ark4ne\OpenApi\Description\DocumentDescription;
use Ark4ne\OpenApi\Description\ParameterDescription;
use Ark4ne\OpenApi\Discovery\RouteDefinition;
use Ark4ne\OpenApi\Generation\Pass;
use Ark4ne\OpenApiWriter\Enums\ParameterLocation;

/**
 * The webhooks as requests the application sends: the path parameters and the servers of their route are left out
 * (the route is not what is called), and a name given twice to one method keeps its first operation, with a warning.
 * The methods of one name are written in a single Path Item.
 */
final readonly class WebhookPass implements Pass
{
    public function process(DocumentDescription $document, GenerationContext $context): DocumentDescription
    {
        /** @var array<string, string|null> $seen name and method → route of the first operation */
        $seen = [];
        $webhooks = [];

        foreach ($document->webhooks as $webhook) {
            $name = $webhook->webhook ?? $webhook->path;
            $label = RouteDefinition::labelOf($webhook->method, $name);

            if (array_key_exists($label, $seen)) {
                $by = $seen[$label] === null ? '' : " by {$seen[$label]}";
                $context->diagnostics->warning(Stage::Pass, "the webhook {$name} is already sent with this method{$by}, operation left out", $webhook->route);

                continue;
            }

            $seen[$label] = $webhook->route;
            $parameters = array_filter($webhook->request->parameters, static fn(ParameterDescription $parameter): bool => $parameter->in !== ParameterLocation::Path);
            $webhooks[] = $webhook->servers()->request($webhook->request->parameters(...$parameters));
        }

        return $document->webhooks(...$webhooks);
    }
}
