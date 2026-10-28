<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Extraction\Rules;

use Ark4ne\OpenApi\Description\FieldDescription;
use Ark4ne\OpenApi\Extraction\Rules\FieldsBuilder;
use Ark4ne\OpenApi\Extraction\Rules\FieldTree;
use Ark4ne\OpenApi\Extraction\Rules\RuleMappers;
use Ark4ne\OpenApi\Extraction\Rules\RuleParser;
use Ark4ne\OpenApi\Tests\Fixtures\Requests\UnknownRule;
use Ark4ne\OpenApi\Tests\Fixtures\Requests\UppercaseRule;
use Ark4ne\OpenApi\Tests\Fixtures\Routing\Enums\Priority;
use Ark4ne\OpenApi\Tests\Fixtures\Routing\Enums\Status;
use Ark4ne\OpenApi\Tests\Support\Schemas;
use Ark4ne\OpenApi\Tests\TestCase;
use Closure;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Rules\Password;
use PHPUnit\Framework\Attributes\CoversNamespace;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Every built-in RuleMapper, through the RuleParser and the FieldsBuilder: one row per rule and variant.
 *
 * Each row gives the rules of the field `f` and what is expected of it: `schema` (compared whatever the order of its
 * top-level keys), `required`, `conditions`, `file`, `contentTypes`, `forbidden`.
 */
#[CoversNamespace('Ark4ne\OpenApi\Extraction\Rules')]
final class RuleMappersTest extends TestCase
{
    private const ULID = '^[0-7][0-9a-hjkmnp-tv-zA-HJKMNP-TV-Z]{25}$';

    /**
     * @return iterable<string, array{mixed, array<string, mixed>}>
     */
    public static function presence(): iterable
    {
        yield 'no rule' => [[], ['schema' => [], 'required' => false]];
        yield 'required' => ['required', ['required' => true]];
        yield 'required_if' => ['required_if:type,company', ['required' => false, 'conditions' => ['required if type is company']]];
        yield 'required_if with several values' => ['required_if:type,a,b', ['conditions' => ['required if type is a or b']]];
        yield 'required_unless' => ['required_unless:type,person', ['required' => false, 'conditions' => ['required unless type is person']]];
        yield 'required_with' => ['required_with:a,b', ['required' => false, 'conditions' => ['required with a or b']]];
        yield 'required_with_all' => ['required_with_all:a,b', ['conditions' => ['required with a and b']]];
        yield 'required_without' => ['required_without:a,b', ['conditions' => ['required without a or b']]];
        yield 'required_without_all' => ['required_without_all:a,b', ['conditions' => ['required without a and b']]];
        yield 'required_if_accepted' => ['required_if_accepted:terms', ['conditions' => ['required if terms is accepted']]];
        yield 'required_if_declined' => ['required_if_declined:terms', ['conditions' => ['required if terms is declined']]];
        yield 'required_array_keys' => ['required_array_keys:id,name', ['schema' => ['type' => 'object', 'required' => ['id', 'name']]]];
        yield 'Rule::requiredIf' => [[Rule::requiredIf(true)], ['required' => false, 'conditions' => ['required in some cases']]];
        yield 'nullable' => ['string|nullable', ['schema' => ['type' => ['string', 'null']]]];
        yield 'sometimes cancels required' => ['sometimes|required|string', ['required' => false]];
        yield 'present' => ['present', ['required' => true]];
        yield 'present_if' => ['present_if:type,a', ['required' => false, 'conditions' => ['present if type is a']]];
        yield 'present_with' => ['present_with:a', ['conditions' => ['present with a']]];
        yield 'filled' => ['string|filled', ['schema' => ['type' => 'string', 'minLength' => 1]]];
        yield 'missing' => ['missing', ['forbidden' => true]];
        yield 'missing_if' => ['missing_if:type,a', ['forbidden' => false, 'conditions' => ['missing if type is a']]];
        yield 'prohibited' => ['prohibited', ['forbidden' => true]];
        yield 'prohibited_if' => ['prohibited_if:type,a', ['forbidden' => false, 'conditions' => ['prohibited if type is a']]];
        yield 'prohibited_unless' => ['prohibited_unless:type,a', ['conditions' => ['prohibited unless type is a']]];
        yield 'prohibited_if_accepted' => ['prohibited_if_accepted:terms', ['conditions' => ['prohibited if terms is accepted']]];
        yield 'prohibits' => ['prohibits:a,b', ['conditions' => ['prohibits a and b']]];
        yield 'exclude and bail change nothing' => ['bail|exclude_if:a,b|exclude|string', ['schema' => ['type' => 'string'], 'conditions' => []]];
        yield 'Rule::when, both branches' => [[Rule::when(true, ['required', 'string'], ['integer'])], ['required' => false, 'conditions' => ['required in some cases']]];
    }

