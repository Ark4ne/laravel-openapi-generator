<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Exceptions;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Context\Stage;
use Ark4ne\OpenApi\Description\ResponseDescription;
use Ark4ne\OpenApi\Description\ResponseOrigin;
use Ark4ne\OpenApi\Extraction\OperationContext;
use Ark4ne\OpenApi\Extraction\Responses\DeclaredResponses;
use Ark4ne\OpenApi\Types\NamedType;

/**
 * The responses of the exceptions of `@throws`, their description naming the exception (`Not Found
 * (ModelNotFoundException)`). An exception no mapper knows gives an info and no response: no 500 is invented.
 */
final readonly class ExceptionResponses
{
    public function __construct(
        private ExceptionMappers $mappers,
    ) {}

    /**
     * @return list<ResponseDescription>
     */
    public function describe(OperationContext $operation, GenerationContext $context): array
    {
        $responses = [];

        foreach ($operation->throws as $type) {
            if (! $type instanceof NamedType || ! class_exists($type->name)) {
                continue;
            }

            $response = $this->mappers->map($type->name, $context);

            if ($response === null) {
                $context->diagnostics->info(Stage::Response, "no response known for {$type->name}", $operation->route->label());

                continue;
            }

            $basename = substr($type->name, (int) strrpos('\\' . $type->name, '\\'));
            $responses[] = $response
                ->description(sprintf('%s (%s)', $response->description ?? DeclaredResponses::phrase($response->status), $basename))
                ->origin(ResponseOrigin::Exception);
        }

        return $responses;
    }
}
