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
use Illuminate\Http\Request;
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
            $instance = $this->requests->make($class, $operation, $context);

            return $this->describe($instance->describe(RequestDescriber::create()), $instance, $request, $operation, $context, $label);
        } catch (Throwable $error) {
            $context->diagnostics->warning(Stage::Request, "cannot read the description of {$class}: {$error->getMessage()}", $label);

            return $request;
        }
    }

    /**
     * @param Request $instance the request described, on which its rules are prepared
     */
    private function describe(RequestDescriber $describer, Request $instance, RequestDescription $request, OperationContext $operation, GenerationContext $context, string $label): RequestDescription
    {
        $parameters = [];

        foreach ($describer->headers as $entry) {
            $parameter = $this->parameter($entry, $instance, $context, $label);

            if ($parameter !== null) {
                $parameters[] = $parameter;
            }
        }

        $request = $request->parameters(...FieldPlacer::merge($request->parameters, [...$parameters, ...$describer->parameters]));
        $request = $this->placer->place($request, $this->build($describer->query, $instance, $context, $label), $operation, $context, inQuery: true);

        foreach ($describer->bodies as $mediaType => $rules) {
            $request = $this->placer->place($request, $this->build($rules, $instance, $context, $label), $operation, $context, false, $mediaType === '' ? null : $mediaType);
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
     * A header or cookie parameter; `null` when its rules are left out by the preparation of the rules (a parameter
     * the document does not have).
     *
     * @param array{in: ParameterLocation, name: string, rules: Schema|string|array<array-key, mixed>, description: string|null, required: bool} $entry
     */
    private function parameter(array $entry, Request $instance, GenerationContext $context, string $label): ?ParameterDescription
    {
        $schema = $entry['rules'];
        $required = $entry['required'];

        if (! $schema instanceof Schema) {
            $field = $this->build([$entry['name'] => $schema], $instance, $context, $label)[0] ?? null;

            if ($field === null) {
                return null;
            }

            $schema = FieldTree::leaf($field);
            $required = $required || $field->required;
        }

        $parameter = $entry['in'] === ParameterLocation::Cookie
            ? ParameterDescription::cookie($entry['name'], $schema)
            : ParameterDescription::header($entry['name'], $schema);

        return $parameter->required($required)->description($entry['description']);
    }

    /**
     * The fields of rules the describer gives, prepared by the context first, as the rules of a form request.
     *
     * @param array<array-key, mixed> $rules
     *
     * @return list<FieldDescription>
     */
    private function build(array $rules, Request $instance, GenerationContext $context, string $label): array
    {
        return $this->fields->build($this->parser->parse($context->prepareRules($rules, $instance, $label), $context, $label), $context, $label);
    }
}
