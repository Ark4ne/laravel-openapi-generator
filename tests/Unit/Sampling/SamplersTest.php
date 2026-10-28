<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Sampling;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Sampling\EnumSampler;
use Ark4ne\OpenApi\Sampling\FakeObject;
use Ark4ne\OpenApi\Sampling\FakeObjectSampler;
use Ark4ne\OpenApi\Sampling\FakeValues;
use Ark4ne\OpenApi\Sampling\ModelFactorySampler;
use Ark4ne\OpenApi\Sampling\NullSamplingSession;
use Ark4ne\OpenApi\Sampling\ResourceFactorySampler;
use Ark4ne\OpenApi\Sampling\SampleRequest;
use Ark4ne\OpenApi\Sampling\Samplers;
use Ark4ne\OpenApi\Tests\Fixtures\Extensions\TeapotSampler;
use Ark4ne\OpenApi\Tests\Fixtures\Routing\Enums\Priority;
use Ark4ne\OpenApi\Tests\Fixtures\Routing\Enums\Status;
use Ark4ne\OpenApi\Tests\Fixtures\Routing\Models\User;
use Ark4ne\OpenApi\Tests\Fixtures\Sampling\Http\Resources\ArticleResource;
use Ark4ne\OpenApi\Tests\Fixtures\Sampling\Http\Resources\BrokenFactoryResource;
use Ark4ne\OpenApi\Tests\Fixtures\Sampling\Http\Resources\FactoryResource;
use Ark4ne\OpenApi\Tests\Fixtures\Sampling\Http\Resources\InvokedResource;
use Ark4ne\OpenApi\Tests\Fixtures\Sampling\Models\Article;
use Ark4ne\OpenApi\Tests\Fixtures\Sampling\Models\Author;
use Ark4ne\OpenApi\Tests\Fixtures\Types\Signatures;
use Ark4ne\OpenApi\Types\TypeResolver;
use Carbon\CarbonInterface;
use Countable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionClass;

#[CoversClass(Samplers::class)]
#[CoversClass(ResourceFactorySampler::class)]
#[CoversClass(ModelFactorySampler::class)]
#[CoversClass(EnumSampler::class)]
#[CoversClass(FakeObjectSampler::class)]
#[CoversClass(FakeObject::class)]
#[CoversClass(FakeValues::class)]
final class SamplersTest extends SamplingTestCase
{
    public function test_the_samplers_are_tried_by_decreasing_priority(): void
    {
        self::assertSame(
            [ResourceFactorySampler::class, ModelFactorySampler::class, EnumSampler::class, FakeObjectSampler::class],
            array_map(get_class(...), $this->samplers()->all()),
        );
    }

    public function test_the_resource_factory_calls_its_method_with_its_parameters(): void
    {
        $samples = $this->sample(new SampleRequest(Article::class, ArticleResource::class, 2));

        self::assertCount(2, $samples);
        self::assertInstanceOf(Article::class, $samples[0]);
        self::assertSame('From the factory', $samples[0]->title);
    }

    public function test_the_resource_factory_is_invoked_without_method(): void
    {
        $sample = $this->sample(new SampleRequest(Article::class, InvokedResource::class))[0];

        self::assertInstanceOf(Article::class, $sample);
        self::assertSame('Invoked', $sample->title);
    }

    public function test_the_resource_factory_may_be_a_model_factory(): void
    {
        $sample = $this->sample(new SampleRequest(Article::class, FactoryResource::class))[0];

        self::assertInstanceOf(Article::class, $sample);
        self::assertSame('From the model factory', $sample->title);
        self::assertFalse($sample->exists);
    }

    public function test_the_model_factory_creates_inside_a_session(): void
    {
        $session = $this->samplingSession();

        $sample = $session->run(fn(): mixed => $this->sample(new SampleRequest(Article::class), samplers: Samplers::default($session, new TypeResolver()))[0], $this->context());

        self::assertInstanceOf(Article::class, $sample);
        self::assertTrue($sample->exists);
        self::assertGreaterThan(0, $sample->getKey());
        self::assertSame(0, DB::table('articles')->count());
    }

