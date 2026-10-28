<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Generation;

use Ark4ne\OpenApi\Configuration\Configuration;
use Ark4ne\OpenApi\Exceptions\InvalidConfigurationException;
use Ark4ne\OpenApi\Generation\Middlewares\BasicAuthMiddlewareMapper;
use Ark4ne\OpenApi\Generation\Passes;
use Ark4ne\OpenApi\Generation\Passes\AuthorizationResponsePass;
use Ark4ne\OpenApi\Generation\Passes\CleanupPass;
use Ark4ne\OpenApi\Generation\Passes\ComponentExtractionPass;
use Ark4ne\OpenApi\Generation\Passes\EquivalentPathsPass;
use Ark4ne\OpenApi\Generation\Passes\MiddlewareSecurityPass;
use Ark4ne\OpenApi\Generation\Passes\NotFoundResponsePass;
use Ark4ne\OpenApi\Generation\Passes\OperationIdPass;
use Ark4ne\OpenApi\Generation\Passes\PathParametersPass;
use Ark4ne\OpenApi\Generation\Passes\TagGroupPass;
use Ark4ne\OpenApi\Generation\Passes\TagPass;
use Ark4ne\OpenApi\Generation\Passes\ThrottleResponsePass;
use Ark4ne\OpenApi\Generation\Passes\ThrownExceptionResponsePass;
use Ark4ne\OpenApi\Generation\Passes\ValidationErrorResponsePass;
use Ark4ne\OpenApi\Tests\Support\Descriptions;
use Ark4ne\OpenApi\Tests\TestCase;
use Ark4ne\OpenApiWriter\Enums\HttpMethod;
use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;

#[CoversClass(Passes::class)]
final class PassesTest extends TestCase
{
    public function test_the_default_chain_runs_in_its_order(): void
    {
        self::assertSame([
            ThrownExceptionResponsePass::class,
            MiddlewareSecurityPass::class,
            AuthorizationResponsePass::class,
            NotFoundResponsePass::class,
            ValidationErrorResponsePass::class,
            ThrottleResponsePass::class,
            TagPass::class,
            TagGroupPass::class,
            OperationIdPass::class,
            ComponentExtractionPass::class,
            EquivalentPathsPass::class,
            PathParametersPass::class,
            CleanupPass::class,
        ], array_map(get_class(...), Passes::default()->all()));
    }

    public function test_the_configured_middleware_mappers_replace_the_built_in_ones(): void
    {
        $context = $this->context();
        $configuration = new Configuration($context->configuration->documents, middlewares: ['auth' => [BasicAuthMiddlewareMapper::class]]);

        $document = Passes::default($configuration)->process(Descriptions::document(Descriptions::operation(HttpMethod::Get, '/api/users', 'auth:sanctum')), $context);

        self::assertSame(['basic'], array_keys($document->securitySchemes));
    }

    public function test_a_configured_middleware_mapper_must_be_one(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        Passes::default(new Configuration([], middlewares: ['auth' => [stdClass::class]]));
    }

    public function test_every_pass_runs_on_the_document(): void
    {
        $document = Passes::default()->process(Descriptions::document(Descriptions::operation()->operationId('users.index')->tags('users')), $this->context());

        self::assertSame(['users'], array_map(static fn($tag): string => $tag->name, $document->tags));
    }
}
