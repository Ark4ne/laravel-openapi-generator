<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Integrations\JsonApiV2;

use Ark4ne\JsonApi\Definition\Revision;
use Ark4ne\JsonApi\Definition\RevisionOrder;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Context\Stage;
use BackedEnum;
use WeakMap;

/**
 * The revision a document documents, from its settings (`documents.<name>.integrations.json_api.revision`, a case of
 * an enum implementing `Ark4ne\JsonApi\Definition\Revision`), read once per context. `null`, the head, without the
 * setting; with an invalid one (not a case of a revision enum, a package without revisions), with a warning. A setting
 * other than `revision` is a warning.
 *
 * Every class of the package that knows revisions is reached only when it exists (2.1): with 2.0, every document is
 * the head.
 */
final class Revisions
{
    /** The key of the settings of the integration in a document. */
    public const KEY = 'json_api';

    /** The interface the revisions implement, named as a string so that nothing is loaded with 2.0. */
    public const REVISION = 'Ark4ne\JsonApi\Definition\Revision';

    /** The settings the integration reads. */
    private const SETTINGS = ['revision'];

    /** @var WeakMap<GenerationContext, array{Revision|null}> the revision of each context, once read */
    private WeakMap $revisions;

    public function __construct()
    {
        $this->revisions = new WeakMap();
    }

    /**
     * The revision the document of the context documents, `null` for the head.
     */
    public function of(GenerationContext $context): ?Revision
    {
        $read = $this->revisions[$context] ?? null;

        if ($read === null) {
            $read = [self::read($context)];
            $this->revisions[$context] = $read;
        }

        return $read[0];
    }

    /**
     * Whether the installed package knows revisions (2.1).
     */
    public static function supported(): bool
    {
        return interface_exists(self::REVISION);
    }

    /**
     * The name of a revision, as a client sends it: the value of a backed case, else the name of the case.
     */
    public static function name(Revision $revision): string
    {
        return $revision instanceof BackedEnum ? (string) $revision->value : $revision->name;
    }

    /**
     * The changelog of the revisions newer than `$revision`, oldest first (`- 2026-12-01: Names become labels`);
     * `null` for the newest revision and the head.
     */
    public static function changelog(?Revision $revision): ?string
    {
        if ($revision === null) {
            return null;
        }

        $lines = [];

        foreach ($revision::cases() as $case) {
            if (RevisionOrder::compare($case, $revision) > 0) {
                $lines[] = '- ' . self::name($case) . ": {$case->description()}";
            }
        }

        return $lines === [] ? null : "Newer revisions:\n\n" . implode("\n", $lines);
    }

    /**
     * The `revision` setting of the document, the other settings and an invalid value reported.
     */
    private static function read(GenerationContext $context): ?Revision
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

        if (! self::supported()) {
            $context->diagnostics->warning(Stage::Configuration, "{$key}.revision: the installed ark4ne/laravel-json-api has no revisions (2.1 has), the head documented");

            return null;
        }

        if ($value instanceof Revision) {
            return $value;
        }

        $given = is_string($value) ? "\"{$value}\"" : get_debug_type($value);
        $context->diagnostics->warning(Stage::Configuration, "{$key}.revision: expected a case of an enum implementing " . self::REVISION . ", got {$given}; the head documented");

        return null;
    }
}
