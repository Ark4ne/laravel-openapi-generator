<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Generation;

use Ark4ne\OpenApi\Configuration\Configuration;
use Ark4ne\OpenApi\Configuration\DocumentConfiguration;
use Ark4ne\OpenApi\Configuration\InfoConfiguration;
use Ark4ne\OpenApi\Configuration\ServerConfiguration;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\BodyDescription;
use Ark4ne\OpenApi\Description\CallbackDescription;
use Ark4ne\OpenApi\Description\DocumentDescription;
use Ark4ne\OpenApi\Description\HeaderDescription;
use Ark4ne\OpenApi\Description\OperationDescription;
use Ark4ne\OpenApi\Description\ParameterDescription;
use Ark4ne\OpenApi\Description\RequestDescription;
use Ark4ne\OpenApi\Description\ResponseDescription;
use Ark4ne\OpenApi\Description\SecurityDescription;
use Ark4ne\OpenApi\Description\ServerDescription;
use Ark4ne\OpenApi\Description\TagDescription;
use Ark4ne\OpenApi\Generation\DocumentBuilder;
use Ark4ne\OpenApi\Tests\Support\Schemas;
use Ark4ne\OpenApi\Tests\TestCase;
use Ark4ne\OpenApiWriter\Enums\HttpMethod;
use Ark4ne\OpenApiWriter\Enums\ParameterStyle;
use Ark4ne\OpenApiWriter\Enums\VendorExtensions;
use Ark4ne\OpenApiWriter\Enums\Version;
use Ark4ne\OpenApiWriter\Objects\Schema;
use Ark4ne\OpenApiWriter\Objects\SecurityScheme;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversNamespace;

