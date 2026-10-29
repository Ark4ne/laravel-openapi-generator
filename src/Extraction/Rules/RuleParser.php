<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Rules;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Context\Stage;
use Closure;
use Illuminate\Contracts\Validation\InvokableRule;
use Illuminate\Contracts\Validation\Rule as RuleContract;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\ConditionalRules;
use Illuminate\Validation\Rules\ArrayRule;
use Illuminate\Validation\Rules\Date;
use Illuminate\Validation\Rules\ExcludeIf;
use Illuminate\Validation\Rules\Numeric;
use Illuminate\Validation\Rules\ProhibitedIf;
use Illuminate\Validation\Rules\RequiredIf;
use Illuminate\Validation\ValidationRuleParser;
use Stringable;

/**
 * Normalises the validation rules of a request into one list of RuleInvocation per field.
 *
 * - Strings (`required|max:255`), lists, rule objects; `Stringable` rule objects (`Rule::in()`, `Rule::unique()`…)
 *   are read as their string, as Laravel reads them; `Rule::date()`, `Rule::numeric()`, `Rule::array()` are split.
 * - `Rule::when()`: the rules of both branches, marked conditional; `Rule::requiredIf()`, `Rule::prohibitedIf()`,
 *   `Rule::excludeIf()`: their rule, conditional.
 * - Closures: left out with an info.
 * - `confirmed` adds the confirmation field (`password_confirmation`, or its parameter) with the other rules.
 * - Order: type rules first, then the constraints in their order, then `sometimes`.
 */
final class RuleParser
{
    /**
     * Rules that set the type of a field, applied before the constraints that depend on it (`max`, `size`…).
     */
    public const TYPES = [
        'string', 'integer', 'numeric', 'decimal', 'boolean', 'array', 'list', 'json', 'file', 'image', 'date', 'accepted',
        'declined', 'Illuminate\Validation\Rules\Enum', 'Illuminate\Validation\Rules\File', 'Illuminate\Validation\Rules\ImageFile',
        'Illuminate\Validation\Rules\Password', 'Illuminate\Validation\Rules\Email', 'Illuminate\Validation\Rules\AnyOf',
    ];

    /**
     * Rules applied last, because they change what the other rules set.
     */
    public const LAST = ['sometimes'];

    /**
     * Rule objects that hold a condition (boolean or callback) and stand for one rule.
     */
    private const CONDITIONAL_OBJECTS = [RequiredIf::class => 'required', ProhibitedIf::class => 'prohibited', ExcludeIf::class => 'exclude'];

    /**
     * Stringable rule objects that stand for several rules joined by `|`.
     */
    private const COMPOSITE_OBJECTS = [ArrayRule::class, Date::class, Numeric::class, 'Illuminate\Validation\Rules\StringRule'];

    private const ALIASES = ['int' => 'integer', 'bool' => 'boolean'];

    /**
     * @param array<array-key, mixed> $rules field → its rules, as `rules()` returns them
     * @param string $label route label for the diagnostics
     *
     * @return array<string, list<RuleInvocation>> field → rules, in the order of the fields
     */
    public function parse(array $rules, GenerationContext $context, string $label): array
    {
        $fields = [];

        foreach ($rules as $field => $fieldRules) {
            $field = (string) $field;
            $invocations = $this->invocations($field, $fieldRules, false, $context, $label);
            $fields[$field] = self::sort($invocations);

            foreach ($invocations as $invocation) {
                if ($invocation->name === 'confirmed') {
                    $confirmation = $invocation->parameters[0] ?? "{$field}_confirmation";
                    $fields[$confirmation] = self::confirmation($confirmation, $fields[$field]);
                }
            }
        }

        return $fields;
    }

    /**
     * @return list<RuleInvocation>
     */
    private function invocations(string $field, mixed $rules, bool $conditional, GenerationContext $context, string $label): array
    {
        if (is_string($rules)) {
            $rules = explode('|', $rules);
        }

        if (! is_array($rules)) {
            $rules = [$rules];
        }

        $invocations = [];

        foreach ($rules as $rule) {
            array_push($invocations, ...$this->rule($field, $rule, $conditional, $context, $label));
        }

        return $invocations;
    }

    /**
     * @return list<RuleInvocation>
     */
    private function rule(string $field, mixed $rule, bool $conditional, GenerationContext $context, string $label): array
    {
        if ($rule instanceof Closure) {
            $context->diagnostics->info(Stage::Request, "closure rule on {$field} ignored", $label);

            return [];
        }

        if ($rule instanceof ConditionalRules) {
            return [
                ...$this->invocations($field, $rule->rules(), true, $context, $label),
                ...$this->invocations($field, $rule->defaultRules(), true, $context, $label),
            ];
        }

        if (is_object($rule)) {
            return $this->object($field, $rule, $conditional, $context, $label);
        }

        return is_scalar($rule) ? $this->string($field, (string) $rule, $conditional) : [];
    }

    /**
     * A rule object: conditional (`Rule::requiredIf()`), read as its string like Laravel (`Rule::in()`), or kept.
     *
     * @return list<RuleInvocation>
     */
    private function object(string $field, object $rule, bool $conditional, GenerationContext $context, string $label): array
    {
        if (isset(self::CONDITIONAL_OBJECTS[$rule::class])) {
            return [new RuleInvocation($field, self::CONDITIONAL_OBJECTS[$rule::class], [], $rule, true)];
        }

        $kept = $rule instanceof RuleContract || $rule instanceof ValidationRule || $rule instanceof InvokableRule;

        if ($kept || ! $rule instanceof Stringable) {
            return [new RuleInvocation($field, $rule::class, [], $rule, $conditional)];
        }

        return in_array($rule::class, self::COMPOSITE_OBJECTS, true)
            ? $this->invocations($field, (string) $rule, $conditional, $context, $label)
            : $this->string($field, (string) $rule, $conditional);
    }

    /**
     * `max:255` → `max` with `['255']`.
     *
     * @return list<RuleInvocation>
     */
    private function string(string $field, string $rule, bool $conditional): array
    {
        $rule = trim($rule);

        if ($rule === '') {
            return [];
        }

        $name = strtolower(trim(explode(':', $rule, 2)[0]));
        $parameters = str_contains($rule, ':') ? ValidationRuleParser::parse($rule)[1] : [];

        return [new RuleInvocation(
            $field,
            self::ALIASES[$name] ?? $name,
            array_values(array_map(static fn(mixed $parameter): string => is_scalar($parameter) ? (string) $parameter : '', (array) $parameters)),
            conditional: $conditional,
        )];
    }

    /**
     * @param list<RuleInvocation> $invocations
     *
     * @return list<RuleInvocation>
     */
    private static function sort(array $invocations): array
    {
        $rank = static fn(RuleInvocation $invocation): int => match (true) {
            in_array($invocation->name, self::TYPES, true) => 0,
            in_array($invocation->name, self::LAST, true) => 2,
            default => 1,
        };

        usort($invocations, static fn(RuleInvocation $a, RuleInvocation $b): int => $rank($a) <=> $rank($b));

        return $invocations;
    }

    /**
     * The rules of the confirmation field: those of the confirmed field, `confirmed` left out.
     *
     * @param list<RuleInvocation> $invocations
     *
     * @return list<RuleInvocation>
     */
    private static function confirmation(string $field, array $invocations): array
    {
        $copies = [];

        foreach ($invocations as $invocation) {
            if ($invocation->name !== 'confirmed') {
                $copies[] = new RuleInvocation($field, $invocation->name, $invocation->parameters, $invocation->rule, $invocation->conditional);
            }
        }

        return $copies;
    }
}
