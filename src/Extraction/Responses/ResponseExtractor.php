<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Responses;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\ResponseDescription;
use Ark4ne\OpenApi\Extraction\OperationContext;
use Ark4ne\OpenApi\Types\Type;

/**
 * Describes the responses of one returned type. The first supporting extractor, by decreasing priority, is used.
 *
 * An integration specializing a generic type has a higher priority than the generic extractor
 * (JsonApiResource before JsonResource).
 */
interface ResponseExtractor
{
    public function priority(): int;

    public function supports(Type $type, OperationContext $operation): bool;

    /**
     * @return list<ResponseDescription>
     */
    public function extract(Type $type, OperationContext $operation, GenerationContext $context): array;
}