    /**
     * @return iterable<string, array{mixed, array<string, mixed>}>
     */
    public static function types(): iterable
    {
        yield 'string' => ['string', ['schema' => ['type' => 'string']]];
        yield 'integer' => ['integer', ['schema' => ['type' => 'integer']]];
        yield 'int' => ['int', ['schema' => ['type' => 'integer']]];
        yield 'numeric' => ['numeric', ['schema' => ['type' => 'number']]];
        yield 'numeric keeps integer' => ['integer|numeric', ['schema' => ['type' => 'integer']]];
        yield 'decimal' => ['decimal:2', ['schema' => ['type' => 'number'], 'conditions' => ['2 decimal places']]];
        yield 'decimal range' => ['decimal:1,3', ['conditions' => ['1 to 3 decimal places']]];
        yield 'boolean' => ['boolean', ['schema' => ['type' => 'boolean']]];
        yield 'bool' => ['bool', ['schema' => ['type' => 'boolean']]];
        yield 'array' => ['array', ['schema' => ['type' => 'array']]];
        yield 'list' => ['list', ['schema' => ['type' => 'array']]];
        yield 'array with keys' => ['array:id,name', ['schema' => ['type' => 'object', 'properties' => ['id' => [], 'name' => []], 'additionalProperties' => false]]];
        yield 'Rule::array' => [[Rule::array(['id'])], ['schema' => ['type' => 'object', 'properties' => ['id' => []], 'additionalProperties' => false]]];
        yield 'json' => ['json', ['schema' => ['type' => 'string', 'contentMediaType' => 'application/json']]];
        yield 'file' => ['file', ['schema' => ['type' => 'string', 'format' => 'binary'], 'file' => true]];
        yield 'image' => ['image', ['file' => true, 'contentTypes' => ['image/jpeg', 'image/png', 'image/bmp', 'image/gif', 'image/webp']]];
        yield 'image with svg' => ['image:allow_svg', ['contentTypes' => ['image/jpeg', 'image/png', 'image/bmp', 'image/gif', 'image/svg+xml', 'image/webp']]];
        yield 'date' => ['date', ['schema' => ['type' => 'string'], 'conditions' => ['a date']]];
        yield 'accepted' => ['accepted', ['schema' => ['enum' => ['yes', 'on', 1, '1', true, 'true']], 'required' => true]];
        yield 'accepted_if' => ['accepted_if:type,a', ['required' => false, 'conditions' => ['accepted if type is a']]];
        yield 'declined' => ['declined', ['schema' => ['enum' => ['no', 'off', 0, '0', false, 'false']], 'required' => true]];
        yield 'declined_if' => ['declined_if:type,a', ['conditions' => ['declined if type is a']]];
    }

