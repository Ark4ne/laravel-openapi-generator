<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Discovery;

use Ark4ne\OpenApi\Configuration\Configuration;
use Ark4ne\OpenApi\Configuration\DocumentConfiguration;
use Ark4ne\OpenApi\Configuration\InfoConfiguration;
use Ark4ne\OpenApi\Configuration\WebhooksConfiguration;
use Ark4ne\OpenApi\Context\Diagnostic;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Discovery\WebhookClassFinder;
use Ark4ne\OpenApi\Exceptions\InvalidConfigurationException;
use Ark4ne\OpenApi\Tests\Fixtures\PayloadWebhooks\ArticleArchived;
use Ark4ne\OpenApi\Tests\Fixtures\PayloadWebhooks\ArticleDrafted;
use Ark4ne\OpenApi\Tests\Fixtures\PayloadWebhooks\ArticlePublished;
use Ark4ne\OpenApi\Tests\Fixtures\PayloadWebhooks\ArticleReviewed;
use Ark4ne\OpenApi\Tests\Fixtures\PayloadWebhooks\BasePayload;
use Ark4ne\OpenApi\Tests\Fixtures\PayloadWebhooks\LegacyWebhooks;
use Ark4ne\OpenApi\Tests\Fixtures\PayloadWebhooks\Unnamed;
use Ark4ne\OpenApi\Tests\Fixtures\Sampling\Http\Resources\AuthorResource;
use Ark4ne\OpenApi\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(WebhookClassFinder::class)]
final class WebhookClassFinderTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../../Fixtures/PayloadWebhooks';

    private ?string $directory = null;

    protected function tearDown(): void
    {
        if ($this->directory !== null) {
            $files = glob("{$this->directory}/*.php");
            array_map(unlink(...), $files === false ? [] : $files);
            rmdir($this->directory);
        }

        parent::tearDown();
    }

    public function test_the_concrete_resources_marked_webhook_of_a_directory_are_found_sorted(): void
    {
        self::assertSame(
            [ArticleArchived::class, ArticlePublished::class, ArticleReviewed::class, Unnamed::class],
            (new WebhookClassFinder())->find($this->webhookContext(new WebhooksConfiguration([self::FIXTURES]))),
        );
    }

    public function test_a_file_is_scanned_alone_and_a_missing_path_gives_nothing(): void
    {
        $finder = new WebhookClassFinder();

        self::assertSame([ArticleArchived::class], $finder->find($this->webhookContext(new WebhooksConfiguration([self::FIXTURES . '/ArticleArchived.php']))));
        self::assertSame([], $finder->find($this->webhookContext(new WebhooksConfiguration([self::FIXTURES . '/Missing', self::FIXTURES . '/Missing.php']))));
        self::assertSame([], $finder->find($this->webhookContext(new WebhooksConfiguration([self::FIXTURES . '/BasePayload.php', self::FIXTURES . '/LegacyWebhooks.php']))));
    }

    public function test_a_listed_class_is_kept_once_with_those_found(): void
    {
        $found = (new WebhookClassFinder())->find($this->webhookContext(new WebhooksConfiguration([self::FIXTURES . '/ArticleReviewed.php'], [ArticleReviewed::class, ArticleArchived::class])));

        self::assertSame([ArticleArchived::class, ArticleReviewed::class], $found);
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function notPayloadClasses(): iterable
    {
        yield 'a resource without #[Webhook]' => [AuthorResource::class];
        yield 'an abstract payload class' => [BasePayload::class];
        yield 'a controller marked #[Webhook]' => [LegacyWebhooks::class];
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('notPayloadClasses')]
    public function test_a_listed_class_that_is_not_a_payload_class_is_rejected(string $class): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage("openapi.documents.v1.webhooks.classes: expected concrete JsonResource classes marked #[Ark4ne\\OpenApi\\Attributes\\Webhook], got \"{$class}\"");

        (new WebhookClassFinder())->listed($this->document(new WebhooksConfiguration(classes: [$class])));
    }

    public function test_a_class_that_cannot_be_loaded_is_skipped_with_a_warning(): void
    {
        $file = $this->write('Broken.php', "<?php\nnamespace Ark4ne\\OpenApi\\Tests\\Generated;\nuse Ark4ne\\OpenApi\\Attributes\\Webhook;\n#[Webhook('broken')]\nfinal class Broken extends MissingParent {}\n");
        $context = $this->webhookContext(new WebhooksConfiguration([(string) $this->directory]));

        self::assertSame([], $this->withAutoloader($file, 'Broken', static fn(): array => (new WebhookClassFinder())->find($context)));

        $messages = array_map(static fn(Diagnostic $diagnostic): string => $diagnostic->message, $context->diagnostics->all());
        self::assertCount(1, $messages);
        self::assertStringStartsWith("cannot load Ark4ne\\OpenApi\\Tests\\Generated\\Broken ({$file}), skipped from the webhooks: ", $messages[0]);
    }

    public function test_a_class_marked_exclude_is_left_out_found_or_listed(): void
    {
        $finder = new WebhookClassFinder();

        self::assertSame([], $finder->find($this->webhookContext(new WebhooksConfiguration([self::FIXTURES . '/ArticleDrafted.php']))));
        self::assertSame([ArticleArchived::class], $finder->find($this->webhookContext(new WebhooksConfiguration(classes: [ArticleDrafted::class, ArticleArchived::class]))));
    }

    /**
     * @return iterable<string, array{WebhooksConfiguration, string, bool}>
     */
    public static function names(): iterable
    {
        yield 'no mask' => [new WebhooksConfiguration(), 'order.paid', true];
        yield 'included' => [new WebhooksConfiguration(include: ['order.*']), 'order.paid', true];
        yield 'not included' => [new WebhooksConfiguration(include: ['order.*']), 'invoice.issued', false];
        yield 'excluded' => [new WebhooksConfiguration(exclude: ['*.paid']), 'order.paid', false];
        yield 'excluded over included' => [new WebhooksConfiguration(include: ['order.*'], exclude: ['order.paid']), 'order.paid', false];
    }

    #[DataProvider('names')]
    public function test_the_webhooks_of_a_document_are_chosen_by_their_name(WebhooksConfiguration $webhooks, string $name, bool $kept): void
    {
        self::assertSame($kept, (new WebhookClassFinder())->keeps($name, $this->webhookContext($webhooks)));
    }

    public function test_a_file_that_does_not_mention_webhook_is_never_loaded(): void
    {
        $file = $this->write('Silent.php', "<?php\nnamespace Ark4ne\\OpenApi\\Tests\\Generated;\nfinal class Silent extends MissingParent {}\n");
        $context = $this->webhookContext(new WebhooksConfiguration([(string) $this->directory]));

        self::assertSame([], $this->withAutoloader($file, 'Silent', static fn(): array => (new WebhookClassFinder())->find($context)));
        self::assertSame([], $context->diagnostics->all());
    }

    public function test_anonymous_classes_class_constants_and_a_global_namespace_block_are_read(): void
    {
        $file = $this->write('Global.php', "<?php\nnamespace {\n    \$webhook = new class {};\n    \$name = \\Ark4ne\\OpenApi\\Attributes\\Webhook::class;\n    final class GlobalWebhookFixture {}\n}\n");
        $context = $this->webhookContext(new WebhooksConfiguration([(string) $this->directory]));

        self::assertSame([], $this->withAutoloader($file, 'GlobalWebhookFixture', static fn(): array => (new WebhookClassFinder())->find($context), ''));
        self::assertTrue(class_exists('GlobalWebhookFixture', false));
    }

    private function write(string $name, string $contents): string
    {
        $this->directory ??= sys_get_temp_dir() . '/webhook-finder-' . bin2hex(random_bytes(4));

        if (! is_dir($this->directory)) {
            mkdir($this->directory);
        }

        file_put_contents("{$this->directory}/{$name}", $contents);

        return "{$this->directory}/{$name}";
    }

    /**
     * Runs `$run` with an autoloader that loads `$class` from `$file`.
     *
     * @template T
     *
     * @param callable(): T $run
     *
     * @return T
     */
    private function withAutoloader(string $file, string $class, callable $run, string $namespace = 'Ark4ne\\OpenApi\\Tests\\Generated\\'): mixed
    {
        $loader = static function (string $name) use ($file, $class, $namespace): void {
            if ($name === $namespace . $class) {
                require $file;
            }
        };
        spl_autoload_register($loader);

        try {
            return $run();
        } finally {
            spl_autoload_unregister($loader);
        }
    }

    private function webhookContext(WebhooksConfiguration $webhooks): GenerationContext
    {
        $document = $this->document($webhooks);

        return GenerationContext::for(new Configuration(['v1' => $document]), $document);
    }

    private function document(WebhooksConfiguration $webhooks): DocumentConfiguration
    {
        return new DocumentConfiguration('v1', new InfoConfiguration('API', '1.0.0'), webhooks: $webhooks);
    }
}
