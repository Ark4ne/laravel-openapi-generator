<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Generation;

use Ark4ne\OpenApi\Configuration\Configuration;
use Ark4ne\OpenApi\Configuration\DocumentConfiguration;
use Ark4ne\OpenApi\Configuration\InfoConfiguration;
use Ark4ne\OpenApi\Configuration\Variant;
use Ark4ne\OpenApi\Configuration\VariantsConfiguration;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\DocumentDescription;
use Ark4ne\OpenApi\Description\OperationDescription;
use Ark4ne\OpenApi\Description\VariantBounds;
use Ark4ne\OpenApi\Generation\Passes\VariantBoundsPass;
use Ark4ne\OpenApi\Tests\Fixtures\Variants\ApiRevision;
use Ark4ne\OpenApi\Tests\Support\Descriptions;
use Ark4ne\OpenApi\Tests\TestCase;
use Ark4ne\OpenApiWriter\Enums\HttpMethod;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(VariantBoundsPass::class)]
final class VariantBoundsPassTest extends TestCase
{
    public function test_an_operation_since_a_variant_is_absent_from_older_documents(): void
    {
        $document = self::document(VariantBounds::between(since: ApiRevision::Labels));

        self::assertSame(['GET /api/users'], self::kept($document, $this->variantContext(ApiRevision::Initial)));
        self::assertSame(['GET /api/users', 'POST /api/users'], self::kept($document, $this->variantContext(ApiRevision::Labels)));
        self::assertSame(['GET /api/users', 'POST /api/users'], self::kept($document, $this->variantContext(null)));
    }

    public function test_an_operation_until_a_variant_is_absent_from_newer_documents_and_the_head(): void
    {
        $document = self::document(VariantBounds::between(until: ApiRevision::Initial));

        self::assertSame(['GET /api/users', 'POST /api/users'], self::kept($document, $this->variantContext(ApiRevision::Initial)));
        self::assertSame(['GET /api/users'], self::kept($document, $this->variantContext(ApiRevision::Labels)));
        self::assertSame(['GET /api/users'], self::kept($document, $this->variantContext(null)));
    }

    public function test_bounds_are_inclusive(): void
    {
        $document = self::document(VariantBounds::between(ApiRevision::Labels, ApiRevision::Labels));

        self::assertSame(['GET /api/users'], self::kept($document, $this->variantContext(ApiRevision::Initial)));
        self::assertSame(['GET /api/users', 'POST /api/users'], self::kept($document, $this->variantContext(ApiRevision::Labels)));
        self::assertSame(['GET /api/users'], self::kept($document, $this->variantContext(null)));
    }

    public function test_webhooks_are_bounded_too(): void
    {
        $webhook = Descriptions::operation(HttpMethod::Post, '/orders')->webhook('order.shipped')->bounds(VariantBounds::between(since: ApiRevision::Labels));
        $document = DocumentDescription::create()->webhooks($webhook);

        self::assertSame([], (new VariantBoundsPass())->process($document, $this->variantContext(ApiRevision::Initial))->webhooks);
        self::assertSame([$webhook], (new VariantBoundsPass())->process($document, $this->variantContext(ApiRevision::Labels))->webhooks);
    }

    public function test_an_unknown_bound_is_reported(): void
    {
        $context = $this->variantContext(ApiRevision::Initial);

        self::assertSame(['GET /api/users', 'POST /api/users'], self::kept(self::document(VariantBounds::between('2027-01-01', 'latest')), $context));
        self::assertDiagnostics([
            '[error] pass POST /api/users: the bound 2027-01-01 is not a variant of the document, ignored',
            '[error] pass POST /api/users: the bound latest is not a variant of the document, ignored',
        ], $context);
    }

    public function test_bounds_are_ignored_without_variants(): void
    {
        $document = self::document(VariantBounds::between(until: 'unknown'));
        $context = $this->context();

        self::assertSame($document, (new VariantBoundsPass())->process($document, $context));
        self::assertDiagnostics([], $context);
    }

    /**
     * Two operations: `GET /api/users` without bounds, `POST /api/users` with `$bounds`.
     */
    private static function document(VariantBounds $bounds): DocumentDescription
    {
        return Descriptions::document(Descriptions::operation(), Descriptions::operation(HttpMethod::Post)->bounds($bounds));
    }

    /**
     * @return list<string|null> the routes of the operations kept
     */
    private static function kept(DocumentDescription $document, GenerationContext $context): array
    {
        return array_map(static fn(OperationDescription $operation): ?string => $operation->route, (new VariantBoundsPass())->process($document, $context)->operations);
    }

    private function variantContext(?ApiRevision $revision): GenerationContext
    {
        $variants = new VariantsConfiguration([Variant::ofCase(ApiRevision::Initial, 0), Variant::ofCase(ApiRevision::Labels, 1)], head: 'latest');
        $document = new DocumentConfiguration('v1', new InfoConfiguration('API', '1'), variants: $variants);

        return GenerationContext::for(new Configuration(['v1' => $document]), $document, variant: $revision === null ? null : $variants->named($revision->value));
    }
}
