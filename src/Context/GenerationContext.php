<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Context;

use Ark4ne\OpenApi\Configuration\Configuration;
use Ark4ne\OpenApi\Configuration\DocumentConfiguration;
use Closure;
use Illuminate\Http\Request;
use Throwable;

/**
 * State of the generation of one document in one language, passed explicitly to every extractor, sampler and pass.
 *
 * It replaces the static state of v0.6 (Config::version(), ArrayCache, Logger, Component). It is not a service
 * container: dependencies arrive through the constructors.
 */
final readonly class GenerationContext
{
    private SampleRequest $sample;

    /**
     * @param array<array-key, Closure(Request, GenerationContext): void> $requestPreparers what prepares every request
     *        the generator builds for the application, in order: by integration key, named in the diagnostics (those of
     *        the available document integrations), or a list
     * @param array<array-key, Closure(array<array-key, mixed>, Request, GenerationContext): array<array-key, mixed>> $rulesPreparers
     *        what prepares the rules read from a request before they are parsed, in order, keyed as `$requestPreparers`
     */
    public function __construct(
        public Configuration $configuration,
        public DocumentConfiguration $document,
        public Diagnostics $diagnostics = new Diagnostics(),
        public ComponentRegistry $components = new ComponentRegistry(),
        public ?string $locale = null,
        public array $requestPreparers = [],
        public array $rulesPreparers = [],
    ) {
        $this->sample = new SampleRequest();
    }

    /**
     * A context for the given document, in that locale, with its own diagnostics and components.
     *
     * @param array<array-key, Closure(Request, GenerationContext): void> $requestPreparers
     * @param array<array-key, Closure(array<array-key, mixed>, Request, GenerationContext): array<array-key, mixed>> $rulesPreparers
     */
    public static function for(Configuration $configuration, DocumentConfiguration $document, ?string $locale = null, array $requestPreparers = [], array $rulesPreparers = []): self
    {
        $diagnostics = new Diagnostics();

        return new self($configuration, $document, $diagnostics, new ComponentRegistry($diagnostics), $locale, $requestPreparers, $rulesPreparers);
    }

    /**
     * A new `GET /` request on which the application renders its samples (resources, responsables), prepared as
     * `prepareRequest()` does: once per context, each call returning its own copy. The only place of `src/` that
     * creates a plain request.
     */
    public function sampleRequest(): Request
    {
        return $this->sample->get(fn(): Request => $this->prepareRequest(Request::create('/')));
    }

    /**
     * Prepares a request the generator builds for the application (a sample request, the form request whose rules are
     * read): every request preparer, in order. A preparer that throws is reported at `$stage`, for `$route` when
     * given; the next ones still run.
     */
    public function prepareRequest(Request $request, Stage $stage = Stage::Sampling, ?string $route = null): Request
    {
        foreach ($this->requestPreparers as $name => $prepare) {
            try {
                $prepare($request, $this);
            } catch (Throwable $error) {
                $what = $stage === Stage::Sampling ? 'a sample request' : 'the request ' . $request::class;
                $this->diagnostics->warning($stage, "cannot prepare {$what}" . self::by($name) . ": {$error->getMessage()}", $route);
            }
        }

        return $request;
    }

    /**
     * Prepares the rules read from a request before they are parsed: every rules preparer, in order, each given what
     * the previous one returned. A preparer that throws is reported at the `request` stage, for `$route` when given,
     * the rules it was given kept; the next ones still run.
     *
     * @param array<array-key, mixed> $rules
     *
     * @return array<array-key, mixed>
     */
    public function prepareRules(array $rules, Request $request, ?string $route = null): array
    {
        foreach ($this->rulesPreparers as $name => $prepare) {
            try {
                $rules = $prepare($rules, $request, $this);
            } catch (Throwable $error) {
                $this->diagnostics->warning(Stage::Request, 'cannot prepare the rules of ' . $request::class . self::by($name) . ": {$error->getMessage()}", $route);
            }
        }

        return $rules;
    }

    /**
     * ` for the integration json_api` for a preparer keyed by integration, nothing for one of a list.
     */
    private static function by(int|string $name): string
    {
        return is_string($name) ? " for the integration {$name}" : '';
    }
}
