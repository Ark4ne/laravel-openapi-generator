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
     */
    public function __construct(
        public Configuration $configuration,
        public DocumentConfiguration $document,
        public Diagnostics $diagnostics = new Diagnostics(),
        public ComponentRegistry $components = new ComponentRegistry(),
        public ?string $locale = null,
        public array $requestPreparers = [],
    ) {
        $this->sample = new SampleRequest();
    }

    /**
     * A context for the given document, in that locale, with its own diagnostics and components.
     *
     * @param array<array-key, Closure(Request, GenerationContext): void> $requestPreparers
     */
    public static function for(Configuration $configuration, DocumentConfiguration $document, ?string $locale = null, array $requestPreparers = []): self
    {
        $diagnostics = new Diagnostics();

        return new self($configuration, $document, $diagnostics, new ComponentRegistry($diagnostics), $locale, $requestPreparers);
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
                $by = is_string($name) ? " for the integration {$name}" : '';
                $this->diagnostics->warning($stage, "cannot prepare {$what}{$by}: {$error->getMessage()}", $route);
            }
        }

        return $request;
    }
}
