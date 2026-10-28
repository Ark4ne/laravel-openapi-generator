<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Extraction\Operations;

use Ark4ne\OpenApi\Description\OperationDescription;
use Ark4ne\OpenApi\Description\TagDescription;
use Ark4ne\OpenApi\Extraction\Operations\AttributeOperationExtractor;
use Ark4ne\OpenApi\Extraction\Operations\NamingOperationExtractor;
use Ark4ne\OpenApi\Extraction\Operations\OperationExtractors;
use Ark4ne\OpenApi\Extraction\Operations\PhpDocOperationExtractor;
use Ark4ne\OpenApi\Extraction\Operations\TranslationOperationExtractor;
use Ark4ne\OpenApi\Extraction\Strategies\BuiltInOperationIdStrategy;
use Ark4ne\OpenApi\Extraction\Strategies\BuiltInSummaryStrategy;
use Ark4ne\OpenApi\Extraction\Strategies\BuiltInTagStrategy;
use Ark4ne\OpenApi\Tests\Fixtures\Metadata\Controllers\UserController;
use Ark4ne\OpenApi\Tests\TestCase;
use Illuminate\Contracts\Translation\Translator;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(OperationExtractors::class)]
#[CoversClass(NamingOperationExtractor::class)]
#[CoversClass(PhpDocOperationExtractor::class)]
#[CoversClass(AttributeOperationExtractor::class)]
#[CoversClass(TranslationOperationExtractor::class)]
final class OperationExtractorsTest extends TestCase
{
    public function test_the_naming_strategies_give_the_defaults(): void
    {
        $this->router()->get('api/users', [UserController::class, 'index'])->name('users.index');
        $operation = $this->operation();

        $description = $this->naming()->extract($operation, $operation->describe(), $this->context());

        self::assertSame('List the users.', $description->summary);
        self::assertSame(['User'], $description->tags);
        self::assertSame('users.index', $description->operationId);
    }

    public function test_the_phpdoc_gives_the_description_and_the_deprecation(): void
    {
        $this->router()->get('api/users/{user}', [UserController::class, 'show']);
        $operation = $this->operation();

        $description = (new PhpDocOperationExtractor())->extract($operation, $operation->describe(), $this->context());

        self::assertNull($description->description);
        self::assertTrue($description->deprecated);
    }

    public function test_the_phpdoc_extractor_needs_a_phpdoc(): void
    {
        $this->router()->get('api/missing', [UserController::class, 'missing']);

        self::assertFalse((new PhpDocOperationExtractor())->supports($this->operation()));
        self::assertFalse((new AttributeOperationExtractor())->supports($this->operation()));
    }

    public function test_the_attributes_of_the_action_override_those_of_the_class(): void
    {
        $this->router()->get('api/users/{user}', [UserController::class, 'show']);
        $operation = $this->operation();

        $description = (new AttributeOperationExtractor())->extract($operation, $operation->describe()->summary('From the doc')->tags('User'), $this->context());

        self::assertSame('Show one user', $description->summary);
        self::assertSame('Default description of the controller', $description->description);
        self::assertSame('users.find', $description->operationId);
        self::assertSame(['url' => 'https://example.com/users', 'description' => 'Users guide'], $description->externalDocs);
        self::assertSame(['User', 'users'], $description->tags);
        self::assertEquals([TagDescription::create('users')->description('Users of the API')], $description->declaredTags);
    }

    public function test_the_attribute_of_the_class_fills_only_what_is_empty(): void
    {
        $this->router()->get('api/users', [UserController::class, 'index']);
        $operation = $this->operation();

        $description = (new AttributeOperationExtractor())->extract($operation, $operation->describe()->description('From the doc'), $this->context());

        self::assertSame('From the doc', $description->description);
    }

