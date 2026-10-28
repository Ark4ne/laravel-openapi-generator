<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Strategies;

use Ark4ne\OpenApi\Description\FieldDescription;
use Ark4ne\OpenApi\Description\ParameterDescription;

/**
 * Turns validated fields into query parameters: flat (`filter[][name]`), last level kept as an array, or
 * `deepObject` (`query` of a document configuration: `all`, `last`, `none`).
 */
interface QueryStrategy
{
    /**
     * @param list<FieldDescription> $fields
     *
     * @return list<ParameterDescription>
     */
    public function parameters(array $fields): array;
}
