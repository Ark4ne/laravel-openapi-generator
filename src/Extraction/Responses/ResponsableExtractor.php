<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Responses;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Context\Stage;
use Ark4ne\OpenApi\Description\HeaderDescription;
use Ark4ne\OpenApi\Description\ResponseDescription;
use Ark4ne\OpenApi\Extraction\OperationContext;
use Ark4ne\OpenApi\Sampling\SampleRequest;
use Ark4ne\OpenApi\Sampling\Samplers;
use Ark4ne\OpenApi\Sampling\SchemaInferrer;
use Ark4ne\OpenApi\Types\NamedType;
use Ark4ne\OpenApi\Types\Type;
use Ark4ne\OpenApiWriter\Objects\Schema;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

/**
 * A `Responsable`: built (without constructor argument, else sampled), its `toResponse()` run; a JSON response gives
 * its status, its data and its headers (technical headers left out).
 */
final readonly class ResponsableExtractor implements ResponseExtractor
{
    public const PRIORITY = 250;

    /**
     * Headers every response has.
     */
    public const TECHNICAL = ['cache-control', 'date', 'content-type', 'content-length', 'set-cookie', 'vary', 'connection'];

    public function __construct(
        private Samplers $samplers,
        private SchemaInferrer $inferrer,
    ) {}

    public function priority(): int
    {
        return self::PRIORITY;
    }

    public function supports(Type $type, OperationContext $operation): bool
    {
        // Resources and responses (RedirectResponse is Responsable in Laravel 13) have their own extractors.
        return $type instanceof NamedType
            && is_a($type->name, Responsable::class, true)
            && ! is_a($type->name, JsonResource::class, true)
            && ! is_a($type->name, SymfonyResponse::class, true);
    }

    public function extract(Type $type, OperationContext $operation, GenerationContext $context): array
    {
        if (! $type instanceof NamedType || ! class_exists($type->name)) {
            return [];
        }

        try {
            $class = new ReflectionClass($type->name);
            $instance = ($class->getConstructor()?->getNumberOfRequiredParameters() ?? 0) === 0 ? $class->newInstance() : ($this->samplers->sample(new SampleRequest($type->name), $context)[0] ?? null);
            $response = $instance instanceof Responsable ? $instance->toResponse(Request::create('/')) : null;
        } catch (Throwable $error) {
            $context->diagnostics->warning(Stage::Response, "cannot run {$type->name}::toResponse(): {$error->getMessage()}", $operation->route->label());

            return [];
        }

        if (! $response instanceof SymfonyResponse) {
            return [];
        }

        $status = $response->getStatusCode();
        $description = ResponseDescription::create($status)->description(SymfonyResponse::$statusTexts[$status] ?? null);

        foreach (array_keys($response->headers->allPreserveCaseWithoutCookies()) as $name) {
            if (! in_array(strtolower($name), self::TECHNICAL, true)) {
                $description = $description->addHeaders(HeaderDescription::create($name, Schema::string()));
            }
        }

        return [$response instanceof JsonResponse ? $description->content('application/json', $this->inferrer->infer($response->getData(true), $context)) : $description];
    }
}
