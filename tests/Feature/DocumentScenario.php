<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Feature;

use Ark4ne\OpenApi\Configuration\Configuration;
use Ark4ne\OpenApi\Generation\Generator;
use Ark4ne\OpenApi\Output\DocumentValidator;
use Ark4ne\OpenApi\Output\DocumentWriter;
use Ark4ne\OpenApi\Tests\Unit\Sampling\SamplingTestCase;
use Ark4ne\OpenApiWriter\Validation\OpisJsonSchemaValidator;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Contracts\Translation\Translator;

/**
 * A functional scenario: the documents of its application, generated in 3.0, 3.1 and 3.2, valid against the official
 * schemas, compared with `expected/<line>.json` next to the test (rewritten by `composer fixtures:update`).
 */
abstract class DocumentScenario extends SamplingTestCase
{
    private const VERSIONS = ['3.0' => '3.0.4', '3.1' => '3.1.2', '3.2' => '3.2.0'];

    /**
     * @param array<string, mixed> $document entries of the document configuration, over the scenario's defaults
     */
    protected function assertExpectedDocuments(string $directory, array $document = []): void
    {
        foreach (self::VERSIONS as $line => $version) {
            $configuration = Configuration::fromArray([
                'documents' => ['scenario' => [
                    'openapi' => $version,
                    'strict' => false,
                    'info' => ['title' => 'Scenario', 'version' => '1.0.0'],
                    'routes' => ['include' => null],
                    ...$document,
                ]],
                'sampling' => ['examples' => false],
            ]);
            $generated = $this->generator()->generate($configuration, 'scenario');
            $violations = (new DocumentValidator(new OpisJsonSchemaValidator()))->validate($generated->openApi, $generated->context);

            self::assertSame([], array_map(strval(...), $violations->errors()), "{$line}: the document must be valid");

            $factory = $this->app?->make(Factory::class);
            self::assertInstanceOf(Factory::class, $factory);
            $written = (new DocumentWriter($factory))->contents($generated->openApi, $generated->context);
            $expected = "{$directory}/expected/{$line}.json";

            if (getenv('OPENAPI_GENERATOR_UPDATE_FIXTURES') === '1') {
                @mkdir(dirname($expected), 0o755, true);
                file_put_contents($expected, $written);
            }

            self::assertFileExists($expected);
            self::assertSame((string) file_get_contents($expected), $written, "{$line}: the document differs from {$expected}");
        }
    }

    protected function generator(): Generator
    {
        $container = $this->app?->make(Container::class);
        self::assertInstanceOf(Container::class, $container);
        $translator = $this->app?->make(Translator::class);

        return Generator::make($this->router(), $container, $this->samplingSession(), $translator instanceof Translator ? $translator : null);
    }
}
