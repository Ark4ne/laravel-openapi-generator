<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Output;

use Ark4ne\OpenApi\Configuration\Configuration;
use Ark4ne\OpenApi\Configuration\DocumentConfiguration;
use Ark4ne\OpenApi\Configuration\InfoConfiguration;
use Ark4ne\OpenApi\Configuration\OutputConfiguration;
use Ark4ne\OpenApi\Configuration\OutputFormat;
use Ark4ne\OpenApi\Configuration\Variant;
use Ark4ne\OpenApi\Configuration\VariantsConfiguration;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Exceptions\GenerationFailedException;
use Ark4ne\OpenApi\Output\DocumentValidator;
use Ark4ne\OpenApi\Output\DocumentWriter;
use Ark4ne\OpenApi\Tests\Fixtures\Variants\ApiRevision;
use Ark4ne\OpenApi\Tests\TestCase;
use Ark4ne\OpenApiWriter\Enums\Strictness;
use Ark4ne\OpenApiWriter\Enums\Version;
use Ark4ne\OpenApiWriter\Objects\Info;
use Ark4ne\OpenApiWriter\Objects\OpenApi;
use Ark4ne\OpenApiWriter\Objects\Operation;
use Ark4ne\OpenApiWriter\Objects\PathItem;
use Ark4ne\OpenApiWriter\Objects\Response;
use Ark4ne\OpenApiWriter\Validation\OpisJsonSchemaValidator;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DocumentWriter::class)]
#[CoversClass(DocumentValidator::class)]
#[CoversClass(GenerationFailedException::class)]
final class DocumentWriterTest extends TestCase
{
    public function test_the_document_is_written_as_readable_json_on_its_disk(): void
    {
        Storage::fake('docs');

        $path = $this->writer()->write($this->document(), $this->writing(new OutputConfiguration('docs', 'openapi', '{document}.json')));

        self::assertSame('openapi/v1.json', $path);
        self::assertSame((string) json_encode($this->document()->toArray(Version::V3_1_2), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n", Storage::disk('docs')->get($path));
    }

    public function test_contents_already_written_are_stored_without_writing_them_again(): void
    {
        Storage::fake('docs');
        $context = $this->writing(new OutputConfiguration('docs', 'openapi', '{document}.json'));

        $path = $this->writer()->store('{}', $context);

        self::assertSame('openapi/v1.json', $path);
        self::assertSame('{}', Storage::disk('docs')->get($path));
        self::assertCount(0, $context->diagnostics);
    }

    public function test_the_document_can_be_written_as_yaml(): void
    {
        Storage::fake('docs');

        $path = $this->writer()->write($this->document(), $this->writing(new OutputConfiguration('docs', 'openapi', '{document}.yaml', OutputFormat::Yaml)));

        self::assertStringStartsWith("openapi: 3.1.2\n", (string) Storage::disk('docs')->get($path));
    }

    public function test_the_file_name_holds_the_locale(): void
    {
        self::assertSame('openapi/v1.fr.json', $this->writer()->path($this->writing(new OutputConfiguration(file: '{document}.json'), locale: 'fr')));
        self::assertSame('openapi/fr/v1.json', $this->writer()->path($this->writing(new OutputConfiguration(file: '{locale}/{document}.json'), locale: 'fr')));
        self::assertSame('openapi/v1.json', $this->writer()->path($this->writing(new OutputConfiguration(file: '{document}{locale}.json'))));
    }

    public function test_the_file_name_holds_the_variant(): void
    {
        $labels = Variant::ofCase(ApiRevision::Labels, 1);

        self::assertSame('openapi/v1-2026-12-01.json', $this->writer()->path($this->variantWriting(new OutputConfiguration(file: '{document}-{variant}.json'), $labels)));
        self::assertSame('openapi/2026-12-01/v1.json', $this->writer()->path($this->variantWriting(new OutputConfiguration(file: '{variant}/{document}.json'), $labels)));
    }

    public function test_the_variant_is_inserted_in_a_file_name_without_placeholder(): void
    {
        self::assertSame('openapi/v1.2026-12-01.json', $this->writer()->path($this->variantWriting(new OutputConfiguration(file: '{document}.json'), Variant::ofCase(ApiRevision::Labels, 1))));
        self::assertSame('openapi/v1.2026-12-01', $this->writer()->path($this->variantWriting(new OutputConfiguration(file: '{document}'), Variant::ofCase(ApiRevision::Labels, 1))));
    }

    public function test_the_file_name_of_the_head_holds_its_name(): void
    {
        self::assertSame('openapi/v1-latest.json', $this->writer()->path($this->variantWriting(new OutputConfiguration(file: '{document}-{variant}.json'), null)));
        self::assertSame('openapi/v1.latest.json', $this->writer()->path($this->variantWriting(new OutputConfiguration(file: '{document}.json'), null)));
    }

    public function test_the_variant_comes_before_the_locale_in_a_file_name_without_placeholders(): void
    {
        self::assertSame('openapi/v1.2026-12-01.fr.json', $this->writer()->path($this->variantWriting(new OutputConfiguration(file: '{document}.json'), Variant::ofCase(ApiRevision::Labels, 1), 'fr')));
    }

    public function test_the_variant_comes_before_a_locale_placeholder_in_a_file_name_without_variant_placeholder(): void
    {
        self::assertSame('openapi/v1.2026-12-01.fr.json', $this->writer()->path($this->variantWriting(new OutputConfiguration(file: '{document}.{locale}.json'), Variant::ofCase(ApiRevision::Labels, 1), 'fr')));
        self::assertSame('openapi/fr/v1.2026-12-01.json', $this->writer()->path($this->variantWriting(new OutputConfiguration(file: '{locale}/{document}.json'), Variant::ofCase(ApiRevision::Labels, 1), 'fr')));
    }

    public function test_a_file_name_without_variants_ignores_the_placeholder(): void
    {
        self::assertSame('openapi/v1.json', $this->writer()->path($this->writing(new OutputConfiguration(file: '{document}{variant}.json'))));
    }

    public function test_the_conversions_of_the_writer_become_diagnostics(): void
    {
        $context = $this->writing(version: Version::V3_0_4, strictness: Strictness::Lenient);
        $document = $this->document()->info(Info::create()->title('API')->version('1')->summary('Dropped in 3.0'));

        $this->writer()->contents($document, $context);

        self::assertStringStartsWith('[warning] writing: [dropped] /info/summary', (string) $context->diagnostics->all()[0]);
    }

    public function test_a_strict_writing_failure_carries_the_diagnostics(): void
    {
        $context = $this->writing(version: Version::V3_0_4);
        $document = $this->document()->info(Info::create()->title('API')->version('1')->summary('Not in 3.0'));

        try {
            $this->writer()->contents($document, $context);
            self::fail('The strict writing should fail.');
        } catch (GenerationFailedException $exception) {
            self::assertStringStartsWith('The document v1 cannot be generated', $exception->getMessage());
            self::assertStringStartsWith('[error] writing: [unsupported] /info/summary', (string) $exception->diagnostics()->all()[0]);
        }
    }

    public function test_the_violations_of_the_document_become_diagnostics(): void
    {
        $context = $this->writing();
        $duplicated = $this->document()->addPaths(PathItem::create('/other')->operations(Operation::get()->operationId('users')->responses(Response::ok())));

        $violations = (new DocumentValidator(new OpisJsonSchemaValidator()))->validate($duplicated, $context);

        self::assertFalse($violations->isValid());
        self::assertStringStartsWith('[error] validation: [error] /paths/~1other/get/operationId: operationId "users" is already used', (string) $context->diagnostics->all()[0]);
        self::assertTrue((new DocumentValidator(new OpisJsonSchemaValidator()))->validate($this->document(), $this->writing())->isValid());
    }

    private function writer(): DocumentWriter
    {
        $factory = $this->app?->make(Factory::class);
        self::assertInstanceOf(Factory::class, $factory);

        return new DocumentWriter($factory);
    }

    private function document(): OpenApi
    {
        return OpenApi::create()
            ->info(Info::create()->title('API')->version('1'))
            ->paths(PathItem::create('/users')->operations(Operation::get()->operationId('users')->responses(Response::ok())));
    }

    private function writing(OutputConfiguration $output = new OutputConfiguration(), ?string $locale = null, Version $version = Version::V3_1_2, Strictness $strictness = Strictness::Strict): GenerationContext
    {
        $document = new DocumentConfiguration('v1', new InfoConfiguration('API', '1'), $version, $strictness, output: $output);

        return GenerationContext::for(new Configuration(['v1' => $document]), $document, $locale);
    }

    /**
     * A document of the revisions of `ApiRevision` with a `latest` head, generated for `$variant` (`null`: the head).
     */
    private function variantWriting(OutputConfiguration $output, ?Variant $variant, ?string $locale = null): GenerationContext
    {
        $variants = new VariantsConfiguration([Variant::ofCase(ApiRevision::Initial, 0), Variant::ofCase(ApiRevision::Labels, 1)], head: 'latest');
        $document = new DocumentConfiguration('v1', new InfoConfiguration('API', '1'), output: $output, variants: $variants);

        return GenerationContext::for(new Configuration(['v1' => $document]), $document, $locale, $variant);
    }
}
