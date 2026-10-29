<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Output;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Context\Stage;
use Ark4ne\OpenApiWriter\Enums\Strictness;
use Ark4ne\OpenApiWriter\Objects\OpenApi;
use Ark4ne\OpenApiWriter\Serialization\Serializer;
use Ark4ne\OpenApiWriter\Support\JsonPointer;
use Ark4ne\OpenApiWriter\Validation\JsonSchemaValidator;
use Ark4ne\OpenApiWriter\Validation\OpisJsonSchemaValidator;
use Ark4ne\OpenApiWriter\Validation\Severity;
use Ark4ne\OpenApiWriter\Validation\Validator;
use Ark4ne\OpenApiWriter\Validation\Violation;
use Ark4ne\OpenApiWriter\Validation\Violations;
use JsonException;

/**
 * Validates the document as it is written: the official schema (when `opis/json-schema` is installed) and the
 * semantic rules of the writer, run on the output of the version and the vendor extensions of the document.
 *
 * What the version cannot express is reported by the writing (DocumentWriter), not here: the writer's Validator
 * stops at such features, while a lenient document is written without them and must still be valid. The violations
 * become diagnostics; `--strict` fails on them.
 */
final readonly class DocumentValidator
{
    private Validator $validator;

    private ?JsonSchemaValidator $schemas;

    public function __construct(?JsonSchemaValidator $schemas = null)
    {
        $this->schemas = $schemas ?? (class_exists('Opis\JsonSchema\Validator') ? new OpisJsonSchemaValidator() : null);
        $this->validator = Validator::create();
    }

    public function validate(OpenApi $document, GenerationContext $context): Violations
    {
        $serializer = Serializer::for($context->document->version, Strictness::Lenient, $context->document->vendorExtensions);
        $written = $serializer->serialize($document);
        $violations = new Violations();

        try {
            $this->schemas?->validate($written->toJson(), $serializer->version, $violations);
        } catch (JsonException $exception) {
            $violations->add(new Violation(JsonPointer::root(), self::class, "the document cannot be written as JSON: {$exception->getMessage()}"));
        }

        foreach ($this->validator->rules() as $rule) {
            $rule->check($written->toArray(), $serializer->version, $violations);
        }

        foreach ($violations as $violation) {
            if ($violation->severity === Severity::Error) {
                $context->diagnostics->error(Stage::Validation, (string) $violation);
            } else {
                $context->diagnostics->warning(Stage::Validation, (string) $violation);
            }
        }

        return $violations;
    }
}
