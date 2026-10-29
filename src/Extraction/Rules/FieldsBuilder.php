<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Rules;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Context\Stage;
use Ark4ne\OpenApi\Description\FieldDescription;
use Ark4ne\OpenApiWriter\Objects\Schema;
use Illuminate\Validation\Rules\AnyOf;
use ReflectionProperty;

/**
 * Applies the rules of each field: a rule describing itself (`DescribesRule`), `Rule::anyOf()` (one schema per set
 * of rules), else the RuleMapper of the rule. An unknown rule leaves the field as it is, with an info.
 */
final readonly class FieldsBuilder
{
    public function __construct(
        private RuleMappers $mappers,
    ) {}

    /**
     * @param array<string, list<RuleInvocation>> $rules as the RuleParser gives them
     *
     * @return list<FieldDescription> in the order of the fields
     */
    public function build(array $rules, GenerationContext $context, string $label): array
    {
        $fields = [];

        foreach ($rules as $name => $invocations) {
            $fields[] = $this->field($name, $invocations, $context, $label);
        }

        return $fields;
    }

    /**
     * @param list<RuleInvocation> $invocations
     */
    private function field(string $name, array $invocations, GenerationContext $context, string $label): FieldDescription
    {
        $field = FieldDescription::create($name);

        foreach ($invocations as $invocation) {
            $rule = $invocation->rule;

            if ($rule instanceof DescribesRule) {
                $field = $rule->describe($field);
            } elseif ($rule instanceof AnyOf) {
                $field = $field->schema($field->schema->anyOf(...$this->alternatives($name, $rule, $context, $label)));
            } elseif (($mapper = $this->mappers->for($invocation->name)) !== null) {
                $field = $mapper->map($invocation, $field, $context);
            } else {
                $context->diagnostics->info(Stage::Request, "unknown rule {$invocation->name} on {$name}, field kept without its constraint", $label);
            }
        }

        return $field;
    }

    /**
     * One schema per set of rules of `Rule::anyOf()`.
     *
     * @return list<Schema>
     */
    private function alternatives(string $name, AnyOf $rule, GenerationContext $context, string $label): array
    {
        $sets = (new ReflectionProperty($rule, 'rules'))->getValue($rule);
        $parser = new RuleParser();
        $schemas = [];

        foreach (is_array($sets) ? $sets : [] as $set) {
            $parsed = $parser->parse([$name => $set], $context, $label);
            $schemas[] = $this->field($name, $parsed[$name] ?? [], $context, $label)->schema;
        }

        return $schemas;
    }
}
