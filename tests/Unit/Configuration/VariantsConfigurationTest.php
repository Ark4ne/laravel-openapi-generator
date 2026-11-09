<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Configuration;

use Ark4ne\OpenApi\Configuration\DocumentConfiguration;
use Ark4ne\OpenApi\Configuration\OutputFormat;
use Ark4ne\OpenApi\Configuration\Variant;
use Ark4ne\OpenApi\Configuration\VariantsConfiguration;
use Ark4ne\OpenApi\Exceptions\InvalidConfigurationException;
use Ark4ne\OpenApi\Tests\Fixtures\Variants\ApiRevision;
use Ark4ne\OpenApi\Tests\Fixtures\Variants\Channel;
use Ark4ne\OpenApi\Tests\Fixtures\Variants\NoChannel;
use Ark4ne\OpenApi\Tests\Fixtures\Variants\SlashedRevision;
use Ark4ne\OpenApi\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;

/**
 * The variant dimension of a document (`documents.<name>.variants`).
 */
#[CoversClass(VariantsConfiguration::class)]
#[CoversClass(Variant::class)]
#[CoversClass(DocumentConfiguration::class)]
final class VariantsConfigurationTest extends TestCase
{
    public function test_variants_of_an_enum_follow_the_order_of_its_cases(): void
    {
        $variants = self::variants(['values' => ApiRevision::class]);

        self::assertSame([ApiRevision::Initial, ApiRevision::Labels], array_map(static fn(Variant $variant): mixed => $variant->value, $variants->values));
        self::assertSame([0, 1], array_map(static fn(Variant $variant): int => $variant->position, $variants->values));
    }

    public function test_a_backed_case_is_named_by_its_value(): void
    {
        self::assertSame(['2026-09-01', '2026-12-01'], self::names(self::variants(['values' => ApiRevision::class])));
    }

    public function test_a_pure_case_is_named_by_its_name(): void
    {
        self::assertSame(['Alpha', 'Beta'], self::names(self::variants(['values' => Channel::class])));
    }

    public function test_a_list_of_strings_declares_its_variants_in_order(): void
    {
        $variants = self::variants(['values' => ['v1', 'v2', 'v3']]);

        self::assertSame(['v1', 'v2', 'v3'], array_map(static fn(Variant $variant): mixed => $variant->value, $variants->values));
        self::assertSame(['v1', 'v2', 'v3'], self::names($variants));
        self::assertSame([0, 1, 2], array_map(static fn(Variant $variant): int => $variant->position, $variants->values));
    }

    public function test_variants_of_an_enum_belong_to_the_enum(): void
    {
        $variants = self::variants(['values' => ApiRevision::class])->values;
        self::assertCount(2, $variants);

        foreach ($variants as $variant) {
            self::assertSame(ApiRevision::class, $variant->dimension);
        }
    }

    public function test_variants_of_a_list_belong_to_its_configuration_key(): void
    {
        $variants = self::variants(['values' => ['v1', 'v2']])->values;
        self::assertCount(2, $variants);

        foreach ($variants as $variant) {
            self::assertSame('documents.v1.variants.values', $variant->dimension);
        }
    }

    public function test_the_head_and_the_header_are_read(): void
    {
        $variants = self::variants(['values' => ApiRevision::class, 'head' => 'latest', 'header' => 'Api-Version']);

        self::assertSame('latest', $variants->head);
        self::assertSame('Api-Version', $variants->header);
    }

    public function test_a_header_is_any_http_token(): void
    {
        self::assertSame("X-Api_Version.2!#$%&'*+^`|~", self::variants(['values' => ['v1'], 'header' => "X-Api_Version.2!#$%&'*+^`|~"])->header);
    }

    public function test_there_is_no_head_and_no_header_by_default(): void
    {
        $variants = self::variants(['values' => ApiRevision::class]);

        self::assertNull($variants->head);
        self::assertNull($variants->header);
    }

    public function test_a_false_head_is_no_head(): void
    {
        self::assertNull(self::variants(['values' => ApiRevision::class, 'head' => false])->head);
    }

