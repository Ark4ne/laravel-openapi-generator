<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Rules;

/**
 * Rule mappers indexed by the rules they handle; a mapper registered later replaces an earlier one.
 */
final class RuleMappers
{
    /** @var list<RuleMapper> */
    private readonly array $mappers;

    public function __construct(RuleMapper ...$mappers)
    {
        $this->mappers = array_values($mappers);
    }

    /**
     * @param string $rule rule name or rule class (a parent class or an interface matches too)
     */
    public function for(string $rule): ?RuleMapper
    {
        return null;
    }

    /**
     * @return list<RuleMapper> in registration order
     */
    public function all(): array
    {
        return $this->mappers;
    }
}
