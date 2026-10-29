<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Generation;

use Ark4ne\OpenApi\Configuration\DocumentConfiguration;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\DocumentDescription;
use Ark4ne\OpenApi\Description\OperationDescription;
use Ark4ne\OpenApi\Description\ParameterDescription;
use Ark4ne\OpenApi\Description\SecurityDescription;
use Ark4ne\OpenApi\Generation\Middleware;
use Ark4ne\OpenApi\Generation\MiddlewareMappers;
use Ark4ne\OpenApi\Generation\Middlewares\AuthMiddlewareMapper;
use Ark4ne\OpenApi\Generation\Middlewares\BasicAuthMiddlewareMapper;
use Ark4ne\OpenApi\Generation\Middlewares\CsrfMiddlewareMapper;
use Ark4ne\OpenApi\Generation\Middlewares\PassportScopesMiddlewareMapper;
use Ark4ne\OpenApi\Generation\Middlewares\SanctumAbilitiesMiddlewareMapper;
use Ark4ne\OpenApi\Generation\Middlewares\Scopes;
use Ark4ne\OpenApi\Generation\Middlewares\SignedMiddlewareMapper;
use Ark4ne\OpenApi\Generation\Passes\MiddlewareSecurityPass;
use Ark4ne\OpenApi\Generation\SecuritySchemes;
use Ark4ne\OpenApi\Tests\Support\Descriptions;
use Ark4ne\OpenApi\Tests\TestCase;
use Ark4ne\OpenApiWriter\Enums\HttpMethod;
use Ark4ne\OpenApiWriter\Enums\ParameterLocation;
use Ark4ne\OpenApiWriter\Enums\Version;
use Illuminate\Auth\Middleware\Authenticate;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(MiddlewareSecurityPass::class)]
#[CoversClass(MiddlewareMappers::class)]
#[CoversClass(Middleware::class)]
#[CoversClass(AuthMiddlewareMapper::class)]
#[CoversClass(BasicAuthMiddlewareMapper::class)]
#[CoversClass(CsrfMiddlewareMapper::class)]
#[CoversClass(SignedMiddlewareMapper::class)]
#[CoversClass(PassportScopesMiddlewareMapper::class)]
#[CoversClass(SanctumAbilitiesMiddlewareMapper::class)]
#[CoversClass(SecuritySchemes::class)]
#[CoversClass(Scopes::class)]
final class MiddlewareSecurityPassTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string|null}>
     */
    public static function middlewares(): iterable
    {
        yield 'alias' => ['auth:sanctum', 'auth', 'sanctum'];
        yield 'class of an alias' => [Authenticate::class . ':api,web', 'auth', 'api'];
        yield 'without argument' => ['throttle', 'throttle', null];
        yield 'several arguments' => ['can:update,post', 'can', 'update'];
    }

    #[DataProvider('middlewares')]
    public function test_a_declared_middleware_is_read_with_its_alias_and_arguments(string $declared, string $name, ?string $first): void
    {
        $middleware = Middleware::parse($declared);

        self::assertSame($name, $middleware->name);
        self::assertSame($first, $middleware->parameters[0] ?? null);
    }

    /**
     * @return iterable<string, array{string, list<array<string, list<string>>>, list<string>}>
     */
    public static function schemes(): iterable
    {
        yield 'sanctum: bearer or the cookie of a SPA' => ['auth:sanctum', [['sanctum' => []], ['session' => [], 'xsrf' => []]], ['sanctum', 'session', 'xsrf']];
        yield 'passport' => ['auth:api', [['passport' => []]], ['passport']];
        yield 'default guard' => ['auth', [['bearer' => []]], ['bearer']];
        yield 'another guard' => ['auth:jwt', [['jwt' => []]], ['jwt']];
        yield 'basic' => ['auth.basic', [['basic' => []]], ['basic']];
    }

    /**
     * @param list<array<string, list<string>>> $security
     * @param list<string> $schemes
     */
    #[DataProvider('schemes')]
    public function test_an_authentication_middleware_requires_its_scheme(string $middleware, array $security, array $schemes): void
    {
        $document = $this->process(Descriptions::operation(HttpMethod::Get, '/api/users', $middleware));

        self::assertSame($security, array_map(static fn(SecurityDescription $requirement): array => $requirement->schemes, $document->operations[0]->security ?? []));
        self::assertSame($schemes, array_keys($document->securitySchemes));
    }

    public function test_the_schemes_are_written_as_laravel_reads_them(): void
    {
        $schemes = $this->process(Descriptions::operation(HttpMethod::Get, '/api/users', 'auth:sanctum', 'auth.basic', 'auth:api'))->securitySchemes;

        self::assertSame(['type' => 'http', 'scheme' => 'bearer'], $schemes['sanctum']->toArray());
        self::assertSame(['type' => 'apiKey', 'name' => 'laravel_session', 'in' => 'cookie'], $schemes['session']->toArray());
        self::assertSame(['type' => 'apiKey', 'name' => 'X-XSRF-TOKEN', 'in' => 'header'], $schemes['xsrf']->toArray());
        self::assertSame(['type' => 'http', 'scheme' => 'basic'], $schemes['basic']->toArray());
        self::assertSame('{"type":"oauth2","flows":{"authorizationCode":{"authorizationUrl":"/oauth/authorize","tokenUrl":"/oauth/token","scopes":{}}}}', $schemes['passport']->toJson());
    }

    public function test_the_configured_schemes_replace_the_inferred_ones(): void
    {
        $context = $this->context();
        $context = new GenerationContext($context->configuration, new DocumentConfiguration('v1', $context->document->info, securitySchemes: [
            'passport' => ['type' => 'oauth2', 'description' => 'Passport', 'flows' => ['clientCredentials' => ['tokenUrl' => 'https://example.com/oauth/token', 'scopes' => ['read' => 'Read']]]],
            'oidc' => ['type' => 'openIdConnect', 'openIdConnectUrl' => 'https://example.com/.well-known/openid-configuration'],
        ], security: ['oidc']));

        $document = $this->process(Descriptions::operation(HttpMethod::Get, '/api/users', 'auth:api'), $context);

        self::assertSame('{"type":"oauth2","description":"Passport","flows":{"clientCredentials":{"tokenUrl":"https://example.com/oauth/token","scopes":{"read":"Read"}}}}', $document->securitySchemes['passport']->toJson());
        self::assertSame('{"type":"openIdConnect","openIdConnectUrl":"https://example.com/.well-known/openid-configuration"}', $document->securitySchemes['oidc']->toJson());
        self::assertSame([['oidc' => []]], array_map(static fn(SecurityDescription $requirement): array => $requirement->schemes, $document->security));
    }

    public function test_csrf_adds_its_header_and_its_419(): void
    {
        $operation = $this->process(Descriptions::operation(HttpMethod::Post, '/users', 'Illuminate\Foundation\Http\Middleware\ValidateCsrfToken'))->operations[0];

        self::assertSame([['X-CSRF-TOKEN', ParameterLocation::Header]], array_map(static fn(ParameterDescription $p): array => [$p->name, $p->in], $operation->request->parameters));
        self::assertSame([200, 419], array_map(static fn($response): int|string => $response->status, $operation->responses));
    }

    public function test_signed_adds_its_query_parameters_and_its_403(): void
    {
        $operation = $this->process(Descriptions::operation(HttpMethod::Get, '/unsubscribe', 'signed'))->operations[0];

        self::assertSame([['signature', true], ['expires', false]], array_map(static fn(ParameterDescription $p): array => [$p->name, $p->required], $operation->request->parameters));
        self::assertSame([200, 403], array_map(static fn($response): int|string => $response->status, $operation->responses));
    }

    public function test_passport_scopes_are_the_scopes_of_its_requirement(): void
    {
        $document = $this->process(Descriptions::operation(HttpMethod::Get, '/api/users', 'auth:api', 'scopes:read,write'));
        $any = $this->process(Descriptions::operation(HttpMethod::Get, '/api/users', 'auth:api', 'scope:read,write'))->operations[0];

        self::assertSame([['passport' => ['read', 'write']]], array_map(static fn(SecurityDescription $requirement): array => $requirement->schemes, $document->operations[0]->security ?? []));
        self::assertSame([['passport' => ['read']], ['passport' => ['write']]], array_map(static fn(SecurityDescription $requirement): array => $requirement->schemes, $any->security ?? []));
        self::assertSame('{"read":"read","write":"write"}', json_encode($document->securitySchemes['passport']->flows?->authorizationCode?->scopes));
    }

    public function test_sanctum_abilities_are_scopes_from_3_1_and_a_sentence_before(): void
    {
        $operation = $this->process(Descriptions::operation(HttpMethod::Get, '/api/users', 'auth:sanctum', 'abilities:posts:read'))->operations[0];
        $legacy = $this->process(Descriptions::operation(HttpMethod::Get, '/api/users', 'auth:sanctum', 'ability:a,b'), $this->context(version: Version::V3_0_4))->operations[0];

        self::assertSame(['sanctum' => ['posts:read']], ($operation->security ?? [])[0]->schemes);
        self::assertSame(['sanctum' => []], ($legacy->security ?? [])[0]->schemes);
        self::assertSame('Requires one of the abilities a, b.', $legacy->description);
    }

    public function test_an_operation_without_authentication_has_no_security(): void
    {
        self::assertNull($this->process(Descriptions::operation(HttpMethod::Get, '/api/users', 'throttle:api'))->operations[0]->security);
    }

    public function test_a_declared_security_wins_over_the_middlewares(): void
    {
        $operation = Descriptions::operation(HttpMethod::Get, '/api/users', 'auth:sanctum')->security(SecurityDescription::create(['apiKey' => []]));

        self::assertSame([['apiKey' => []]], array_map(static fn(SecurityDescription $requirement): array => $requirement->schemes, $this->process($operation)->operations[0]->security ?? []));
    }

    public function test_a_configured_mapper_replaces_the_built_in_ones_of_its_middleware(): void
    {
        $mappers = MiddlewareMappers::default()->with('auth', new BasicAuthMiddlewareMapper());

        $document = (new MiddlewareSecurityPass($mappers))->process(Descriptions::document(Descriptions::operation(HttpMethod::Get, '/api/users', 'auth:sanctum')), $this->context());

        self::assertSame(['basic'], array_keys($document->securitySchemes));
    }

    public function test_the_webhooks_are_left_alone(): void
    {
        $document = (new MiddlewareSecurityPass(MiddlewareMappers::default()))->process(DocumentDescription::create()->webhooks(Descriptions::operation(HttpMethod::Post, 'order.created', 'auth:sanctum')), $this->context());

        self::assertNull($document->webhooks[0]->security);
    }

    private function process(OperationDescription $operation, ?GenerationContext $context = null): DocumentDescription
    {
        return (new MiddlewareSecurityPass(MiddlewareMappers::default()))->process(Descriptions::document($operation), $context ?? $this->context());
    }
}
