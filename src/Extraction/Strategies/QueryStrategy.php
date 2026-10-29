<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Strategies;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\FieldDescription;
use Ark4ne\OpenApi\Description\ParameterDescription;

/**
 * Turns validated fields into query parameters (`query` of a document configuration): every level flattened
 * (`filter[][name][]`), the last list kept as an array (`filter[][name]`), objects as `deepObject`, or the whole
 * query as one `querystring` parameter (3.2).
 */
interface QueryStrategy
{
    /**
     * @param list<FieldDescription> $fields
     *
     * @return list<ParameterDescription>
     */
    public function parameters(array $fields, GenerationContext $context): array;
}
