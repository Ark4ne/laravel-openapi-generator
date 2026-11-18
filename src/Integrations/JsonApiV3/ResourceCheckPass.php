<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Integrations\JsonApiV3;

use Ark4ne\ApiRevisions\Checking\Checker;
use Ark4ne\JsonApi\Compilation\Definitions;
use Ark4ne\JsonApi\Compilation\ResourceChecker;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Context\Stage;
use Ark4ne\OpenApi\Description\DocumentDescription;
use Ark4ne\OpenApi\Generation\Pass;
use Illuminate\Contracts\Container\Container;
use Throwable;

/**
 * The check of the package (`Compilation\ResourceChecker`, that of `jsonapi:check`), run once per document on the
 * JSON:API resources it documents (`ResourceDefinitions::covered()`), at its revision (the head revision for a
 * document of the head): the findings of that revision and those of no revision in particular are relayed as
 * diagnostics of the document, each once, with its code and its revision. The check of the application (its
 * `Checking\Checker`, from the container) when it has one, that of the package otherwise. The document is unchanged.
 */
final readonly class ResourceCheckPass implements Pass
{
    /**
     * The checker of `ark4ne/laravel-api-revisions`, named as a string: only this integration loads its classes.
     */
    public const CHECKER_CLASS = 'Ark4ne\ApiRevisions\Checking\Checker';

    public function __construct(
        private ResourceDefinitions $definitions,
        private Revisions $revisions,
        private ?Container $container = null,
    ) {}

    public function process(DocumentDescription $document, GenerationContext $context): DocumentDescription
    {
        $classes = $this->definitions->covered($context);

        if ($classes === []) {
            return $document;
        }

        $revision = $this->revisions->of($context) ?? Definitions::head();

        try {
            $checker = $this->container?->bound(self::CHECKER_CLASS) === true ? $this->container->make(self::CHECKER_CLASS) : null;
            $found = (new ResourceChecker(Definitions::compiler(), $checker instanceof Checker ? $checker : null))->check($classes, $revision);
        } catch (Throwable $error) {
            $context->diagnostics->warning(Stage::Pass, "cannot check the JSON:API resources: {$error->getMessage()}");

            return $document;
        }

        foreach ($found as $class => $diagnostics) {
            foreach ($diagnostics as $diagnostic) {
                $shown = $diagnostic->revision === $revision->name ? Revisions::name($revision) : $diagnostic->revision;
                $this->definitions->relay($context, Stage::Pass, $class, $diagnostic, $shown);
            }
        }

        return $document;
    }
}