    public function test_the_model_factory_makes_outside_a_session(): void
    {
        $samples = $this->sample(new SampleRequest(Article::class, count: 2));

        self::assertCount(2, $samples);
        self::assertInstanceOf(Article::class, $samples[1]);
        self::assertFalse($samples[1]->exists);
    }

    public function test_an_enum_gives_its_cases(): void
    {
        self::assertSame([Status::Draft, Status::Published, Status::Draft], $this->sample(new SampleRequest(Status::class, count: 3)));
    }

    public function test_a_model_without_factory_is_filled_from_its_declared_types(): void
    {
        $sample = $this->sample(new SampleRequest(Author::class))[0];

        self::assertInstanceOf(Author::class, $sample);
        self::assertSame(1, $sample->id);
        self::assertSame('string', $sample->name);
        self::assertTrue($sample->active);
        self::assertInstanceOf(CarbonInterface::class, $sample->born_at);
        self::assertSame(1.5, $sample->score);
    }

    public function test_any_other_class_gives_a_fake_object_answering_from_its_types(): void
    {
        $sample = $this->sample(new SampleRequest(Signatures::class))[0];

        self::assertInstanceOf(FakeObject::class, $sample);
        self::assertSame(Signatures::class, $sample->class());
        self::assertSame(1, $sample->__get('count'));
        self::assertSame(1, $sample->__call('native', []));
        // `Author` is the alias of the User model in the fixture.
        self::assertSame([User::class, User::class], array_map(get_debug_type(...), (array) $sample->__get('authors')));
        self::assertNull($sample->__get('unknown'));
        self::assertNull($sample->__call('unknown', []));
        self::assertTrue($sample->__isset('authors'));
        self::assertSame(['authors', 'count'], array_keys($sample->jsonSerialize()));
    }

    public function test_the_fake_values_follow_the_declared_types(): void
    {
        $resolver = new TypeResolver();
        $value = static fn(string $type): mixed => (new FakeValues($resolver))->for($resolver->parse($type, new ReflectionClass(Signatures::class)));

        self::assertSame(1, $value('int'));
        self::assertSame(1.5, $value('float'));
        self::assertSame('string', $value('string'));
        self::assertTrue($value('bool'));
        self::assertNull($value('null'));
        self::assertSame(1, $value('?int'));
        self::assertSame('draft', $value("'draft'|'published'"));
        self::assertSame([1, 1], $value('list<int>'));
        self::assertSame(['id' => 1, 'name' => 'string'], $value('array{id: int, name?: string}'));
        self::assertSame(Priority::Low, $value('\\' . Priority::class));
        $date = $value('\DateTimeInterface');
        self::assertInstanceOf(DateTimeInterface::class, $date);
        self::assertSame('2024-01-01T00:00:00+00:00', $date->format(DATE_ATOM));
        self::assertNull($value('mixed'));
    }

    public function test_a_sampler_that_fails_lets_the_next_one_try(): void
    {
        $context = $this->context();

        $samples = $this->sample(new SampleRequest(Article::class, BrokenFactoryResource::class), $context);

        self::assertInstanceOf(Article::class, $samples[0]);
        self::assertStringStartsWith('[warning] sampling: #[ResourceFactory] of ' . BrokenFactoryResource::class . ' failed: ', (string) $context->diagnostics->all()[0]);
    }

    public function test_no_sample_for_an_interface(): void
    {
        self::assertNull($this->samplers()->sample(new SampleRequest(Countable::class), $this->context()));
    }

    /**
     * @return list<mixed>
     */
    private function sample(SampleRequest $request, ?GenerationContext $context = null, ?Samplers $samplers = null): array
    {
        $samples = ($samplers ?? $this->samplers())->sample($request, $context ?? $this->context());
        self::assertIsArray($samples);

        return $samples;
    }

    private function samplers(): Samplers
    {
        return Samplers::default($this->samplingSession(), new TypeResolver());
    }

    public function test_other_samplers_join_by_priority(): void
    {
        $samplers = Samplers::default(new NullSamplingSession(), new TypeResolver())->with(new TeapotSampler());

        self::assertInstanceOf(TeapotSampler::class, $samplers->all()[0]);
    }
}