    public function test_operation_tags_replace_the_strategy_tags_and_tag_attributes_add_theirs(): void
    {
        $this->router()->post('api/users', [UserController::class, 'store']);
        $operation = $this->operation();

        $description = (new AttributeOperationExtractor())->extract($operation, $operation->describe()->tags('User')->deprecated(), $this->context());

        self::assertSame(['accounts', 'users', 'admin'], $description->tags);
        self::assertFalse($description->deprecated);
        self::assertEquals(
            TagDescription::create('admin')->parent('accounts')->summary('Administration')->kind('nav')->externalDocs(['url' => 'https://example.com/admin']),
            $description->declaredTags[1],
        );
    }

    public function test_the_native_deprecated_attribute_deprecates_the_operation(): void
    {
        $this->router()->get('api/legacy', [UserController::class, 'legacy']);
        $operation = $this->operation();

        self::assertTrue((new AttributeOperationExtractor())->extract($operation, $operation->describe(), $this->context())->deprecated);
    }

    public function test_the_extractors_run_by_increasing_priority(): void
    {
        $this->router()->get('api/users/{user}', [UserController::class, 'show'])->name('users.show');
        $extractors = new OperationExtractors(new AttributeOperationExtractor(), new PhpDocOperationExtractor(), $this->naming());
        $operation = $this->operation();

        $description = $extractors->extract($operation, $operation->describe(), $this->context());

        self::assertSame([NamingOperationExtractor::class, PhpDocOperationExtractor::class, AttributeOperationExtractor::class], array_map(get_class(...), $extractors->all()));
        self::assertSame('Show one user', $description->summary);
        self::assertSame('users.find', $description->operationId);
        self::assertTrue($description->deprecated);
    }

    public function test_the_default_extractors_translate_last(): void
    {
        $translator = $this->app?->make(Translator::class);
        self::assertInstanceOf(Translator::class, $translator);
        $translator->addLines(['docs.users' => 'Utilisateurs', 'docs.list' => 'Lister les utilisateurs'], 'fr');
        $this->router()->get('api/users', [UserController::class, 'index']);
        $context = $this->context(locale: 'fr');
        $operation = $this->operation($context);
        $extractors = OperationExtractors::default(new BuiltInSummaryStrategy(), new BuiltInTagStrategy(), new BuiltInOperationIdStrategy(), $translator);

        $description = $extractors->extract(
            $operation,
            OperationDescription::create($operation->method, $operation->path)->summary('docs.list')->declaredTags(TagDescription::create('docs.users')->description('docs.users')),
            $context,
        );

        self::assertSame(TranslationOperationExtractor::class, get_class($extractors->all()[3]));
        self::assertSame('List the users.', $description->summary);
        self::assertSame('Sorted by name.', $description->description);
        self::assertSame(['User', 'users'], $description->tags);
        self::assertSame('Utilisateurs', $description->declaredTags[0]->description);
    }

    public function test_the_translation_extractor_translates_keys_and_keeps_other_texts(): void
    {
        $translator = $this->app?->make(Translator::class);
        self::assertInstanceOf(Translator::class, $translator);
        $translator->addLines(['docs.users' => 'Utilisateurs', 'docs.list' => 'Lister'], 'fr');
        $this->router()->get('api/users', [UserController::class, 'index']);
        $context = $this->context(locale: 'fr');
        $operation = $this->operation($context);
        $description = OperationDescription::create($operation->method, $operation->path)
            ->summary('docs.list')
            ->description('Plain text')
            ->tags('docs.users')
            ->declaredTags(TagDescription::create('docs.users')->summary('docs.list'));

        $translated = (new TranslationOperationExtractor($translator))->extract($operation, $description, $context);

        self::assertSame('Lister', $translated->summary);
        self::assertSame('Plain text', $translated->description);
        self::assertSame(['Utilisateurs'], $translated->tags);
        self::assertSame('Utilisateurs', $translated->declaredTags[0]->name);
        self::assertSame('Lister', $translated->declaredTags[0]->summary);
        self::assertNull($translated->declaredTags[0]->description);
    }

    private function naming(): NamingOperationExtractor
    {
        return new NamingOperationExtractor(new BuiltInSummaryStrategy(), new BuiltInTagStrategy(), new BuiltInOperationIdStrategy());
    }
}
