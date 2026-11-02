<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Extraction\Requests;

use Ark4ne\OpenApi\Description\ParameterDescription;
use Ark4ne\OpenApi\Extraction\Requests\FieldPlacer;
use Ark4ne\OpenApi\Tests\Support\Schemas;
use Ark4ne\OpenApiWriter\Objects\Schema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Parameters merged by name and location, completed by an attribute, and query parameters folded into a
 * `querystring`.
 */
#[CoversClass(FieldPlacer::class)]
final class FieldPlacerTest extends TestCase
{
    public function test_a_folded_query_parameter_keeps_its_description_and_its_deprecation(): void
    {
        $merged = FieldPlacer::merge([self::querystring()], [ParameterDescription::query('page', Schema::integer())->description('Page')->deprecated()]);

        Schemas::assertSame('{"type":"object","properties":{"q":{"type":"string"},"page":{"type":"integer","description":"Page","deprecated":true}},"required":["q","page"]}', $merged[0]->schema);
    }

    public function test_an_optional_folded_query_parameter_keeps_the_requirement_of_the_querystring(): void
    {
        $merged = FieldPlacer::merge([self::querystring()], [ParameterDescription::query('q')]);

        self::assertSame(['q', 'page'], $merged[0]->schema->required);
    }

    public function test_a_merged_header_completes_the_header_of_the_same_name_whatever_its_case_under_its_first_name(): void
    {
        $declared = ParameterDescription::header('X-Csrf-Token', Schema::string()->format('uuid'))->required()->description('Token');

        $merged = FieldPlacer::merge([$declared, ParameterDescription::cookie('session')], [ParameterDescription::header('X-CSRF-TOKEN')->description('CSRF token')]);

        self::assertSame(['X-Csrf-Token', 'session'], array_map(static fn(ParameterDescription $parameter): string => $parameter->name, $merged));
        self::assertTrue($merged[0]->required);
        self::assertSame('CSRF token', $merged[0]->description);
        Schemas::assertSame(['type' => 'string'], $merged[0]->schema);
    }

    public function test_a_merged_header_keeps_the_description_and_the_deprecation_of_the_first_one_when_it_has_none(): void
    {
        $merged = FieldPlacer::merge([ParameterDescription::header('X-Tenant')->description('Tenant')->deprecated()], [ParameterDescription::header('x-tenant')]);

        self::assertCount(1, $merged);
        self::assertSame(['X-Tenant', 'Tenant', true, false], [$merged[0]->name, $merged[0]->description, $merged[0]->deprecated, $merged[0]->required]);
    }

    public function test_a_merged_query_parameter_keeps_its_case_and_replaces_the_one_of_the_same_name(): void
    {
        $merged = FieldPlacer::merge([ParameterDescription::query('page')->description('Page')], [ParameterDescription::query('Page'), ParameterDescription::query('page')]);

        self::assertSame(['page', 'Page'], array_map(static fn(ParameterDescription $parameter): string => $parameter->name, $merged));
        self::assertNull($merged[0]->description);
    }

    public function test_a_completed_header_replaces_the_header_of_the_same_name_whatever_its_case(): void
    {
        $completed = FieldPlacer::complete([ParameterDescription::header('X-Tenant')], ParameterDescription::header('x-tenant')->required());

        self::assertCount(1, $completed);
        self::assertTrue($completed[0]->required);
    }

    public function test_a_completed_header_replaces_every_header_of_the_same_name_at_the_place_of_the_first(): void
    {
        $parameters = [ParameterDescription::header('X-Tenant'), ParameterDescription::cookie('session'), ParameterDescription::header('x-tenant')];

        $completed = FieldPlacer::complete($parameters, ParameterDescription::header('X-TENANT')->required());

        self::assertSame(['X-TENANT', 'session'], array_map(static fn(ParameterDescription $parameter): string => $parameter->name, $completed));
    }

    public function test_a_completed_query_parameter_is_written_in_the_last_querystring_like_the_folded_ones(): void
    {
        $first = ParameterDescription::querystring('first', Schema::object());

        $completed = FieldPlacer::complete([$first, self::querystring()], ParameterDescription::query('sort')->required());

        self::assertSame($first, $completed[0]);
        self::assertSame(['q', 'page', 'sort'], $completed[1]->schema->required);
    }

    public function test_a_completed_query_parameter_keeps_the_place_of_its_name_in_required(): void
    {
        $completed = FieldPlacer::complete([self::querystring()], ParameterDescription::query('q')->required());

        self::assertSame(['q', 'page'], $completed[0]->schema->required);
    }

    public function test_a_completed_query_parameter_leaves_required_and_its_deprecation_when_it_has_none(): void
    {
        $querystring = ParameterDescription::querystring('query', Schema::object()->properties(Schema::string('q')->deprecated())->required('q'));

        $completed = FieldPlacer::complete([$querystring], ParameterDescription::query('q', Schema::string()));

        Schemas::assertSame('{"type":"object","properties":{"q":{"type":"string"}}}', $completed[0]->schema);
    }

    public function test_a_completed_parameter_without_match_is_added(): void
    {
        $completed = FieldPlacer::complete([ParameterDescription::header('X-Tenant')], ParameterDescription::cookie('session'));

        self::assertSame(['X-Tenant', 'session'], array_map(static fn(ParameterDescription $parameter): string => $parameter->name, $completed));
    }

    public function test_find_returns_the_parameter_of_the_same_name_and_location(): void
    {
        $parameters = [ParameterDescription::query('tenant'), ParameterDescription::header('X-Tenant')->description('Tenant')];

        self::assertSame('Tenant', FieldPlacer::find(ParameterDescription::header('x-tenant'), $parameters)?->description);
        self::assertNull(FieldPlacer::find(ParameterDescription::cookie('tenant'), $parameters));
    }

    public function test_find_returns_the_property_of_the_querystring_as_a_query_parameter(): void
    {
        $querystring = ParameterDescription::querystring('query', Schema::object()->properties(Schema::string('q')->description('Words')->deprecated())->required('q'));

        $found = FieldPlacer::find(ParameterDescription::query('q'), [$querystring]);

        self::assertNotNull($found);
        self::assertSame(['q', 'Words', true, true], [$found->name, $found->description, $found->required, $found->deprecated]);
        self::assertNull(FieldPlacer::find(ParameterDescription::query('page'), [$querystring]));
    }

    private static function querystring(): ParameterDescription
    {
        return ParameterDescription::querystring('query', Schema::object()->properties(Schema::string('q'), Schema::integer('page'))->required('q', 'page'));
    }
}