    /**
     * @return iterable<string, array{mixed, array<string, mixed>}>
     */
    public static function formats(): iterable
    {
        yield 'email' => ['email', ['schema' => ['type' => 'string', 'format' => 'email']]];
        yield 'email with validators' => ['email:rfc,dns', ['schema' => ['type' => 'string', 'format' => 'email']]];
        yield 'Rule::email' => [[Rule::email()], ['schema' => ['type' => 'string', 'format' => 'email']]];
        yield 'url' => ['url', ['schema' => ['type' => 'string', 'format' => 'uri']]];
        yield 'url with schemes' => ['url:https', ['schema' => ['type' => 'string', 'format' => 'uri'], 'conditions' => ['scheme https']]];
        yield 'active_url' => ['active_url', ['schema' => ['type' => 'string', 'format' => 'uri']]];
        yield 'uuid' => ['uuid', ['schema' => ['type' => 'string', 'format' => 'uuid']]];
        yield 'ulid' => ['ulid', ['schema' => ['type' => 'string', 'pattern' => self::ULID]]];
        yield 'ip' => ['ip', ['schema' => ['type' => 'string'], 'conditions' => ['an IP address']]];
        yield 'ipv4' => ['ipv4', ['schema' => ['type' => 'string', 'format' => 'ipv4']]];
        yield 'ipv6' => ['ipv6', ['schema' => ['type' => 'string', 'format' => 'ipv6']]];
        yield 'mac_address' => ['mac_address', ['schema' => ['type' => 'string', 'pattern' => '^([0-9A-Fa-f]{2}[:-]){5}[0-9A-Fa-f]{2}$']]];
        yield 'hex_color' => ['hex_color', ['schema' => ['type' => 'string', 'pattern' => '^#([0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$']]];
        yield 'timezone' => ['timezone', ['schema' => ['type' => 'string'], 'conditions' => ['a timezone identifier']]];
        yield 'date_format date' => ['date_format:Y-m-d', ['schema' => ['type' => 'string', 'format' => 'date']]];
        yield 'date_format date-time' => ['date_format:Y-m-d\TH:i:sP', ['schema' => ['type' => 'string', 'format' => 'date-time']]];
        yield 'date_format other' => ['date_format:d/m/Y', ['schema' => ['type' => 'string'], 'conditions' => ['format d/m/Y']]];
        yield 'ascii' => ['ascii', ['schema' => ['type' => 'string', 'pattern' => '^[\x00-\x7F]*$']]];
        yield 'lowercase' => ['lowercase', ['schema' => ['type' => 'string'], 'conditions' => ['lowercase']]];
        yield 'uppercase' => ['uppercase', ['conditions' => ['uppercase']]];
        yield 'alpha' => ['alpha', ['schema' => ['type' => 'string'], 'conditions' => ['letters only']]];
        yield 'alpha ascii' => ['alpha:ascii', ['schema' => ['type' => 'string', 'pattern' => '^[a-zA-Z]+$']]];
        yield 'alpha_num ascii' => ['alpha_num:ascii', ['schema' => ['type' => 'string', 'pattern' => '^[a-zA-Z0-9]+$']]];
        yield 'alpha_dash ascii' => ['alpha_dash:ascii', ['schema' => ['type' => 'string', 'pattern' => '^[a-zA-Z0-9_-]+$']]];
        yield 'alpha_dash' => ['alpha_dash', ['conditions' => ['letters, numbers, dashes and underscores only']]];
        yield 'regex' => ['regex:/^[a-z]+$/', ['schema' => ['type' => 'string', 'pattern' => '^[a-z]+$']]];
        yield 'regex with a pipe in an array' => [['regex:/^(a|b)$/'], ['schema' => ['type' => 'string', 'pattern' => '^(a|b)$']]];
        yield 'regex with flags' => ['regex:/^[a-z]+$/i', ['schema' => ['type' => 'string'], 'conditions' => ['matches /^[a-z]+$/i']]];
        yield 'not_regex' => ['not_regex:/^\d+$/', ['conditions' => ['does not match /^\d+$/']]];
        yield 'starts_with' => ['starts_with:a.,b', ['schema' => ['type' => 'string', 'pattern' => '^(?:a\.|b)']]];
        yield 'ends_with' => ['ends_with:x', ['schema' => ['type' => 'string', 'pattern' => '(?:x)$']]];
        yield 'starts_with with a pattern' => ['alpha:ascii|starts_with:a', ['schema' => ['type' => 'string', 'pattern' => '^[a-zA-Z]+$'], 'conditions' => ['starts with a']]];
        yield 'doesnt_start_with' => ['doesnt_start_with:a,b', ['conditions' => ['does not start with a or b']]];
        yield 'doesnt_end_with' => ['doesnt_end_with:a', ['conditions' => ['does not end with a']]];
    }

