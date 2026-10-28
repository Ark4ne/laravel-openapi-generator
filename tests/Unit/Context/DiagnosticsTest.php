<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Context;

use Ark4ne\OpenApi\Context\Diagnostic;
use Ark4ne\OpenApi\Context\DiagnosticLevel;
use Ark4ne\OpenApi\Context\Diagnostics;
use Ark4ne\OpenApi\Context\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Diagnostics::class)]
#[CoversClass(Diagnostic::class)]
final class DiagnosticsTest extends TestCase
{
    public function test_a_new_collection_is_empty(): void
    {
        $diagnostics = new Diagnostics();

        self::assertTrue($diagnostics->isEmpty());
        self::assertCount(0, $diagnostics);
        self::assertSame([], $diagnostics->all());
        self::assertFalse($diagnostics->has(DiagnosticLevel::Info));
    }

    public function test_diagnostics_are_kept_in_the_order_they_were_reported(): void
    {
        $diagnostics = new Diagnostics();
        $first = new Diagnostic(DiagnosticLevel::Error, Stage::Writing, 'first');

        $diagnostics->add($first);
        $diagnostics->info(Stage::Request, 'second', 'GET /users');
        $diagnostics->warning(Stage::Discovery, 'third');
        $diagnostics->error(Stage::Response, 'fourth');

        self::assertFalse($diagnostics->isEmpty());
        self::assertCount(4, $diagnostics);
        self::assertSame($first, $diagnostics->all()[0]);
        self::assertSame(['first', 'second', 'third', 'fourth'], array_map(static fn(Diagnostic $d): string => $d->message, $diagnostics->all()));
        self::assertSame($diagnostics->all(), iterator_to_array($diagnostics));
        self::assertEquals(new Diagnostic(DiagnosticLevel::Info, Stage::Request, 'second', 'GET /users'), $diagnostics->all()[1]);
    }

    public function test_diagnostics_are_filtered_by_level(): void
    {
        $diagnostics = new Diagnostics();
        $diagnostics->info(Stage::Request, 'info');
        $diagnostics->warning(Stage::Request, 'warning');
        $diagnostics->info(Stage::Request, 'info again');

        self::assertSame(['info', 'info again'], array_map(static fn(Diagnostic $d): string => $d->message, $diagnostics->ofLevel(DiagnosticLevel::Info)));
        self::assertSame([], $diagnostics->ofLevel(DiagnosticLevel::Error));
    }

    public function test_has_is_true_for_the_level_and_the_more_severe_ones(): void
    {
        $diagnostics = new Diagnostics();
        $diagnostics->warning(Stage::Request, 'warning');

        self::assertTrue($diagnostics->has(DiagnosticLevel::Info));
        self::assertTrue($diagnostics->has(DiagnosticLevel::Warning));
        self::assertFalse($diagnostics->has(DiagnosticLevel::Error));
    }

    public function test_a_diagnostic_is_written_with_its_level_stage_and_route(): void
    {
        self::assertSame(
            '[warning] response GET /users/{user}: cannot sample App\Http\Resources\UserResource',
            (string) new Diagnostic(DiagnosticLevel::Warning, Stage::Response, 'cannot sample App\Http\Resources\UserResource', 'GET /users/{user}'),
        );
        self::assertSame('[info] configuration: unused key', (string) new Diagnostic(DiagnosticLevel::Info, Stage::Configuration, 'unused key'));
    }
}
