<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Rules;

use Ark4ne\OpenApi\Description\FieldDescription;

/**
 * Implemented by a validation rule of the application to describe itself, without a RuleMapper.
 */
interface DescribesRule
{
    public function describe(FieldDescription $field): FieldDescription;
}
