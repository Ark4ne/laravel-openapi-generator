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
use Ark4ne\OpenApi\Description\ParameterDescription;
use Ark4ne\OpenApi\Generation\Passes\VariantHeaderPass;
use Ark4ne\OpenApi\Tests\Fixtures\Variants\ApiRevision;
use Ark4ne\OpenApi\Tests\Support\Descriptions;
use Ark4ne\OpenApi\Tests\TestCase;
use Ark4ne\OpenApiWriter\Enums\HttpMethod;
use Ark4ne\OpenApiWriter\Enums\ParameterLocation;
use Ark4ne\OpenApiWriter\Enums\Version;
use Ark4ne\OpenApiWriter\Objects\Schema;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(VariantHeaderPass::class)]
final class VariantHeaderPassTest extends TestCase
{
    public function test_every_operation_of_a_variant_documents_its_header(): void
    {
        $document = (new VariantHeaderPass())->process(Descriptions::document(
            Descriptions::operation(),
            Descriptions::operation(HttpMethod::Post),
        ), $this->variantContext(Variant::ofCase(ApiRevision::Labels, 1)));

        foreach ($document->operations as $operation) {
            $header = self::header($operation, 'Api-Version');
            self::assertSame(ParameterLocation::Header, $header->in);
            self::assertFalse($header->required);
            self::assertSame(['const' => '2026-12-01', 'type' => 'string', 'example' => '2026-12-01'], $header->schema->toArray(Version::V3_1_2));
        }
    }

    public function test_the_header_of_a_variant_is_an_enum_of_one_value_in_3_0(): void
    {
        $document = (new VariantHeaderPass())->process(Descriptions::document(Descriptions::operation()), $this->variantContext(Variant::ofCase(ApiRevision::Labels, 1)));

        self::assertSame(['enum' => ['2026-12-01'], 'type' => 'string', 'example' => '2026-12-01'], self::header($document->operations[0], 'Api-Version')->schema->toArray(Version::V3_0_4));
    }

    public function test_the_head_document_lists_every_variant_in_the_header(): void
    {
        $document = (new VariantHeaderPass())->process(Descriptions::document(Descriptions::operation()), $this->variantContext(null));

        $header = self::header($document->operations[0], 'Api-Version');
        self::assertFalse($header->required);
        self::assertSame(['enum' => ['2026-09-01', '2026-12-01'], 'type' => 'string'], $header->schema->toArray(Version::V3_1_2));
    }

    public function test_a_declared_header_is_not_replaced(): void
    {
        $declared = ParameterDescription::header('api-version', Schema::string()->enum('2026-12-01'))->description('Declared')->required();
        $operation = Descriptions::operation();
        $operation = $operation->request($operation->request->parameters($declared));

        $document = (new VariantHeaderPass())->process(Descriptions::document($operation), $this->variantContext(Variant::ofCase(ApiRevision::Labels, 1)));

        self::assertSame([$declared], $document->operations[0]->request->parameters);
    }

    public function test_the_header_follows_the_parameters_of_the_operation(): void
    {
        $operation = Descriptions::operation();
        $operation = $operation->request($operation->request->parameters(ParameterDescription::query('page', Schema::integer())));

        $document = (new VariantHeaderPass())->process(Descriptions::document($operation), $this->variantContext(Variant::ofCase(ApiRevision::Labels, 1)));

        self::assertSame(['page', 'Api-Version'], array_map(static fn(ParameterDescription $parameter): string => $parameter->name, $document->operations[0]->request->parameters));
    }

    public function test_webhooks_get_no_variant_header(): void
    {
        $webhook = Descriptions::operation(HttpMethod::Post, '/orders')->webhook('order.shipped');

        $document = (new VariantHeaderPass())->process(DocumentDescription::create()->webhooks($webhook), $this->variantContext(Variant::ofCase(ApiRevision::Labels, 1)));

        self::assertSame([], $document->webhooks[0]->request->parameters);
    }

    public function test_variants_without_header_add_no_parameter(): void
    {
        $document = (new VariantHeaderPass())->process(Descriptions::document(Descriptions::operation()), $this->variantContext(Variant::ofCase(ApiRevision::Labels, 1), header: null));

        self::assertSame([], $document->operations[0]->request->parameters);
    }

    public function test_a_document_without_variants_is_unchanged(): void
    {
        $description = Descriptions::document(Descriptions::operation());

        self::assertSame($description, (new VariantHeaderPass())->process($description, $this->context()));
    }

    private function variantContext(?Variant $variant, ?string $header = 'Api-Version'): GenerationContext
    {
        $variants = new VariantsConfiguration([Variant::ofCase(ApiRevision::Initial, 0), Variant::ofCase(ApiRevision::Labels, 1)], head: 'latest', header: $header);
        $document = new DocumentConfiguration('v1', new InfoConfiguration('API', '1'), variants: $variants);

        return GenerationContext::for(new Configuration(['v1' => $document]), $document, variant: $variant);
    }

    private static function header(OperationDescription $operation, string $name): ParameterDescription
    {
        $headers = array_values(array_filter($operation->request->parameters, static fn(ParameterDescription $parameter): bool => $parameter->name === $name && $parameter->in === ParameterLocation::Header));
        self::assertCount(1, $headers);

        return $headers[0];
    }
}
