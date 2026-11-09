<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Generation;

use Ark4ne\OpenApi\Configuration\Configuration;
use Ark4ne\OpenApi\Configuration\DocumentConfiguration;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Context\Stage;
use Ark4ne\OpenApi\Description\DocumentDescription;
use Ark4ne\OpenApi\Discovery\MiddlewareResolver;
use Ark4ne\OpenApi\Discovery\PathParameterResolver;
use Ark4ne\OpenApi\Discovery\RouteCollector;
use Ark4ne\OpenApi\Exceptions\InvalidConfigurationException;
use Ark4ne\OpenApi\Extraction\Callbacks\DeclaredCallbacks;
use Ark4ne\OpenApi\Extraction\Exceptions\ErrorBodies;
use Ark4ne\OpenApi\Extraction\Exceptions\LaravelErrorBodies;
use Ark4ne\OpenApi\Extraction\OperationContextFactory;
use Ark4ne\OpenApi\Extraction\Operations\OperationExtractors;
use Ark4ne\OpenApi\Extraction\Requests\RequestExtractors;
use Ark4ne\OpenApi\Extraction\Responses\OperationResponses;
use Ark4ne\OpenApi\Extraction\Rules\RuleMappers;
use Ark4ne\OpenApi\Extraction\Strategies\BuiltInStrategies;
use Ark4ne\OpenApi\Extraction\Strategies\QueryStrategy;
use Ark4ne\OpenApi\Generation\Passes\IntegrationPass;
use Ark4ne\OpenApi\Sampling\Samplers;
use Ark4ne\OpenApi\Sampling\SamplingSession;
use Ark4ne\OpenApi\Types\TypeResolver;
use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Routing\Router;
use Throwable;

