<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Generation\Passes;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\DocumentDescription;
use Ark4ne\OpenApi\Description\HeaderDescription;
use Ark4ne\OpenApi\Extraction\Exceptions\ErrorBodies;
use Ark4ne\OpenApi\Generation\Middleware;
use Ark4ne\OpenApi\Generation\Pass;
use Ark4ne\OpenApi\Generation\SharedResponses;
use Ark4ne\OpenApiWriter\Objects\Schema;

/**
 * 429 on every throttled operation (`throttle`), with the headers of the rate limiter.
 */
final readonly class ThrottleResponsePass implements Pass
{
    public function __construct(
        private ErrorBodies $errors,
    ) {}

    public function process(DocumentDescription $document, GenerationContext $context): DocumentDescription
    {
        $operations = [];
        $headers = [
            HeaderDescription::create('X-RateLimit-Limit', Schema::integer())->description('Requests allowed in the window.'),
            HeaderDescription::create('X-RateLimit-Remaining', Schema::integer())->description('Requests left in the window.'),
            HeaderDescription::create('Retry-After', Schema::integer())->description('Seconds before a new request is allowed.'),
            HeaderDescription::create('X-RateLimit-Reset', Schema::integer())->description('Unix timestamp of the end of the window.'),
        ];

        foreach ($document->operations as $operation) {
            foreach ($operation->middlewares as $middleware) {
                if (Middleware::parse($middleware)->name === 'throttle') {
                    [$operation, $document] = SharedResponses::add($operation, $document, 'TooManyRequests', 429, 'Too Many Requests', $this->errors->error($context), $headers, mediaType: $this->errors->mediaType());
                }
            }

            $operations[] = $operation;
        }

        return $document->operations(...$operations);
    }
}
