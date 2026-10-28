<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Requests;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Context\Stage;
use Ark4ne\OpenApi\Description\BodyDescription;
use Ark4ne\OpenApi\Description\FieldDescription;
use Ark4ne\OpenApi\Description\ParameterDescription;
use Ark4ne\OpenApi\Description\RequestDescription;
use Ark4ne\OpenApi\Extraction\OperationContext;
use Ark4ne\OpenApi\Extraction\Rules\FieldsBuilder;
use Ark4ne\OpenApi\Extraction\Rules\FieldTree;
use Ark4ne\OpenApi\Extraction\Rules\RuleParser;
use Ark4ne\OpenApiWriter\Enums\ParameterLocation;
use Ark4ne\OpenApiWriter\Objects\Schema;
use Throwable;

/**
 * The manual description of a request class implementing DescribesRequest, applied over its rules.
 */
final readonly class DescribableRequestExtractor implements RequestExtractor
{
    public const PRIORITY = 200;

    public function __construct(
        private RequestFactory $requests,
        private RuleParser $parser,
        private FieldsBuilder $fields,
        private FieldPlacer $placer,
    ) {}

    public function priority(): int
    {
        return self::PRIORITY;
    }

    public function supports(OperationContext $operation): bool
    {
        return RequestClass::of($operation, DescribesRequest::class) !== null;
    }

    public function extract(OperationContext $operation, RequestDescription $request, GenerationContext $context): RequestDescription
    {
        $class = RequestClass::of($operation, DescribesRequest::class);

        if ($class === null) {
            return $request;
        }

        $label = $operation->route->label();

        // The description runs application code: describe(), and the rules it gives.
        try {
            return $this->describe($this->requests->make($class, $operation)->describe(RequestDescriber::create()), $request, $operation, $context, $label);
        } catch (Throwable $error) {
            $context->diagnostics->warning(Stage::Request, "cannot read the description of {$class}: {$error->getMessage()}", $label);

            return $request;
        }
    }

    private function describe(RequestDescriber $describer, RequestDescription $request, OperationContext $operation, GenerationContext $context, string $label): RequestDescription
    {
        $parameters = [];

        foreach ($describer->headers as $entry) {
            $parameters[] = $this->parameter($entry, $context, $label);
        }

        $request = $request->parameters(...FieldPlacer::merge($request->parameters, [...$parameters, ...$describer->parameters]));
        $request = $this->placer->place($request, $this->build($describer->query, $context, $label), $operation, $context, inQuery: true);

        foreach ($describer->bodies as $mediaType => $rules) {
            $request = $this->placer->place($request, $this->build($rules, $context, $label), $operation, $context, false, $mediaType === '' ? null : $mediaType);
        }

        $body = $request->body;

        foreach ($describer->schemas as $mediaType => $schema) {
            $body = ($body ?? BodyDescription::create())->content($mediaType, $schema);
        }

        if ($describer->bodyDescription !== null) {
            $body = ($body ?? BodyDescription::create())->description($describer->bodyDescription);
        }

        return $request->body($body);
    }

    /**
     * @param array{in: ParameterLocation, name: string, rules: Schema|string|array<array-key, mixed>, description: string|null, required: bool} $entry
     */
    private function parameter(array $entry, GenerationContext $context, string $label): ParameterDescription
    {
        $schema = $entry['rules'];
        $required = $entry['required'];

        if (! $schema instanceof Schema) {
            $field = $this->build([$entry['name'] => $schema], $context, $label)[0];
            $schema = FieldTree::leaf($field);
            $required = $required || $field->required;
        }

        $parameter = $entry['in'] === ParameterLocation::Cookie
            ? ParameterDescription::cookie($entry['name'], $schema)
            : ParameterDescription::header($entry['name'], $schema);

        return $parameter->required($required)->description($entry['description']);
    }

    /**
     * @param array<array-key, mixed> $rules
     *
     * @return list<FieldDescription>
     */
    private function build(array $rules, GenerationContext $context, string $label): array
    {
        return $this->fields->build($this->parser->parse($rules, $context, $label), $context, $label);
    }
}