    public function test_a_variant_is_found_by_its_name(): void
    {
        $variants = self::variants(['values' => ApiRevision::class, 'head' => 'latest']);

        self::assertSame(ApiRevision::Labels, $variants->named('2026-12-01')?->value);
        self::assertNull($variants->named('2027-01-01'));
        self::assertNull($variants->named('latest'));
    }

    public function test_the_names_list_the_variants_then_the_head(): void
    {
        self::assertSame(['2026-09-01', '2026-12-01', 'latest'], self::variants(['values' => ApiRevision::class, 'head' => 'latest'])->names());
        self::assertSame(['2026-09-01', '2026-12-01'], self::variants(['values' => ApiRevision::class])->names());
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[DataProvider('invalidVariants')]
    public function test_an_invalid_variants_entry_is_rejected(array $config, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($message);

        self::variants($config);
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function invalidVariants(): iterable
    {
        yield 'no values' => [[], 'openapi.documents.v1.variants.values: expected'];
        yield 'an unknown class' => [['values' => 'App\Missing'], 'openapi.documents.v1.variants.values: expected'];
        yield 'a class that is not an enum' => [['values' => stdClass::class], 'openapi.documents.v1.variants.values: expected'];
        yield 'an empty list' => [['values' => []], 'openapi.documents.v1.variants.values: expected'];
        yield 'a list of other values' => [['values' => ['v1', 2]], 'openapi.documents.v1.variants.values: expected'];
        yield 'a map' => [['values' => ['a' => 'v1']], 'openapi.documents.v1.variants.values: expected'];
        yield 'duplicate names' => [['values' => ['v1', 'v2', 'v1']], 'openapi.documents.v1.variants.values: expected'];
        yield 'an empty name' => [['values' => ['v1', '']], 'openapi.documents.v1.variants.values: expected'];
        yield 'a head named as a variant' => [['values' => ['v1', 'v2'], 'head' => 'v2'], 'openapi.documents.v1.variants.head: expected'];
        yield 'an empty head' => [['values' => ['v1'], 'head' => ''], 'openapi.documents.v1.variants.head: expected'];
        yield 'a true head' => [['values' => ['v1'], 'head' => true], 'openapi.documents.v1.variants.head: expected'];
        yield 'an empty header' => [['values' => ['v1'], 'header' => ''], 'openapi.documents.v1.variants.header: expected'];
        yield 'an enum without cases' => [['values' => NoChannel::class], 'openapi.documents.v1.variants.values: expected'];
        yield 'a name with a slash' => [['values' => ['v1', 'v1/beta']], 'openapi.documents.v1.variants.values: expected'];
        yield 'a name with a backslash' => [['values' => ['v1\\beta']], 'openapi.documents.v1.variants.values: expected'];
        yield 'a parent path' => [['values' => ['..']], 'openapi.documents.v1.variants.values: expected'];
        yield 'a hidden file name' => [['values' => ['.v1']], 'openapi.documents.v1.variants.values: expected'];
        yield 'a backed case that is not a file name' => [['values' => SlashedRevision::class], 'openapi.documents.v1.variants.values: expected'];
        yield 'a head that is not a file name' => [['values' => ['v1'], 'head' => '../latest'], 'openapi.documents.v1.variants.head: expected'];
        yield 'a header of another type' => [['values' => ['v1'], 'header' => ['Api-Version']], 'openapi.documents.v1.variants.header: expected'];
        yield 'the Accept header' => [['values' => ['v1'], 'header' => 'Accept'], 'openapi.documents.v1.variants.header: expected'];
        yield 'the Content-Type header in lower case' => [['values' => ['v1'], 'header' => 'content-type'], 'openapi.documents.v1.variants.header: expected'];
        yield 'the Authorization header' => [['values' => ['v1'], 'header' => 'Authorization'], 'openapi.documents.v1.variants.header: expected'];
        yield 'a header with a space' => [['values' => ['v1'], 'header' => 'Api Version'], 'openapi.documents.v1.variants.header: expected'];
        yield 'a header with a colon' => [['values' => ['v1'], 'header' => 'Api-Version:'], 'openapi.documents.v1.variants.header: expected'];
    }

    public function test_names_of_letters_digits_dots_dashes_and_underscores_are_accepted(): void
    {
        self::assertSame(['v1.2', '2026-12-01', 'beta_2', 'latest'], self::variants(['values' => ['v1.2', '2026-12-01', 'beta_2'], 'head' => 'latest'])->names());
    }

    public function test_the_name_of_a_generation_is_that_of_its_variant_or_of_the_head(): void
    {
        $variants = self::variants(['values' => ApiRevision::class, 'head' => 'latest']);

        self::assertSame('2026-12-01', $variants->nameOf($variants->values[1]));
        self::assertSame('latest', $variants->nameOf(null));
        self::assertNull(self::variants(['values' => ApiRevision::class])->nameOf(null));
    }

    public function test_a_generation_is_chosen_by_its_name(): void
    {
        $document = DocumentConfiguration::fromArray('v1', ['variants' => ['values' => ApiRevision::class, 'head' => 'latest']]);

        self::assertSame(ApiRevision::Labels, $document->generation('2026-12-01')?->value);
        self::assertNull($document->generation('latest'));
    }

    public function test_an_unknown_generation_is_an_invalid_option(): void
    {
        $document = DocumentConfiguration::fromArray('v1', ['variants' => ['values' => ApiRevision::class, 'head' => 'latest']]);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('--variant: expected one of 2026-09-01, 2026-12-01, latest, got "2027-01-01"');

        $document->generation('2027-01-01');
    }

    public function test_a_generation_of_a_document_without_variants_is_an_invalid_option(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('--variant: expected one of the variants of documents.v1, which has none, got "2026-12-01"');

        DocumentConfiguration::fromArray('v1', [])->generation('2026-12-01');
    }

    public function test_a_variant_in_the_version_of_a_document_without_variants_is_rejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('openapi.documents.v1.info.version: expected');

        DocumentConfiguration::fromArray('v1', ['info' => ['title' => 'API', 'version' => '1.0-{variant}']]);
    }

    public function test_a_variant_in_the_version_of_a_document_with_variants_is_accepted(): void
    {
        $document = DocumentConfiguration::fromArray('v1', ['info' => ['title' => 'API', 'version' => '{variant}'], 'variants' => ['values' => ['v1']]]);

        self::assertSame('{variant}', $document->info->version);
    }

    public function test_a_document_reads_its_variants(): void
    {
        $document = DocumentConfiguration::fromArray('v1', ['variants' => ['values' => ApiRevision::class, 'head' => 'latest']]);

        self::assertNotNull($document->variants);
        self::assertSame(['2026-09-01', '2026-12-01', 'latest'], $document->variants->names());
    }

    public function test_a_document_without_variants_has_no_variant_configuration(): void
    {
        self::assertNull(DocumentConfiguration::fromArray('v1', [])->variants);
    }

    public function test_a_variants_entry_that_is_not_an_array_is_rejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('openapi.documents.v1.variants: expected');

        DocumentConfiguration::fromArray('v1', ['variants' => ApiRevision::class]);
    }

    public function test_a_document_in_another_format_keeps_its_variants(): void
    {
        $document = DocumentConfiguration::fromArray('v1', ['variants' => ['values' => ApiRevision::class]]);

        self::assertNotNull($document->variants);
        self::assertSame($document->variants, $document->withFormat(OutputFormat::Yaml)->variants);
    }

    /**
     * @param array<array-key, mixed> $config
     */
    private static function variants(array $config): VariantsConfiguration
    {
        return VariantsConfiguration::fromArray('documents.v1.variants', $config);
    }

    /**
     * @return list<string>
     */
    private static function names(VariantsConfiguration $variants): array
    {
        return array_map(static fn(Variant $variant): string => $variant->name, $variants->values);
    }
}