    /**
     * @return iterable<string, array{mixed, array<string, mixed>}>
     */
    public static function bounds(): iterable
    {
        yield 'string min max' => ['string|min:2|max:5', ['schema' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 5]]];
        yield 'type after the bounds' => ['max:5|string', ['schema' => ['type' => 'string', 'maxLength' => 5]]];
        yield 'integer min max' => ['integer|min:1|max:10', ['schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10]]];
        yield 'numeric between' => ['numeric|between:1.5,3', ['schema' => ['type' => 'number', 'minimum' => 1.5, 'maximum' => 3]]];
        yield 'array size' => ['array|size:3', ['schema' => ['type' => 'array', 'minItems' => 3, 'maxItems' => 3]]];
        yield 'string size' => ['string|size:4', ['schema' => ['type' => 'string', 'minLength' => 4, 'maxLength' => 4]]];
        yield 'file max' => ['file|max:2048', ['schema' => ['type' => 'string', 'format' => 'binary'], 'conditions' => ['at most 2048 KB']]];
        yield 'file between' => ['file|between:1,10', ['conditions' => ['between 1 and 10 KB']]];
        yield 'object min max' => ['array:a,b|min:1|max:2', ['schema' => ['type' => 'object', 'properties' => ['a' => [], 'b' => []], 'additionalProperties' => false, 'minProperties' => 1, 'maxProperties' => 2]]];
        yield 'filled object' => ['array:a|filled', ['schema' => ['type' => 'object', 'properties' => ['a' => []], 'additionalProperties' => false, 'minProperties' => 1]]];
        yield 'untyped max is a length' => ['max:5', ['schema' => ['maxLength' => 5]]];
        yield 'gt on integer' => ['integer|gt:5', ['schema' => ['type' => 'integer', 'exclusiveMinimum' => 5]]];
        yield 'gte on number' => ['numeric|gte:5', ['schema' => ['type' => 'number', 'minimum' => 5]]];
        yield 'lt on integer' => ['integer|lt:5', ['schema' => ['type' => 'integer', 'exclusiveMaximum' => 5]]];
        yield 'lte on integer' => ['integer|lte:5', ['schema' => ['type' => 'integer', 'maximum' => 5]]];
        yield 'gt on string' => ['string|gt:3', ['schema' => ['type' => 'string', 'minLength' => 4]]];
        yield 'lt on array' => ['array|lt:3', ['schema' => ['type' => 'array', 'maxItems' => 2]]];
        yield 'gt on a field' => ['integer|gt:minimum', ['schema' => ['type' => 'integer'], 'conditions' => ['greater than minimum']]];
        yield 'lte on a field' => ['integer|lte:maximum', ['conditions' => ['less than or equal to maximum']]];
        yield 'digits on a string' => ['digits:4', ['schema' => ['type' => 'string', 'pattern' => '^[0-9]{4}$']]];
        yield 'digits on an integer' => ['integer|digits:3', ['schema' => ['type' => 'integer', 'minimum' => 100, 'maximum' => 999]]];
        yield 'digits_between' => ['digits_between:2,4', ['schema' => ['type' => 'string', 'pattern' => '^[0-9]{2,4}$']]];
        yield 'min_digits' => ['integer|min_digits:2', ['schema' => ['type' => 'integer'], 'conditions' => ['at least 2 digits']]];
        yield 'max_digits' => ['integer|max_digits:4', ['conditions' => ['at most 4 digits']]];
        yield 'multiple_of' => ['multiple_of:5', ['schema' => ['type' => 'number', 'multipleOf' => 5]]];
        yield 'multiple_of on integer' => ['integer|multiple_of:5', ['schema' => ['type' => 'integer', 'multipleOf' => 5]]];
    }

    /**
     * @return iterable<string, array{mixed, array<string, mixed>}>
     */
    public static function dates(): iterable
    {
        yield 'after a date' => ['date|after:today', ['conditions' => ['a date', 'after today']]];
        yield 'after_or_equal a field' => ['date|after_or_equal:start', ['conditions' => ['a date', 'after or equal to start']]];
        yield 'before' => ['before:2030-01-01', ['conditions' => ['before 2030-01-01']]];
        yield 'before_or_equal' => ['before_or_equal:end', ['conditions' => ['before or equal to end']]];
        yield 'date_equals' => ['date_equals:today', ['conditions' => ['equal to today']]];
        yield 'Rule::date' => [[Rule::date()->format('Y-m-d')->after('2020-01-01')], ['schema' => ['type' => 'string', 'format' => 'date'], 'conditions' => ['after 2020-01-01']]];
    }

    /**
     * @return iterable<string, array{mixed, array<string, mixed>}>
     */
    public static function sets(): iterable
    {
        yield 'in' => ['in:a,b', ['schema' => ['enum' => ['a', 'b']]]];
        yield 'in on integer' => ['integer|in:1,2', ['schema' => ['type' => 'integer', 'enum' => [1, 2]]]];
        yield 'in on string' => ['string|in:1,2', ['schema' => ['type' => 'string', 'enum' => ['1', '2']]]];
        yield 'Rule::in' => [[Rule::in(['a', 'b,c'])], ['schema' => ['enum' => ['a', 'b,c']]]];
        yield 'in on an array applies to its items' => [['array', Rule::in(['admin', 'user'])], ['schema' => ['type' => 'array', 'items' => ['enum' => ['admin', 'user']]]]];
        yield 'not_in on an array' => ['array|not_in:a', ['schema' => ['type' => 'array', 'items' => ['not' => ['enum' => ['a']]]]]];
        yield 'not_in' => ['not_in:a,b', ['schema' => ['not' => ['enum' => ['a', 'b']]]]];
        yield 'Rule::notIn' => [[Rule::notIn(['a'])], ['schema' => ['not' => ['enum' => ['a']]]]];
        yield 'Rule::enum string' => [[Rule::enum(Status::class)], ['schema' => ['type' => 'string', 'enum' => ['draft', 'published']]]];
        yield 'Rule::enum int' => [[Rule::enum(Priority::class)], ['schema' => ['type' => 'integer', 'enum' => [1, 2]]]];
        yield 'Rule::enum only' => [[Rule::enum(Status::class)->only(Status::Draft)], ['schema' => ['type' => 'string', 'enum' => ['draft']]]];
        yield 'Rule::enum except' => [[Rule::enum(Status::class)->except([Status::Draft])], ['schema' => ['type' => 'string', 'enum' => ['published']]]];
        yield 'exists' => ['exists:users,id', ['conditions' => ['exists in users.id']]];
        yield 'exists without column' => ['exists:users', ['conditions' => ['exists in users']]];
        yield 'Rule::exists' => [[Rule::exists('users', 'email')], ['conditions' => ['exists in users.email']]];
        yield 'unique' => ['unique:users,email', ['conditions' => ['unique in users.email']]];
        yield 'contains' => ['array|contains:a,b', ['schema' => ['type' => 'array'], 'conditions' => ['contains a and b']]];
    }

    /**
     * @return iterable<string, array{mixed, array<string, mixed>}>
     */
    public static function files(): iterable
    {
        yield 'mimes' => ['mimes:jpg,png', ['file' => true, 'contentTypes' => ['image/jpeg', 'image/png'], 'schema' => ['type' => 'string', 'format' => 'binary']]];
        yield 'mimetypes' => ['mimetypes:image/png,application/pdf', ['file' => true, 'contentTypes' => ['image/png', 'application/pdf']]];
        yield 'extensions' => ['file|extensions:pdf,docx', ['file' => true, 'conditions' => ['extension pdf or docx']]];
        yield 'dimensions' => ['image|dimensions:min_width=100,ratio=3/2', ['conditions' => ['dimensions min_width=100, ratio=3/2']]];
        yield 'File::types' => [[File::types(['pdf', 'png'])->min(1)->max(1024)], ['file' => true, 'contentTypes' => ['application/pdf', 'image/png'], 'conditions' => ['at least 1 KB', 'at most 1024 KB']]];
        yield 'File::image' => [[File::image()], ['file' => true, 'contentTypes' => ['image/jpeg', 'image/png', 'image/bmp', 'image/gif', 'image/webp']]];
    }

    /**
     * @return iterable<string, array{mixed, array<string, mixed>}>
     */
    public static function fluent(): iterable
    {
        yield 'Password' => [[Password::min(10)->mixedCase()->numbers()->symbols()->letters()->uncompromised()], ['schema' => ['type' => 'string', 'format' => 'password', 'minLength' => 10], 'conditions' => ['letters', 'mixed case', 'numbers', 'symbols', 'not compromised']]];
        yield 'Password max' => [[Password::min(8)->max(64)], ['schema' => ['type' => 'string', 'format' => 'password', 'minLength' => 8, 'maxLength' => 64]]];
        yield 'Password::defaults' => [[Password::defaults()], ['schema' => ['type' => 'string', 'format' => 'password', 'minLength' => 8]]];
        yield 'Rule::numeric' => [[Rule::numeric()->min(1)], ['schema' => ['type' => 'number', 'minimum' => 1]]];
        yield 'Rule::anyOf' => [[Rule::anyOf([['string', 'max:3'], ['integer']])], ['schema' => ['anyOf' => [['type' => 'string', 'maxLength' => 3], ['type' => 'integer']]]]];
        yield 'DescribesRule' => [[new UppercaseRule()], ['schema' => ['type' => 'string', 'pattern' => '^[A-Z]+$']]];
    }

    /**
     * @return iterable<string, array{mixed, array<string, mixed>}>
     */
    public static function relations(): iterable
    {
        yield 'same' => ['same:other', ['conditions' => ['same as other']]];
        yield 'different' => ['different:other', ['conditions' => ['different from other']]];
        yield 'in_array' => ['in_array:others.*', ['conditions' => ['one of others.*']]];
    }

    /**
     * @param array<string, mixed> $expected
     */
    #[DataProvider('presence')]
    #[DataProvider('types')]
    #[DataProvider('formats')]
    #[DataProvider('bounds')]
    #[DataProvider('dates')]
    #[DataProvider('sets')]
    #[DataProvider('files')]
    #[DataProvider('fluent')]
    #[DataProvider('relations')]
    public function test_a_rule_describes_its_field(mixed $rules, array $expected): void
    {
        $field = $this->field($rules);

        if (array_key_exists('schema', $expected)) {
            self::assertIsArray($expected['schema']);
            Schemas::assertSame($expected['schema'], $field->schema);
        }

        $actual = [
            'required' => $field->required,
            'conditions' => $field->conditions,
            'file' => $field->file,
            'contentTypes' => $field->contentTypes,
            'forbidden' => $field->forbidden,
        ];

        foreach ($actual as $property => $value) {
            if (array_key_exists($property, $expected)) {
                self::assertSame($expected[$property], $value, $property);
            }
        }
    }

    public function test_confirmed_adds_the_confirmation_field(): void
    {
        $fields = $this->fields(['password' => 'required|string|min:8|confirmed']);

        self::assertSame(['password', 'password_confirmation'], array_map(static fn(FieldDescription $field): string => $field->name, $fields));
        self::assertSchema(['type' => 'string', 'minLength' => 8], $fields[1]->schema);
        self::assertTrue($fields[1]->required);
    }

    public function test_confirmed_with_a_name_adds_that_field(): void
    {
        $fields = $this->fields(['password' => 'string|confirmed:repeat']);

        self::assertSame(['password', 'repeat'], array_map(static fn(FieldDescription $field): string => $field->name, $fields));
    }

    public function test_a_key_required_twice_is_written_once(): void
    {
        $schema = FieldTree::object($this->fields(['meta' => 'array|required_array_keys:id', 'meta.id' => 'required']));

        Schemas::assertSame('{"type":"object","properties":{"meta":{"type":"object","properties":{"id":{}},"required":["id"]}}}', $schema);
    }

    public function test_distinct_marks_the_list_item(): void
    {
        self::assertTrue($this->fields(['tags.*' => 'distinct'])[0]->distinct);
    }

    public function test_an_unknown_rule_keeps_the_field_and_adds_an_info(): void
    {
        $context = $this->context();

        $field = $this->field(['string', 'shiny', new UnknownRule()], $context);

        self::assertSchema(['type' => 'string'], $field->schema);
        self::assertDiagnostics([
            '[info] request POST /test: unknown rule shiny on f, field kept without its constraint',
            '[info] request POST /test: unknown rule ' . UnknownRule::class . ' on f, field kept without its constraint',
        ], $context);
    }

    public function test_a_closure_rule_is_ignored_with_an_info(): void
    {
        $context = $this->context();

        $field = $this->field(['integer', static function (string $attribute, mixed $value, Closure $fail): void {}], $context);

        self::assertSchema(['type' => 'integer'], $field->schema);
        self::assertDiagnostics(['[info] request POST /test: closure rule on f ignored'], $context);
    }

    public function test_a_registered_mapper_replaces_the_built_in_one(): void
    {
        $mappers = RuleMappers::default()->with(new \Ark4ne\OpenApi\Tests\Fixtures\Requests\StringAsEmailRuleMapper());
        $context = $this->context();

        $fields = (new FieldsBuilder($mappers))->build((new RuleParser())->parse(['f' => 'string'], $context, 'POST /test'), $context, 'POST /test');

        self::assertSchema(['type' => 'string', 'format' => 'email'], $fields[0]->schema);
    }

    private function field(mixed $rules, ?\Ark4ne\OpenApi\Context\GenerationContext $context = null): FieldDescription
    {
        $fields = $this->fields(['f' => $rules], $context);
        self::assertSame('f', $fields[0]->name);

        return $fields[0];
    }

    /**
     * @param array<string, mixed> $rules
     *
     * @return list<FieldDescription>
     */
    private function fields(array $rules, ?\Ark4ne\OpenApi\Context\GenerationContext $context = null): array
    {
        $context ??= $this->context();

        return (new FieldsBuilder(RuleMappers::default()))->build((new RuleParser())->parse($rules, $context, 'POST /test'), $context, 'POST /test');
    }
}
