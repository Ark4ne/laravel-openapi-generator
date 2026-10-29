<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Extraction;

use Ark4ne\OpenApi\Description\OperationDescription;
use Ark4ne\OpenApi\Extraction\OperationContext;
use Ark4ne\OpenApi\Extraction\Operations\AttributeOperationExtractor;
use Ark4ne\OpenApi\Extraction\Operations\NamingOperationExtractor;
use Ark4ne\OpenApi\Extraction\Operations\OperationExtractors;
use Ark4ne\OpenApi\Extraction\Operations\PhpDocOperationExtractor;
use Ark4ne\OpenApi\Extraction\Strategies\BuiltInOperationIdStrategy;
use Ark4ne\OpenApi\Extraction\Strategies\BuiltInSummaryStrategy;
use Ark4ne\OpenApi\Tests\Fixtures\Metadata\Controllers\Crud\UserCrudController;
use Ark4ne\OpenApi\Tests\Fixtures\Metadata\NoTagStrategy;
use Ark4ne\OpenApi\Tests\TestCase;
use Ark4ne\OpenApi\Types\Type;
use Ark4ne\OpenApi\Types\TypeResolver;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionMethod;

/**
 * Actions inherited from a parent controller, or redefined by the child: the attributes and the PHPDoc of both are
 * merged, the child's winning.
 */
#[CoversClass(OperationContext::class)]
#[CoversClass(AttributeOperationExtractor::class)]
#[CoversClass(TypeResolver::class)]
final class InheritedActionsTest extends TestCase
{
    public function test_the_controller_is_the_controller_of_the_route(): void
    {
        $this->router()->get('api/users', [UserCrudController::class, 'index']);

        self::assertSame(UserCrudController::class, $this->operation()->controller()?->getName());
    }

    public function test_an_inherited_action_merges_the_attributes_of_the_classes_and_of_the_parent_method(): void
    {
        $this->router()->get('api/users', [UserCrudController::class, 'index']);

        $description = $this->extract();

        self::assertSame('Base summary', $description->summary);
        self::assertSame('crud.index', $description->operationId);
        self::assertSame('Child description', $description->description);
        self::assertSame(['url' => 'https://example.com/base'], $description->externalDocs);
        self::assertSame(['crud', 'admin', 'listing'], $description->tags);
    }

    public function test_a_redefined_action_merges_its_attributes_with_those_of_the_parent_method(): void
    {
        $this->router()->get('api/users/{user}', [UserCrudController::class, 'show']);

        $description = $this->extract();

        self::assertSame('Child show', $description->summary);
        self::assertSame('crud.show', $description->operationId);
        self::assertSame(['crud', 'admin', 'reading', 'users'], $description->tags);
    }

    public function test_a_redefined_action_with_inherit_doc_takes_the_phpdoc_of_the_parent(): void
    {
        $this->router()->get('api/users/{user}', [UserCrudController::class, 'show']);

        $operation = $this->operation();

        self::assertSame('Show a record.', $operation->doc?->summary());
        self::assertSame('Read from the parent.', $operation->doc->description());
        self::assertSame([ModelNotFoundException::class], array_map(static fn(Type $type): string => $type->toString(), $operation->throws));
    }

    public function test_a_method_without_phpdoc_and_without_parent_has_none(): void
    {
        $method = new ReflectionMethod(UserCrudController::class, 'store');
        $resolver = new TypeResolver();

        self::assertSame($method, $resolver->documented($method));
        self::assertNull($resolver->docBlock($method)->summary());
    }

    private function extract(): OperationDescription
    {
        $operation = $this->operation();
        $extractors = new OperationExtractors(
            new NamingOperationExtractor(new BuiltInSummaryStrategy(), new NoTagStrategy(), new BuiltInOperationIdStrategy()),
            new PhpDocOperationExtractor(),
            new AttributeOperationExtractor(),
        );

        return $extractors->extract($operation, $operation->describe(), $this->context());
    }
}
