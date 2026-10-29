<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Extensions;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\ParameterDescription;
use Ark4ne\OpenApi\Description\RequestDescription;
use Ark4ne\OpenApi\Extraction\OperationContext;
use Ark4ne\OpenApi\Extraction\Requests\RequestExtractor;

/**
 * Adds the header `X-Stamp` to every request.
 */
final readonly class StampRequestExtractor implements RequestExtractor
{
    public function priority(): int
    {
        return 1000;
    }

    public function supports(OperationContext $operation): bool
    {
        return true;
    }

    public function extract(OperationContext $operation, RequestDescription $request, GenerationContext $context): RequestDescription
    {
        return $request->addParameters(ParameterDescription::header('X-Stamp'));
    }
}