/**
 * Generates the documents of the configuration: for each document and each language, the routes, their
 * metadata, requests and responses inside one sampling session, the passes, then the writer's object.
 *
 * The available integrations add their resource formats and rule mappers, and the last one that has error bodies
 * replaces Laravel's; the extensions of the application join the registries, and its strategy classes come from the
 * container. The available document integrations read their settings in the document: they prepare the requests,
 * describe the document and add passes, in their order.
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
        private GeneratorExtensions $extensions = new GeneratorExtensions(),
    ) {}

    /**
     * @param list<Integration> $integrations
     */
    public static function make(Router $router, Container $container, SamplingSession $session, ?Translator $translator = null, array $integrations = [], GeneratorExtensions $extensions = new GeneratorExtensions()): self
    {
        return new self(new RouteCollector($router, new MiddlewareResolver($router), new PathParameterResolver()), $container, $session, new TypeResolver(), $translator, integrations: $integrations, extensions: $extensions);
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
        $integrations = array_values(array_filter($this->integrations, static fn(Integration $integration): bool => $integration->available()));
        $documents = self::documentIntegrations($integrations);
        $preparers = array_map(static fn(DocumentIntegration $integration): Closure => $integration->prepareRequest(...), $documents);
        $context = GenerationContext::for($configuration, $document, $locale, $preparers);
        self::conflicts($integrations, $documents, $context);
        self::unclaimed($documents, $context);
        $documents = array_values($documents);
        $errors = self::errorBodies($integrations);
        $description = $this->session->run(fn(): DocumentDescription => $this->describe($context, $integrations, $errors)->addNotes(...self::notes($documents, $context)), $context);
        $description = Passes::default($configuration, $errors, $this->container, ...self::passes($documents, $context), ...$this->extensions->passes)->process($description, $context);

        return new GeneratedDocument($this->builder->build($description, $context), $description, $context);
    }

    /**
     * Builds what the configuration names for a document (strategy classes, middleware mappers) without generating it,
     * so that an invalid class is reported before any document is written.
     *
     * @throws InvalidConfigurationException
     */
    public function prepare(Configuration $configuration, DocumentConfiguration $document): void
    {
        $this->operations($document);
        $this->query($document);
        Passes::default($configuration, new LaravelErrorBodies(), $this->container);
    }

    /**
     * @throws InvalidConfigurationException
     */
    private function operations(DocumentConfiguration $document): OperationExtractors
    {
        $key = "documents.{$document->name}.strategies";

        return OperationExtractors::default(
            BuiltInStrategies::summary($document->summary, "{$key}.summary", $this->container),
            BuiltInStrategies::tags($document->tags, "{$key}.tags", $this->container),
            BuiltInStrategies::operationId($document->operationId, "{$key}.operation_id", $this->container),
            $this->translator,
            BuiltInStrategies::groups($document->groups, "{$key}.groups", $this->container),
        )->with(...$this->extensions->operationExtractors);
    }

    /**
     * @throws InvalidConfigurationException
     */
    private function query(DocumentConfiguration $document): QueryStrategy
    {
        return BuiltInStrategies::query($document->query, "documents.{$document->name}.strategies.query", $this->container);
    }

    /**
     * The error bodies of the last integration that has some, Laravel's otherwise.
     *
     * @param list<Integration> $integrations
     */
    private static function errorBodies(array $integrations): ErrorBodies
    {
        $errors = new LaravelErrorBodies();

        foreach ($integrations as $integration) {
            $errors = $integration->errorBodies() ?? $errors;
        }

        return $errors;
    }

    /**
     * The available document integrations by key, in their order: the first of a key wins, the others are left out.
     *
     * @param list<Integration> $integrations the available ones
     *
     * @return array<string, DocumentIntegration>
     */
    private static function documentIntegrations(array $integrations): array
    {
        $documents = [];

        foreach ($integrations as $integration) {
            if ($integration instanceof DocumentIntegration) {
                $documents[$integration->key()] ??= $integration;
            }
        }

        return $documents;
    }

    /**
     * Reports the document integrations left out because an earlier one has their key.
     *
     * @param list<Integration> $integrations the available ones
     * @param array<string, DocumentIntegration> $documents those kept
     */
    private static function conflicts(array $integrations, array $documents, GenerationContext $context): void
    {
        foreach ($integrations as $integration) {
            if ($integration instanceof DocumentIntegration && $documents[$integration->key()] !== $integration) {
                $kept = $documents[$integration->key()]::class;
                $context->diagnostics->warning(Stage::Configuration, 'the integration ' . $integration::class . " has the key {$integration->key()} of {$kept}, left out");
            }
        }
    }

    /**
     * Reports the settings of the document no available document integration reads: a typo, or a package missing.
     *
     * @param array<string, DocumentIntegration> $integrations the available ones, by key
     */
    private static function unclaimed(array $integrations, GenerationContext $context): void
    {
        foreach (array_keys($context->document->integrations) as $key) {
            if (! isset($integrations[$key])) {
                $context->diagnostics->warning(Stage::Configuration, "no available integration reads documents.{$context->document->name}.integrations.{$key}, settings ignored");
            }
        }
    }

    /**
     * The passes of the document integrations, in their order, each guarded (`IntegrationPass`); an integration that
     * cannot give them, or gives something that is not a pass, is reported.
     *
     * @param list<DocumentIntegration> $integrations the available ones
     *
     * @return list<Pass>
     */
    private static function passes(array $integrations, GenerationContext $context): array
    {
        $passes = [];

        foreach ($integrations as $integration) {
            $key = $integration->key();

            try {
                $given = self::given($integration->passes($context));
            } catch (Throwable $error) {
                $context->diagnostics->warning(Stage::Pass, "cannot add the passes of the integration {$key}: {$error->getMessage()}");

                continue;
            }

            foreach ($given as $pass) {
                if ($pass instanceof Pass) {
                    $passes[] = new IntegrationPass($key, $pass);
                } else {
                    $context->diagnostics->warning(Stage::Pass, "the integration {$key} gives a pass that does not implement " . Pass::class . ', left out: ' . get_debug_type($pass));
                }
            }
        }

        return $passes;
    }

    /**
     * What an integration gives as its passes, read as anything: its PHPDoc type is not checked at runtime.
     *
     * @return list<mixed>
     */
    private static function given(mixed $passes): array
    {
        return is_array($passes) ? array_values($passes) : [$passes];
    }

    /**
     * What the document integrations describe of the document, in their order; one that throws is reported.
     *
     * @param list<DocumentIntegration> $integrations the available ones
     *
     * @return list<string>
     */
    private static function notes(array $integrations, GenerationContext $context): array
    {
        $notes = [];

        foreach ($integrations as $integration) {
            try {
                $note = $integration->describe($context);
            } catch (Throwable $error) {
                $context->diagnostics->warning(Stage::Pass, "the integration {$integration->key()} cannot describe the document {$context->document->name}: {$error->getMessage()}");

                continue;
            }

            if ($note !== null) {
                $notes[] = $note;
            }
        }

        return $notes;
    }

    /**
     * @param list<Integration> $integrations the available ones
     */
    private function describe(GenerationContext $context, array $integrations, ErrorBodies $errors): DocumentDescription
    {
        $configuration = $context->document;
        $factory = new OperationContextFactory($this->types);
        $operations = $this->operations($configuration);
        $formats = array_merge(...array_map(static fn(Integration $integration): array => $integration->resourceFormats(), $integrations));
        $mappers = array_merge(...array_map(static fn(Integration $integration): array => $integration->ruleMappers(), $integrations));
        $samplers = Samplers::default($this->session, $this->types)->with(...$this->extensions->samplers);
        $responses = OperationResponses::default($samplers, $this->types, $formats, $errors, $this->extensions->responseExtractors);
        $requests = RequestExtractors::default($this->container, $this->query($configuration), RuleMappers::default()->with(...$mappers, ...$this->extensions->ruleMappers), $this->types, $responses->resources())
            ->with(...$this->extensions->requestExtractors);
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
