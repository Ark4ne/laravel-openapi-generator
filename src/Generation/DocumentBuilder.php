<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Generation;

use Ark4ne\OpenApi\Configuration\InfoConfiguration;
use Ark4ne\OpenApi\Configuration\ServerConfiguration;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\BodyDescription;
use Ark4ne\OpenApi\Description\CallbackDescription;
use Ark4ne\OpenApi\Description\DocumentDescription;
use Ark4ne\OpenApi\Description\HeaderDescription;
use Ark4ne\OpenApi\Description\OperationDescription;
use Ark4ne\OpenApi\Description\ParameterDescription;
use Ark4ne\OpenApi\Description\ResponseDescription;
use Ark4ne\OpenApi\Description\SecurityDescription;
use Ark4ne\OpenApi\Description\ServerDescription;
use Ark4ne\OpenApi\Description\TagDescription;
use Ark4ne\OpenApiWriter\Contracts\ResponseContract;
use Ark4ne\OpenApiWriter\Enums\HttpMethod;
use Ark4ne\OpenApiWriter\Enums\ParameterLocation;
use Ark4ne\OpenApiWriter\Objects\Callback;
use Ark4ne\OpenApiWriter\Objects\Components;
use Ark4ne\OpenApiWriter\Objects\Contact;
use Ark4ne\OpenApiWriter\Objects\Encoding;
use Ark4ne\OpenApiWriter\Objects\ExternalDocumentation;
use Ark4ne\OpenApiWriter\Objects\Header;
use Ark4ne\OpenApiWriter\Objects\Info;
use Ark4ne\OpenApiWriter\Objects\License;
use Ark4ne\OpenApiWriter\Objects\MediaType;
use Ark4ne\OpenApiWriter\Objects\OpenApi;
use Ark4ne\OpenApiWriter\Objects\Operation;
use Ark4ne\OpenApiWriter\Objects\Parameter;
use Ark4ne\OpenApiWriter\Objects\PathItem;
use Ark4ne\OpenApiWriter\Objects\RequestBody;
use Ark4ne\OpenApiWriter\Objects\Response;
use Ark4ne\OpenApiWriter\Objects\SecurityRequirement;
use Ark4ne\OpenApiWriter\Objects\Server;
use Ark4ne\OpenApiWriter\Objects\ServerVariable;
use Ark4ne\OpenApiWriter\Objects\Tag;
use Closure;

/**
 * The only mapper between the description and `openapi-writer`: everything it writes is already in the description
 * or in the configuration of the document; it takes no decision. The version, the mode and the vendor extensions are
 * given to the writer at writing (Output).
 */
