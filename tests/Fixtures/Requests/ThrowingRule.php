<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Requests;

use Ark4ne\OpenApi\Description\FieldDescription;
use Ark4ne\OpenApi\Extraction\Rules\DescribesRule;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use RuntimeException;

/**
 * An application rule whose description fails.
 */
final class ThrowingRule implements ValidationRule, DescribesRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void {}

    public function describe(FieldDescription $field): FieldDescription
    {
        throw new RuntimeException('cannot describe');
    }
}
