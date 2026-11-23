<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Webhooks;

use Ark4ne\OpenApi\Attributes\Header;
use Ark4ne\OpenApi\Attributes\Operation;
use Ark4ne\OpenApi\Attributes\Response;
use Ark4ne\OpenApi\Attributes\Tag;
use Ark4ne\OpenApi\Attributes\Webhook;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Context\Stage;
use Ark4ne\OpenApi\Description\BodyDescription;
use Ark4ne\OpenApi\Description\OperationDescription;
use Ark4ne\OpenApi\Description\ParameterDescription;
use Ark4ne\OpenApi\Description\RequestDescription;
use Ark4ne\OpenApi\Description\ResponseDescription;
use Ark4ne\OpenApi\Discovery\RouteDefinition;
use Ark4ne\OpenApi\Extraction\Operations\AttributeOperationExtractor;
use Ark4ne\OpenApi\Extraction\Operations\TranslationOperationExtractor;
use Ark4ne\OpenApi\Extraction\Requests\FieldPlacer;
use Ark4ne\OpenApi\Extraction\Responses\DeclaredResponses;
use Ark4ne\OpenApi\Extraction\Responses\ResourceSchemas;
use Ark4ne\OpenApi\Sampling\TypeSchemas;
use Ark4ne\OpenApi\Types\TypeResolver;
use Illuminate\Http\Resources\Json\JsonResource;
use ReflectionClass;
use Throwable;

/**
 * A payload webhook: a `JsonResource` marked `#[Webhook]`, described without a route.
 *
 * - The resource is the body sent, described as a resource returned by an action (sampled, a component referenced by
 *   `$ref`, the media type of its format). The body is required.
 * - The name of `#[Webhook]` is its key and its operationId (unless `#[Operation(operationId:)]`); without a name,
 *   a warning and no webhook. Its method is `POST` unless `#[Webhook(method:)]`.
 * - The PHPDoc of the class gives the summary, the description and `@deprecated`; `#[Operation]` overrides them.
 * - Tagged `Webhooks` unless `#[Operation(tags:)]` or a `#[Tag]` tags it.
 * - `#[Header]` gives the headers sent, `#[Response]` what the receiver answers: `200` by default.
 * - Only the attributes of the class itself are read; one that cannot be built is left out with a warning.
 */
final readonly class PayloadWebhookExtractor
{
    public const TAG = 'Webhooks';

    public function __construct(
        private ResourceSchemas $resources,
        private DeclaredResponses $responses,
        private TypeResolver $types,
        private ?TranslationOperationExtractor $translation = null,
    ) {}

    /**
     * @param class-string<JsonResource> $class
     */
    public function extract(string $class, GenerationContext $context): ?OperationDescription
    {
        $reflection = new ReflectionClass($class);
        $webhook = self::attributes($reflection, Webhook::class, $class, $context)[0] ?? null;

        if ($webhook === null) {
            return null;
        }

        if ($webhook->name === null || trim($webhook->name) === '') {
            $context->diagnostics->warning(Stage::Operation, "#[Webhook] without name on the payload class {$class}, webhook left out", $class);

            return null;
        }

        $method = RouteDefinition::method($webhook->method ?? 'post');
        $label = RouteDefinition::labelOf($method, $webhook->name);
        $doc = $this->types->docBlock($reflection);
        $description = OperationDescription::create($method, $webhook->name)
            ->route($label)
            ->webhook($webhook->name)
            ->operationId($webhook->name)
            ->summary($doc->summary())
            ->description($doc->description())
            ->deprecated($doc->isDeprecated())
            ->tags(self::TAG);
        $operation = self::attributes($reflection, Operation::class, $label, $context)[0] ?? null;
        $tags = self::attributes($reflection, Tag::class, $label, $context);

        if ($tags !== [] && ($operation === null || $operation->tags === [])) {
            $description = $description->tags();
        }

        if ($operation !== null) {
            $description = AttributeOperationExtractor::operation($operation, $description);
        }

        foreach ($tags as $tag) {
            $description = $description->addDeclaredTags(AttributeOperationExtractor::tag($tag));

            if (! in_array($tag->name, $description->tags, true)) {
                $description = $description->addTags($tag->name);
            }
        }

        $description = $description
            ->request($this->request($class, $reflection, $label, $context))
            ->responses(...$this->responses($reflection, $label, $context));

        return $this->translation?->translated($description, $context) ?? $description;
    }

    /**
     * @param class-string<JsonResource> $class
     * @param ReflectionClass<JsonResource> $reflection
     */
    private function request(string $class, ReflectionClass $reflection, string $label, GenerationContext $context): RequestDescription
    {
        $parameters = [];

        // As on a route: a header given twice (whatever the case of its name) is completed by the later attribute.
        foreach (self::attributes($reflection, Header::class, $label, $context) as $header) {
            $parameter = ParameterDescription::header($header->name);
            $parameter = FieldPlacer::find($parameter, $parameters) ?? $parameter;
            $schema = $header->type === null ? $parameter->schema : TypeSchemas::of($this->types->parse($header->type, $reflection));
            $parameter = $parameter
                ->schema($header->example === null ? $schema : $schema->example($header->example))
                ->description($header->description ?? $parameter->description);
            $parameters = FieldPlacer::complete($parameters, $header->required === null ? $parameter : $parameter->required($header->required));
        }

        $body = BodyDescription::create()
            ->content($this->resources->mediaType($class), $this->resources->request($class, $context))
            ->required();

        return RequestDescription::create()->parameters(...$parameters)->body($body);
    }

    /**
     * @param ReflectionClass<JsonResource> $reflection
     *
     * @return list<ResponseDescription>
     */
    private function responses(ReflectionClass $reflection, string $label, GenerationContext $context): array
    {
        $responses = array_map(
            fn(Response $response): ResponseDescription => $this->responses->response($response, $reflection, $context),
            self::attributes($reflection, Response::class, $label, $context),
        );

        return $responses === [] ? [ResponseDescription::ok()->description(DeclaredResponses::phrase(200))] : $responses;
    }

    /**
     * @template T of object
     *
     * @param ReflectionClass<JsonResource> $class
     * @param class-string<T> $attribute
     *
     * @return list<T>
     */
    private static function attributes(ReflectionClass $class, string $attribute, string $label, GenerationContext $context): array
    {
        $instances = [];

        foreach ($class->getAttributes($attribute) as $reflection) {
            try {
                $instances[] = $reflection->newInstance();
            } catch (Throwable $error) {
                $context->diagnostics->warning(Stage::Operation, "invalid #[{$reflection->getName()}] on {$class->getName()}, attribute ignored: {$error->getMessage()}", $label);
            }
        }

        return $instances;
    }
}
