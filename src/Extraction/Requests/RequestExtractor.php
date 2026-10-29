<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Requests;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\RequestDescription;
use Ark4ne\OpenApi\Extraction\OperationContext;

/**
 * Describes what an operation receives (path parameters, form request, manual description, attributes).
 *
 * Every supporting extractor runs, by increasing priority, each one refining the description.
 */
interface RequestExtractor
{
    public function priority(): int;

    public function supports(OperationContext $operation): bool;

    public function extract(OperationContext $operation, RequestDescription $request, GenerationContext $context): RequestDescription;
}
