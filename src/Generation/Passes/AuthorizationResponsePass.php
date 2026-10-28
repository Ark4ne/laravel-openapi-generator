<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Generation\Passes;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\DocumentDescription;
use Ark4ne\OpenApi\Description\OperationDescription;
use Ark4ne\OpenApi\Description\SecurityDescription;
use Ark4ne\OpenApi\Generation\ErrorBodies;
use Ark4ne\OpenApi\Generation\Middleware;
use Ark4ne\OpenApi\Generation\Pass;
use Ark4ne\OpenApi\Generation\SharedResponses;

/**
 * 401 on the operations that require an authentication (their security, else the default one), 403 on those that
 * check an authorization (`can:` / `Authorize`, a form request redefining `authorize()`).
 */
final readonly class AuthorizationResponsePass implements Pass
{
    public function __construct(
        private ErrorBodies $errors,
    ) {}

    public function process(DocumentDescription $document, GenerationContext $context): DocumentDescription
    {
        $operations = [];

        foreach ($document->operations as $operation) {
            if (self::authenticated($operation->security ?? $document->security)) {
                [$operation, $document] = SharedResponses::add($operation, $document, 'Unauthenticated', 401, 'Unauthenticated', $this->errors->error($context));
            }

            if ($operation->request->authorized || self::authorizes($operation)) {
                [$operation, $document] = SharedResponses::add($operation, $document, 'Forbidden', 403, 'Forbidden', $this->errors->error($context));
            }

            $operations[] = $operation;
        }

        return $document->operations(...$operations);
    }

    /**
     * Whether every alternative requires a scheme: an empty requirement makes the authentication optional.
     *
     * @param list<SecurityDescription> $security
     */
    private static function authenticated(array $security): bool
    {
        return $security !== [] && array_filter($security, static fn(SecurityDescription $requirement): bool => $requirement->schemes === []) === [];
    }

    private static function authorizes(OperationDescription $operation): bool
    {
        foreach ($operation->middlewares as $middleware) {
            if (Middleware::parse($middleware)->name === 'can') {
                return true;
            }
        }

        return false;
    }
}
