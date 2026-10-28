<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Rules;

/**
 * One rule applied to one field: `max:255` gives the name `max` and the parameters `['255']`.
 */
final readonly class RuleInvocation
{
    /**
     * @param string $name snake case rule name, or the class of a rule object
     * @param list<string> $parameters
     * @param object|null $rule the rule object (`Rule::enum()`, `Password`, a custom rule)
     * @param array<string, list<RuleInvocation>> $siblings rules of the other fields, for the rules that refer to them
     */
    public function __construct(
        public string $field,
        public string $name,
        public array $parameters = [],
        public ?object $rule = null,
        public array $siblings = [],
    ) {}
}
