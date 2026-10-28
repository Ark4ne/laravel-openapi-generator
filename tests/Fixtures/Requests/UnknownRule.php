<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Requests;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * An application rule the generator knows nothing about.
 */
final class UnknownRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void {}
}
