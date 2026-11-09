<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Extraction\Operations;

use Ark4ne\OpenApi\Description\VariantBounds;
use Ark4ne\OpenApi\Extraction\Operations\AttributeOperationExtractor;
use Ark4ne\OpenApi\Tests\Fixtures\Variants\BoundedController;
use Ark4ne\OpenApi\Tests\Fixtures\Variants\RevisionController;
use Ark4ne\OpenApi\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(AttributeOperationExtractor::class)]
final class VariantBoundsAttributesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, array{string|null, string|null}}>
     */
    public static function actions(): iterable
    {
        yield 'controller bound' => ['index', [null, '2026-12-01']];
        yield 'action and controller bounds' => ['store', ['2026-12-01', '2026-12-01']];
        yield 'string bound' => ['show', ['2026-09-01', '2026-12-01']];
    }

    /**
     * @param array{string|null, string|null} $expected since and until
     */
    #[DataProvider('actions')]
    public function test_the_bounds_are_read_on_the_action_then_on_the_controller(string $action, array $expected): void
    {
        $bounds = $this->bounds([BoundedController::class, $action]);

        self::assertSame($expected, [$bounds?->since, $bounds?->until]);
    }

    public function test_a_method_bound_overrides_the_controller_bound(): void
    {
        $bounds = $this->bounds([BoundedController::class, 'destroy']);

        self::assertSame([null, '2026-09-01'], [$bounds?->since, $bounds?->until]);
    }

    public function test_an_action_without_bound_has_none(): void
    {
        self::assertNull($this->bounds([RevisionController::class, 'show']));
    }

    /**
     * @param array{class-string, string} $action
     */
    private function bounds(array $action): ?VariantBounds
    {
        $this->router()->get('api/action', $action);
        $context = $this->context();
        $operation = $this->operation($context);

        $bounds = (new AttributeOperationExtractor())->extract($operation, $operation->describe(), $context)->bounds;
        self::assertDiagnostics([], $context);

        return $bounds;
    }
}
