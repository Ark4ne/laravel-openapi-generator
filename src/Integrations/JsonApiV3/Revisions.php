<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Integrations\JsonApiV3;

use Ark4ne\ApiRevisions\Revisions\Revision;
use Ark4ne\ApiRevisions\Revisions\RevisionOrder;
use Ark4ne\JsonApi\Compilation\Definitions;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Context\Stage;
use Closure;
use WeakMap;

/**
 * The revision a document documents, from its settings (`documents.<name>.integrations.json_api.revision`, a case of
 * the revision enum the application configures for `ark4ne/laravel-api-revisions`, `revisions.enum`), read once per
 * context. `null`, the head, without the setting; with an invalid one (not a case of that enum, an application
 * without revision enum), with a warning. A setting other than `revision` is a warning.
 */
final class Revisions
{
    /** The key of the settings of the integration in a document. */
    public const KEY = 'json_api';

    /** The settings the integration reads. */
    private const SETTINGS = ['revision'];

    /** @var WeakMap<GenerationContext, array{Revision|null}> the revision of each context, once read */
    private WeakMap $revisions;

    /**
     * @param (Closure(): (class-string|null))|null $enum the revision enum of the application; that of the configuration
     *        of `ark4ne/laravel-json-api` (`Definitions::configuration()->revisions`) when `null`
     */
    public function __construct(
        private readonly ?Closure $enum = null,
    ) {
        $this->revisions = new WeakMap();
    }

    /**
     * The revision the document of the context documents, `null` for the head.
     */
    public function of(GenerationContext $context): ?Revision
    {
        $read = $this->revisions[$context] ?? null;

        if ($read === null) {
            $read = [$this->read($context)];
            $this->revisions[$context] = $read;
        }

        return $read[0];
    }

    /**
     * The name of a revision, as a client sends it: its backed value.
     */
    public static function name(Revision $revision): string
    {
        return (string) $revision->value;
    }

    /**
     * The changelog of the revisions newer than `$revision`, oldest first (`- 2026-12-01: Names become labels`, the
     * description of its `#[Meta]`); `null` for the newest revision and the head.
     */
    public static function changelog(?Revision $revision): ?string
    {
        if ($revision === null) {
            return null;
        }

        $lines = [];

        foreach ($revision::order() as $case) {
            if (RevisionOrder::compare($case, $revision) > 0) {
                $description = $case->meta()->description;
                $lines[] = '- ' . self::name($case) . ($description === '' ? '' : ": {$description}");
            }
        }

        return $lines === [] ? null : "Newer revisions:\n\n" . implode("\n", $lines);
    }

    /**
     * The `revision` setting of the document, the other settings and an invalid value reported.
     */
    private function read(GenerationContext $context): ?Revision
    {
        $settings = $context->document->integration(self::KEY);
        $key = "documents.{$context->document->name}.integrations." . self::KEY;

        foreach (array_keys($settings) as $name) {
            if (! in_array($name, self::SETTINGS, true)) {
                $context->diagnostics->warning(Stage::Configuration, "{$key}.{$name}: unknown setting, ignored (known: " . implode(', ', self::SETTINGS) . ')');
            }
        }

        $value = $settings['revision'] ?? null;

        if ($value === null) {
            return null;
        }

        $enum = $this->enum === null ? Definitions::configuration()->revisions : ($this->enum)();

        if ($enum === null) {
            $context->diagnostics->warning(Stage::Configuration, "{$key}.revision: the application configures no revision enum (revisions.enum); the head documented");

            return null;
        }

        if ($value instanceof Revision && $value instanceof $enum) {
            return $value;
        }

        $given = is_string($value) ? "\"{$value}\"" : get_debug_type($value);
        $context->diagnostics->warning(Stage::Configuration, "{$key}.revision: expected a case of {$enum}, got {$given}; the head documented");

        return null;
    }
}
