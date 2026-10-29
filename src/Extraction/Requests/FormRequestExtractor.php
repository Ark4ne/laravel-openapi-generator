<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Requests;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Context\Stage;
use Ark4ne\OpenApi\Description\RequestDescription;
use Ark4ne\OpenApi\Extraction\OperationContext;
use Ark4ne\OpenApi\Extraction\Rules\FieldsBuilder;
use Ark4ne\OpenApi\Extraction\Rules\RuleParser;
use ReflectionMethod;
use Throwable;

/**
 * The rules of the form request of the action, placed in the query or the body. A request that redefines
 * `authorize()` can answer 403; one with rules, 422.
 */
final readonly class FormRequestExtractor implements RequestExtractor
{
    public const PRIORITY = 100;

    private const FORM_REQUEST = 'Illuminate\Foundation\Http\FormRequest';

    public function __construct(
        private RequestFactory $requests,
        private RuleParser $parser,
        private FieldsBuilder $fields,
        private FieldPlacer $placer,
    ) {}

    /**
     * Whether the request checks an authorization: Laravel 13's FormRequest has no `authorize()` of its own.
     *
     * @param class-string $class
     */
    private static function authorizes(string $class): bool
    {
        return method_exists($class, 'authorize') && (new ReflectionMethod($class, 'authorize'))->getDeclaringClass()->getName() !== self::FORM_REQUEST;
    }

    public function priority(): int
    {
        return self::PRIORITY;
    }

    public function supports(OperationContext $operation): bool
    {
        return RequestClass::of($operation, self::FORM_REQUEST) !== null;
    }

    public function extract(OperationContext $operation, RequestDescription $request, GenerationContext $context): RequestDescription
    {
        $class = RequestClass::of($operation, self::FORM_REQUEST);

        if ($class === null) {
            return $request;
        }

        $label = $operation->route->label();

        // Reading the rules runs application code (rules(), Rule::when() closures, rules describing themselves).
        try {
            $rules = $this->requests->rules($this->requests->make($class, $operation));
            $fields = $this->fields->build($this->parser->parse($rules, $context, $label), $context, $label);
        } catch (Throwable $error) {
            $context->diagnostics->warning(Stage::Request, "cannot read the rules of {$class}: {$error->getMessage()}", $label);

            return $request;
        }

        $request = $request
            ->authorized($request->authorized || self::authorizes($class))
            ->validated($request->validated || $rules !== []);

        return $this->placer->place($request, $fields, $operation, $context);
    }
}