#[CoversClass(DocumentBuilder::class)]
#[CoversNamespace('Ark4ne\OpenApi\Description')]
final class DocumentBuilderTest extends TestCase
{
    public function test_a_complete_description_is_written_field_for_field(): void
    {
        $operation = OperationDescription::create(HttpMethod::Get, '/users/{user}')
            ->operationId('users.show')
            ->summary('Show a user')
            ->description('The user.')
            ->tags('users')
            ->deprecated()
            ->externalDocs(['url' => 'https://example.com/users', 'description' => 'Users'])
            ->request(RequestDescription::create()->parameters(
                ParameterDescription::query('filter', Schema::object())->style(ParameterStyle::DeepObject)->explode()->description('Filters')->deprecated(),
                ParameterDescription::header('X-Tenant')->required(),
                ParameterDescription::cookie('locale'),
            ))
            ->responses(
                ResponseDescription::ok()->description('OK')->summary('Found')->content('application/json', Schema::ref('User'))->headers(HeaderDescription::create('X-Trace')->description('Trace')->required()),
                ResponseDescription::reference(404, 'NotFound'),
                ResponseDescription::create('default')->description('Error'),
            )
            ->security(SecurityDescription::create(['bearer' => ['read']]), SecurityDescription::create())
            ->servers(ServerDescription::create('https://{account}.example.com')->variable('account', 'acme', ['acme', 'globex'], 'Account'))
            ->x('x-internal', true);
        $store = OperationDescription::create(HttpMethod::Post, '/users')
            ->operationId('users.store')
            ->request(RequestDescription::create()->body(BodyDescription::create()
                ->content('multipart/form-data', Schema::object()->properties(Schema::string('avatar')->format('binary')))
                ->encoding('multipart/form-data', 'avatar', 'image/png')
                ->required()
                ->description('The user')))
            ->responses(ResponseDescription::create(201)->description('Created'));
        $events = OperationDescription::create(HttpMethod::Query, '/events')
            ->request(RequestDescription::create()->parameters(ParameterDescription::querystring('query', Schema::object()->properties(Schema::string('q')))))
            ->responses(ResponseDescription::ok()->description('OK')->itemContent('text/event-stream', Schema::object()));
        $purge = OperationDescription::create('PURGE', '/users')->responses(ResponseDescription::create(204)->description('No Content'));
        $document = DocumentDescription::create()
            ->operations($operation, $store, $events, $purge)
            ->pathParameters('/users/{user}', ParameterDescription::path('user', Schema::integer()))
            ->tags(TagDescription::create('users')->description('Users')->summary('People')->kind('nav')->parent('admin')->externalDocs(['url' => 'https://example.com/tags']), TagDescription::create('admin'))
            ->schema('User', Schema::object()->properties(Schema::integer('id')))
            ->response('NotFound', ResponseDescription::create(404)->description('Not Found')->content('application/json', Schema::ref('Error')))
            ->schema('Error', Schema::object())
            ->securityScheme('bearer', SecurityScheme::http('bearer')->scheme('bearer'))
            ->security(SecurityDescription::create(['bearer' => []]));

        $written = (new DocumentBuilder())->build($document, $this->writing())->toArray(Version::V3_2_0);

        Schemas::assertWritten([
            'openapi' => '3.2.0',
            '$self' => 'https://example.com/openapi.json',
            'info' => ['title' => 'API', 'summary' => 'Summary', 'description' => 'Description', 'termsOfService' => 'https://example.com/terms', 'contact' => ['name' => 'Team', 'url' => 'https://example.com', 'email' => 'team@example.com'], 'license' => ['name' => 'MIT', 'identifier' => 'MIT'], 'version' => '1.0.0'],
            'servers' => [['url' => 'https://{region}.example.com', 'description' => 'Production', 'name' => 'prod', 'variables' => ['region' => ['enum' => ['eu', 'us'], 'default' => 'eu', 'description' => 'Region']]]],
            'paths' => [
                '/users/{user}' => [
                    'parameters' => [['name' => 'user', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                    'get' => [
                        'tags' => ['users'],
                        'summary' => 'Show a user',
                        'description' => 'The user.',
                        'externalDocs' => ['description' => 'Users', 'url' => 'https://example.com/users'],
                        'operationId' => 'users.show',
                        'parameters' => [
                            ['name' => 'filter', 'in' => 'query', 'description' => 'Filters', 'deprecated' => true, 'style' => 'deepObject', 'explode' => true, 'schema' => ['type' => 'object']],
                            ['name' => 'X-Tenant', 'in' => 'header', 'required' => true, 'schema' => ['type' => 'string']],
                            ['name' => 'locale', 'in' => 'cookie', 'schema' => ['type' => 'string']],
                        ],
                        'responses' => [
                            '200' => ['summary' => 'Found', 'description' => 'OK', 'headers' => ['X-Trace' => ['description' => 'Trace', 'required' => true, 'schema' => ['type' => 'string']]], 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/User']]]],
                            '404' => ['$ref' => '#/components/responses/NotFound'],
                            'default' => ['description' => 'Error'],
                        ],
                        'deprecated' => true,
                        'security' => [['bearer' => ['read']], []],
                        'servers' => [['url' => 'https://{account}.example.com', 'variables' => ['account' => ['enum' => ['acme', 'globex'], 'default' => 'acme', 'description' => 'Account']]]],
                        'x-internal' => true,
                    ],
                ],
                '/users' => [
                    'post' => [
                        'operationId' => 'users.store',
                        'requestBody' => ['description' => 'The user', 'content' => ['multipart/form-data' => ['schema' => ['type' => 'object', 'properties' => ['avatar' => ['type' => 'string', 'format' => 'binary']]], 'encoding' => ['avatar' => ['contentType' => 'image/png']]]], 'required' => true],
                        'responses' => ['201' => ['description' => 'Created']],
                    ],
                    'additionalOperations' => ['PURGE' => ['responses' => ['204' => ['description' => 'No Content']]]],
                ],
                '/events' => [
                    'query' => [
                        'parameters' => [['name' => 'query', 'in' => 'querystring', 'content' => ['application/x-www-form-urlencoded' => ['schema' => ['type' => 'object', 'properties' => ['q' => ['type' => 'string']]]]]]],
                        'responses' => ['200' => ['description' => 'OK', 'content' => ['text/event-stream' => ['itemSchema' => ['type' => 'object']]]]],
                    ],
                ],
            ],
            'components' => [
                'schemas' => ['User' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']]], 'Error' => ['type' => 'object']],
                'responses' => ['NotFound' => ['description' => 'Not Found', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]]],
                'securitySchemes' => ['bearer' => ['type' => 'http', 'scheme' => 'bearer']],
            ],
            'security' => [['bearer' => []]],
            'tags' => [
                ['name' => 'users', 'summary' => 'People', 'description' => 'Users', 'externalDocs' => ['url' => 'https://example.com/tags'], 'parent' => 'admin', 'kind' => 'nav'],
                ['name' => 'admin'],
            ],
            'externalDocs' => ['description' => 'Guide', 'url' => 'https://example.com/docs'],
            'x-logo' => ['url' => 'logo.png'],
        ], $written);
    }

    public function test_the_methods_of_a_webhook_are_written_in_one_path_item_under_its_name(): void
    {
        $received = ResponseDescription::create(204)->description('Received');
        $document = DocumentDescription::create()->webhooks(
            OperationDescription::create(HttpMethod::Post, '/hooks/created')->webhook('order.created')->responses($received),
            OperationDescription::create(HttpMethod::Put, '/hooks/created')->webhook('order.created')->responses($received),
        );

        $written = (new DocumentBuilder())->build($document, $this->writing())->toArray(Version::V3_2_0);

        Schemas::assertWritten(['post' => ['responses' => ['204' => ['description' => 'Received']]], 'put' => ['responses' => ['204' => ['description' => 'Received']]]], (array) Schemas::at($written, 'webhooks', 'order.created'));
        self::assertArrayNotHasKey('paths', $written);
    }

    public function test_a_document_without_operation_keeps_empty_paths(): void
    {
        $document = new DocumentConfiguration('v1', new InfoConfiguration('API', '1.0.0'));
        $context = GenerationContext::for(new Configuration(['v1' => $document]), $document);

        foreach ([Version::V3_0_4, Version::V3_1_2] as $version) {
            $written = (new DocumentBuilder())->build(DocumentDescription::create(), $context)->toArray($version);

            self::assertArrayHasKey('paths', $written, $version->value);
            self::assertEmpty((array) $written['paths'], $version->value);
        }
    }

    public function test_a_3_0_document_of_webhooks_only_keeps_empty_paths(): void
    {
        $document = new DocumentConfiguration('v1', new InfoConfiguration('API', '1.0.0'), version: Version::V3_0_4, vendorExtensions: VendorExtensions::Redoc);
        $context = GenerationContext::for(new Configuration(['v1' => $document]), $document);
        $webhook = OperationDescription::create(HttpMethod::Post, '/hooks/created')->webhook('order.created')->responses(ResponseDescription::create(204)->description('Received'));

        $written = (new DocumentBuilder())->build(DocumentDescription::create()->webhooks($webhook), $context)->toArray(Version::V3_0_4, VendorExtensions::Redoc);

        self::assertArrayHasKey('paths', $written);
        self::assertArrayHasKey('x-webhooks', $written);
    }

    public function test_the_callbacks_are_grouped_by_name_then_by_expression(): void
    {
        $sent = static fn(HttpMethod $method, string $expression): OperationDescription => OperationDescription::create($method, $expression)->responses(ResponseDescription::ok()->description('OK'));
        $operation = OperationDescription::create(HttpMethod::Post, '/orders')->responses(ResponseDescription::create(201)->description('Created'))->callbacks(
            CallbackDescription::create('shipped', '{$request.body#/url}', $sent(HttpMethod::Post, '{$request.body#/url}')),
            CallbackDescription::create('shipped', '{$request.body#/url}', $sent(HttpMethod::Put, '{$request.body#/url}')),
            CallbackDescription::create('shipped', '{$request.query.notify}', $sent(HttpMethod::Post, '{$request.query.notify}')),
            CallbackDescription::create('paid', '{$request.body#/url}', $sent(HttpMethod::Post, '{$request.body#/url}')),
            CallbackDescription::fromWebhook('unresolved', '{$request.body#/url}', 'order.created'),
        );

        $written = (new DocumentBuilder())->build(DocumentDescription::create()->operations($operation), $this->writing())->toArray(Version::V3_2_0);
        $ok = ['responses' => ['200' => ['description' => 'OK']]];

        Schemas::assertWritten([
            'shipped' => ['{$request.body#/url}' => ['post' => $ok, 'put' => $ok], '{$request.query.notify}' => ['post' => $ok]],
            'paid' => ['{$request.body#/url}' => ['post' => $ok]],
        ], (array) Schemas::at($written, 'paths', '/orders', 'post', 'callbacks'));
    }

    private function writing(): GenerationContext
    {
        $document = new DocumentConfiguration(
            'v1',
            new InfoConfiguration('API', '1.0.0', 'Summary', 'Description', 'https://example.com/terms', ['name' => 'Team', 'url' => 'https://example.com', 'email' => 'team@example.com'], ['name' => 'MIT', 'identifier' => 'MIT']),
            servers: [new ServerConfiguration('https://{region}.example.com', 'Production', ['region' => ['default' => 'eu', 'enum' => ['eu', 'us'], 'description' => 'Region']], 'prod')],
            externalDocs: ['url' => 'https://example.com/docs', 'description' => 'Guide'],
            self: 'https://example.com/openapi.json',
            extensions: ['x-logo' => ['url' => 'logo.png']],
        );

        return GenerationContext::for(new Configuration(['v1' => $document]), $document);
    }
}