final readonly class DocumentBuilder
{
    public function build(DocumentDescription $document, GenerationContext $context): OpenApi
    {
        $configuration = $context->document;
        $openApi = OpenApi::create()
            ->openapi($configuration->version)
            ->self($configuration->self)
            ->info(self::info($configuration->info))
            ->externalDocs(self::externalDocs($configuration->externalDocs));
        // A setter called without argument writes an empty collection: each one is called only with items.
        $openApi = self::with($openApi, $openApi->servers(...), array_map(self::documentServer(...), $configuration->servers));
        $openApi = self::with($openApi, $openApi->paths(...), $this->paths($document));
        $openApi = self::with($openApi, $openApi->webhooks(...), $this->pathItems($document->webhooks, static fn(OperationDescription $webhook): string => $webhook->webhook ?? $webhook->path));
        $openApi = self::with($openApi, $openApi->tags(...), array_map(self::tag(...), $document->tags));
        $openApi = self::with($openApi, $openApi->security(...), array_map(self::security(...), $document->security));

        $components = Components::create();
        $components = self::with($components, $components->schemas(...), array_map(static fn(string $name, $schema) => $schema->objectId($name), array_keys($document->schemas), $document->schemas));
        $components = self::with($components, $components->responses(...), array_map(fn(string $name, ResponseDescription $response): ResponseContract => $this->response($response, $name), array_keys($document->responses), $document->responses));
        $components = self::with($components, $components->securitySchemes(...), array_map(static fn(string $name, $scheme) => $scheme->objectId($name), array_keys($document->securitySchemes), $document->securitySchemes));

        if ($document->schemas !== [] || $document->responses !== [] || $document->securitySchemes !== []) {
            $openApi = $openApi->components($components);
        }

        foreach ($configuration->extensions as $name => $value) {
            $openApi = $openApi->x($name, $value);
        }

        return $openApi;
    }

    /**
     * @return list<PathItem> one per path, in the order of their first operation
     */
    private function paths(DocumentDescription $document): array
    {
        return array_map(
            static fn(PathItem $item): PathItem => self::with($item, $item->parameters(...), array_map(self::parameter(...), $document->pathParameters[(string) $item->objectId] ?? [])),
            $this->pathItems($document->operations, static fn(OperationDescription $operation): string => $operation->path),
        );
    }

    /**
     * @param list<OperationDescription> $operations
     * @param Closure(OperationDescription): string $key path, webhook name or runtime expression
     *
     * @return list<PathItem> one per key, in the order of their first operation
     */
    private function pathItems(array $operations, Closure $key): array
    {
        /** @var array<string, list<OperationDescription>> $items */
        $items = [];

        foreach ($operations as $operation) {
            $items[$key($operation)][] = $operation;
        }

        return array_map(
            fn(string $key, array $operations): PathItem => PathItem::create($key)->operations(...array_map($this->operation(...), $operations)),
            array_keys($items),
            array_values($items),
        );
    }

    /**
     * @param list<CallbackDescription> $callbacks
     *
     * @return list<Callback> one per name, holding a Path Item per expression
     */
    private function callbacks(array $callbacks): array
    {
        /** @var array<string, array<string, list<OperationDescription>>> $byName name → expression → requests */
        $byName = [];

        foreach ($callbacks as $callback) {
            if ($callback->operation !== null) {
                $byName[$callback->name][$callback->expression][] = $callback->operation;
            }
        }

        $written = [];

        foreach ($byName as $name => $byExpression) {
            $items = array_map(
                fn(string $expression, array $operations): PathItem => PathItem::create($expression)->operations(...array_map($this->operation(...), $operations)),
                array_keys($byExpression),
                array_values($byExpression),
            );
            $written[] = Callback::create($name)->pathItems(...$items);
        }

        return $written;
    }

    private function operation(OperationDescription $description): Operation
    {
        $method = $description->method;
        $operation = match (true) {
            $method === HttpMethod::Query => Operation::query(),
            $method instanceof HttpMethod => Operation::create($method->value),
            default => Operation::create($method),
        };

        $operation = $operation
            ->summary($description->summary)
            ->description($description->description)
            ->externalDocs(self::externalDocs($description->externalDocs))
            ->operationId($description->operationId)
            ->requestBody(self::body($description->request->body));
        $operation = self::with($operation, $operation->tags(...), $description->tags);
        $operation = self::with($operation, $operation->parameters(...), array_map(self::parameter(...), $description->request->parameters));
        $operation = self::with($operation, $operation->responses(...), array_map(fn(ResponseDescription $response): ResponseContract => $this->response($response), $description->responses));
        $operation = self::with($operation, $operation->servers(...), array_map(self::operationServer(...), $description->servers));
        $operation = self::with($operation, $operation->callbacks(...), $this->callbacks($description->callbacks));

        if ($description->deprecated) {
            $operation = $operation->deprecated();
        }

        if ($description->security !== null) {
            $operation = $operation->security(...array_map(self::security(...), $description->security));
        }

        foreach ($description->extensions as $name => $value) {
            $operation = $operation->x($name, $value);
        }

        return $operation;
    }

    private static function parameter(ParameterDescription $description): Parameter
    {
        $parameter = match ($description->in) {
            ParameterLocation::Path => Parameter::path($description->name),
            ParameterLocation::Header => Parameter::header($description->name),
            ParameterLocation::Cookie => Parameter::cookie($description->name),
            ParameterLocation::Querystring => Parameter::querystring($description->name),
            default => Parameter::query($description->name),
        };

        $parameter = $description->in === ParameterLocation::Querystring
            ? $parameter->content(MediaType::formUrlEncoded()->schema($description->schema))
            : $parameter->schema($description->schema);

        $parameter = $parameter->description($description->description)->style($description->style)->explode($description->explode);

        if ($description->required && $description->in !== ParameterLocation::Path) {
            $parameter = $parameter->required();
        }

        return $description->deprecated ? $parameter->deprecated() : $parameter;
    }

    private static function body(?BodyDescription $description): ?RequestBody
    {
        if ($description === null || $description->contents === []) {
            return null;
        }

        $contents = [];

        foreach ($description->contents as $mediaType => $schema) {
            $encodings = [];

            foreach ($description->encodings[$mediaType] ?? [] as $property => $contentType) {
                $encodings[] = Encoding::create($property)->contentType($contentType);
            }

            $content = MediaType::of($mediaType)->schema($schema);
            $contents[] = self::with($content, $content->encoding(...), $encodings);
        }

        $body = RequestBody::create()->description($description->description)->content(...$contents);

        return $description->required ? $body->required() : $body;
    }

    private function response(ResponseDescription $description, ?string $name = null): ResponseContract
    {
        $status = (string) $description->status;

        if ($description->reference !== null && $name === null) {
            return Response::ref($description->reference, $status);
        }

        $contents = [];

        foreach ($description->contents as $mediaType => $schema) {
            $contents[$mediaType] = MediaType::of($mediaType)->schema($schema);
        }

        foreach ($description->itemContents as $mediaType => $schema) {
            $contents[$mediaType] = ($contents[$mediaType] ?? MediaType::of($mediaType))->itemSchema($schema);
        }

        $response = Response::create($name ?? $status)->description($description->description ?? '')->summary($description->summary);
        $response = self::with($response, $response->headers(...), array_map(self::header(...), $description->headers));

        return self::with($response, $response->content(...), array_values($contents));
    }

    private static function header(HeaderDescription $description): Header
    {
        $header = Header::create($description->name)->description($description->description)->schema($description->schema);

        return $description->required ? $header->required() : $header;
    }

    private static function security(SecurityDescription $description): SecurityRequirement
    {
        return $description->schemes === [] ? SecurityRequirement::create() : SecurityRequirement::create()->schemes($description->schemes);
    }

    private static function tag(TagDescription $description): Tag
    {
        return Tag::create()
            ->name($description->name)
            ->summary($description->summary)
            ->description($description->description)
            ->externalDocs(self::externalDocs($description->externalDocs))
            ->parent($description->parent)
            ->kind($description->kind);
    }

    private static function info(InfoConfiguration $configuration): Info
    {
        $contact = $configuration->contact === [] ? null : Contact::create()
            ->name($configuration->contact['name'] ?? null)
            ->url($configuration->contact['url'] ?? null)
            ->email($configuration->contact['email'] ?? null);
        $license = $configuration->license === [] ? null : License::create()->name($configuration->license['name'] ?? null);

        if ($license !== null && isset($configuration->license['identifier'])) {
            $license = $license->identifier($configuration->license['identifier']);
        } elseif ($license !== null && isset($configuration->license['url'])) {
            $license = $license->url($configuration->license['url']);
        }

        return Info::create()
            ->title($configuration->title)
            ->summary($configuration->summary)
            ->description($configuration->description)
            ->termsOfService($configuration->termsOfService)
            ->contact($contact)
            ->license($license)
            ->version($configuration->version);
    }

    private static function documentServer(ServerConfiguration $configuration): Server
    {
        $server = Server::create()->url($configuration->url)->description($configuration->description)->name($configuration->name);

        return self::with($server, $server->variables(...), self::variables($configuration->variables));
    }

    private static function operationServer(ServerDescription $description): Server
    {
        $server = Server::create()->url($description->url)->description($description->description);

        return self::with($server, $server->variables(...), self::variables($description->variables));
    }

    /**
     * @param array<string, array{default: string, enum?: list<string>, description?: string}> $variables
     *
     * @return list<ServerVariable>
     */
    private static function variables(array $variables): array
    {
        $written = [];

        foreach ($variables as $name => $variable) {
            $server = ServerVariable::create($name)->default($variable['default'])->description($variable['description'] ?? null);
            $written[] = self::with($server, $server->enum(...), $variable['enum'] ?? []);
        }

        return $written;
    }

    /**
     * The object with a collection set, when it has items: the setter called without argument writes an empty one.
     *
     * @template T of object
     *
     * @template I
     *
     * @param T $object
     * @param Closure(I ...): T $set
     * @param list<I> $items
     *
     * @return T
     */
    private static function with(object $object, Closure $set, array $items): object
    {
        return $items === [] ? $object : $set(...$items);
    }

    /**
     * @param array{url: string, description?: string}|null $externalDocs
     */
    private static function externalDocs(?array $externalDocs): ?ExternalDocumentation
    {
        return $externalDocs === null ? null : ExternalDocumentation::create()->url($externalDocs['url'])->description($externalDocs['description'] ?? null);
    }
}
