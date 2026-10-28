<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Generation;

use Ark4ne\OpenApi\Configuration\Configuration;
use Ark4ne\OpenApi\Configuration\DocumentConfiguration;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\DocumentDescription;
use Ark4ne\OpenApi\Discovery\MiddlewareResolver;
use Ark4ne\OpenApi\Discovery\PathParameterResolver;
use Ark4ne\OpenApi\Discovery\RouteCollector;
use Ark4ne\OpenApi\Extraction\Callbacks\DeclaredCallbacks;
use Ark4ne\OpenApi\Extraction\OperationContextFactory;
use Ark4ne\OpenApi\Extraction\Operations\OperationExtractors;
use Ark4ne\OpenApi\Extraction\Requests\RequestExtractors;
use Ark4ne\OpenApi\Extraction\Responses\OperationResponses;
use Ark4ne\OpenApi\Extraction\Rules\RuleMappers;
use Ark4ne\OpenApi\Extraction\Strategies\BuiltInStrategies;
use Ark4ne\OpenApi\Sampling\Samplers;
use Ark4ne\OpenApi\Sampling\SamplingSession;
use Ark4ne\OpenApi\Types\TypeResolver;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Routing\Router;

/**
 * Generates the documents of the configuration: for each document and each language, the routes (E2), their
 * metadata, requests and responses (E3 to E6) inside one sampling session, the passes (E7), then the writer's object.
 *
 * The available integrations add their resource formats and rule mappers, and the last one that has error bodies
 * replaces Laravel's.
 */
final readonly class Generator
{
    /**
     * @param list<Integration> $integrations those not available are left out
     */
    public function __construct(
        private RouteCollector $routes,
        private Container $container,
        private SamplingSession $session,
        private TypeResolver $types,
        private ?Translator $translator = null,
        private DocumentBuilder $builder = new DocumentBuilder(),
        private array $integrations = [],
    ) {}

    /**
     * @param list<Integration> $integrations
     */
    public static function make(Router $router, Container $container, SamplingSession $session, ?Translator $translator = null, array $integrations = []): self
    {
        return new self(new RouteCollector($router, new MiddlewareResolver($router), new PathParameterResolver()), $container, $session, new TypeResolver(), $translator, integrations: $integrations);
    }

    /**
     * Every document of the configuration, in each of its languages (once without language when none is set).
     *
     * @return list<GeneratedDocument>
     */
    public function all(Configuration $configuration): array
    {
        $generated = [];

        foreach ($configuration->documents as $document) {
            foreach ($configuration->languages === [] ? [null] : $configuration->languages as $locale) {
                $generated[] = $this->generate($configuration, $document, $locale);
            }
        }

        return $generated;
    }

    public function generate(Configuration $configuration, string|DocumentConfiguration $document, ?string $locale = null): GeneratedDocument
    {
        $document = is_string($document) ? $configuration->document($document) : $document;
        $context = GenerationContext::for($configuration, $document, $locale);
        $description = $this->session->run(fn(): DocumentDescription => $this->describe($context), $context);
        $description = Passes::default($configuration, $this->errorBodies())->process($description, $context);

        return new GeneratedDocument($this->builder->build($description, $context), $description, $context);
    }

    /**
     * @return list<Integration>
     */
    private function available(): array
    {
        return array_values(array_filter($this->integrations, static fn(Integration $integration): bool => $integration->available()));
    }

    /**
     * The error bodies of the last available integration that has some, Laravel's otherwise.
     */
    private function errorBodies(): ErrorBodies
    {
        $errors = new LaravelErrorBodies();

        foreach ($this->available() as $integration) {
            $errors = $integration->errorBodies() ?? $errors;
        }

        return $errors;
    }

    private function describe(GenerationContext $context): DocumentDescription
    {
        $configuration = $context->document;
        $factory = new OperationContextFactory($this->types);
        $operations = OperationExtractors::default(
            BuiltInStrategies::summary($configuration->summary),
            BuiltInStrategies::tags($configuration->tags),
            BuiltInStrategies::operationId($configuration->operationId),
            $this->translator,
            BuiltInStrategies::groups($configuration->groups),
        );
        $integrations = $this->available();
        $formats = array_merge(...array_map(static fn(Integration $integration): array => $integration->resourceFormats(), $integrations));
        $mappers = array_merge(...array_map(static fn(Integration $integration): array => $integration->ruleMappers(), $integrations));
        $responses = OperationResponses::default(Samplers::default($this->session, $this->types), $this->types, ...$formats);
        $requests = RequestExtractors::default($this->container, BuiltInStrategies::query($configuration->query), RuleMappers::default()->with(...$mappers), $this->types, $responses->resources());
        $callbacks = new DeclaredCallbacks($responses->declared());
        $document = DocumentDescription::create();

        foreach ($this->routes->collect($context) as $route) {
            $operation = $factory->create($route, $context);
            $description = $operations->extract($operation, $operation->describe(), $context);
            $description = $description
                ->request($requests->extract($operation, $description->request, $context))
                ->responses(...$responses->describe($operation, $context))
                ->callbacks(...$callbacks->describe($operation, $context));

            // A webhook is filed apart from the start: the passes tied to the routes never see it.
            $document = match (true) {
                $description->excluded => $document,
                $description->webhook !== null => $document->addWebhooks($description),
                default => $document->addOperations($description),
            };
        }

        return $document;
    }
}
