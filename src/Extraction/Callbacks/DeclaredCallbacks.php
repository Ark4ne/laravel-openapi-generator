<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Callbacks;

use Ark4ne\OpenApi\Attributes\Callback;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Context\Stage;
use Ark4ne\OpenApi\Description\BodyDescription;
use Ark4ne\OpenApi\Description\CallbackDescription;
use Ark4ne\OpenApi\Description\OperationDescription;
use Ark4ne\OpenApi\Description\RequestDescription;
use Ark4ne\OpenApi\Description\ResponseDescription;
use Ark4ne\OpenApi\Discovery\RouteDefinition;
use Ark4ne\OpenApi\Extraction\OperationContext;
use Ark4ne\OpenApi\Extraction\Responses\DeclaredResponses;

/**
 * The `#[Callback]` of the action: the request sent to the URL of its runtime expression, described by a resource
 * (as Laravel writes it) or a PHPDoc type, and the response expected from the receiver (200 by default).
 *
 * A callback reusing a webhook carries its name only: `CallbackResolutionPass` fills it once every route has been
 * extracted, with the methods, the payload and the responses of the webhook.
 */
final readonly class DeclaredCallbacks
{
    public function __construct(
        private DeclaredResponses $responses,
    ) {}

    /**
     * @return list<CallbackDescription>
     */
    public function describe(OperationContext $operation, GenerationContext $context): array
    {
        $callbacks = [];

        foreach ($operation->declaredActionAttributes(Callback::class, $context) as [$attribute, $declaration]) {
            if ($attribute->webhook !== null) {
                $callbacks[] = CallbackDescription::fromWebhook($attribute->name, $attribute->url, $attribute->webhook);

                continue;
            }

            $request = RequestDescription::create();
            $payload = $this->responses->body($attribute->resource, $attribute->type, $declaration, $context);

            if ($payload === null && $attribute->resource !== null) {
                $context->diagnostics->warning(Stage::Response, "#[Callback] {$attribute->name}: {$attribute->resource} is not a resource, request written without body", $operation->route->label());
            } elseif ($payload !== null) {
                $request = $request->body(BodyDescription::create()->content($attribute->mediaType ?? $this->responses->mediaType($attribute->resource), $payload)->required());
            }

            $status = $attribute->status ?? 200;
            $sent = OperationDescription::create(RouteDefinition::method($attribute->method), $attribute->url)
                ->description($attribute->description)
                ->request($request)
                ->responses(ResponseDescription::create($status)->description(DeclaredResponses::phrase($status)));
            $callbacks[] = CallbackDescription::create($attribute->name, $attribute->url, $sent);
        }

        return $callbacks;
    }
}
