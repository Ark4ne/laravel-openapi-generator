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

    /** @var array<string, RuleMapper> rule name or class → mapper */
    private readonly array $index;

    /** @var array<string, RuleMapper|null> rule → mapper found, parents and interfaces included */
    private array $found = [];

    public function __construct(RuleMapper ...$mappers)
    {
        $this->mappers = array_values($mappers);
        $index = [];

        foreach ($this->mappers as $mapper) {
            foreach ($mapper->handles() as $rule) {
                $index[$rule] = $mapper;
            }
        }

        $this->index = $index;
    }

    /**
     * Every built-in mapper.
     */
    public static function default(): self
    {
        return new self(
            new Mappers\RequiredRuleMapper(),
            new Mappers\ConditionalRequiredRuleMapper(),
            new Mappers\RequiredArrayKeysRuleMapper(),
            new Mappers\NullableRuleMapper(),
            new Mappers\SometimesRuleMapper(),
            new Mappers\PresentRuleMapper(),
            new Mappers\FilledRuleMapper(),
            new Mappers\ForbiddenRuleMapper(),
            new Mappers\NoEffectRuleMapper(),
            new Mappers\StringRuleMapper(),
            new Mappers\IntegerRuleMapper(),
            new Mappers\NumericRuleMapper(),
            new Mappers\DecimalRuleMapper(),
            new Mappers\BooleanRuleMapper(),
            new Mappers\ArrayRuleMapper(),
            new Mappers\JsonRuleMapper(),
            new Mappers\FileRuleMapper(),
            new Mappers\DateRuleMapper(),
            new Mappers\AcceptedRuleMapper(),
            new Mappers\EmailRuleMapper(),
            new Mappers\UrlRuleMapper(),
            new Mappers\IdentifierRuleMapper(),
            new Mappers\IpRuleMapper(),
            new Mappers\PatternRuleMapper(),
            new Mappers\DateFormatRuleMapper(),
            new Mappers\CharactersRuleMapper(),
            new Mappers\RegexRuleMapper(),
            new Mappers\AffixRuleMapper(),
            new Mappers\SizeRuleMapper(),
            new Mappers\ComparisonRuleMapper(),
            new Mappers\DigitsRuleMapper(),
            new Mappers\MultipleOfRuleMapper(),
            new Mappers\DateComparisonRuleMapper(),
            new Mappers\InRuleMapper(),
            new Mappers\EnumRuleMapper(),
            new Mappers\DistinctRuleMapper(),
            new Mappers\DatabaseRuleMapper(),
            new Mappers\ContainsRuleMapper(),
            new Mappers\MimesRuleMapper(),
            new Mappers\DimensionsRuleMapper(),
            new Mappers\FileObjectRuleMapper(),
            new Mappers\PasswordRuleMapper(),
            new Mappers\FieldReferenceRuleMapper(),
        );
    }

    /**
     * These mappers with others, registered after them: they replace the built-in ones for their rules.
     */
    public function with(RuleMapper ...$mappers): self
    {
        return new self(...$this->mappers, ...$mappers);
    }

    /**
     * @param string $rule rule name or rule class (a parent class or an interface matches too)
     */
    public function for(string $rule): ?RuleMapper
    {
        if (isset($this->index[$rule])) {
            return $this->index[$rule];
        }

        if (array_key_exists($rule, $this->found)) {
            return $this->found[$rule];
        }

        $found = null;

        if (class_exists($rule)) {
            foreach ($this->index as $handled => $mapper) {
                if (is_a($rule, $handled, true)) {
                    $found = $mapper;
                }
            }
        }

        return $this->found[$rule] = $found;
    }

    /**
     * @return list<RuleMapper> in registration order
     */
    public function all(): array
    {
        return $this->mappers;
    }
}
