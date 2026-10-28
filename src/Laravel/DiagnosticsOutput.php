<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Laravel;

use Ark4ne\OpenApi\Context\Diagnostic;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Prints diagnostics grouped by route, those of the document as a whole first. The errors are printed even with
 * `-q`, the warnings by default, the infos with `-v`.
 */
final readonly class DiagnosticsOutput
{
    private const VERBOSITIES = [
        'error' => OutputInterface::VERBOSITY_QUIET,
        'warning' => OutputInterface::VERBOSITY_NORMAL,
        'info' => OutputInterface::VERBOSITY_VERBOSE,
    ];

    private const STYLES = ['error' => 'fg=red', 'warning' => 'fg=yellow', 'info' => 'fg=gray'];

    public function __construct(
        private OutputInterface $output,
    ) {}

    /**
     * @param iterable<Diagnostic> $diagnostics
     * @param bool $all every level, whatever the verbosity (`openapi:inspect`)
     */
    public function print(iterable $diagnostics, bool $all = false): void
    {
        $groups = [];

        foreach ($diagnostics as $diagnostic) {
            $groups[$diagnostic->route ?? ''][] = $diagnostic;
        }

        ksort($groups);

        foreach ($groups as $route => $group) {
            $verbosity = $all ? OutputInterface::VERBOSITY_QUIET : min(array_map(static fn(Diagnostic $diagnostic): int => self::VERBOSITIES[$diagnostic->level->value], $group));
            $this->output->writeln('  <options=bold>' . ($route === '' ? 'Document' : $route) . '</>', $verbosity);

            foreach ($group as $diagnostic) {
                $level = $diagnostic->level->value;
                $this->output->writeln("    <" . self::STYLES[$level] . ">{$level}</> {$diagnostic->stage->value}: {$diagnostic->message}", $all ? OutputInterface::VERBOSITY_QUIET : self::VERBOSITIES[$level]);
            }
        }
    }
}
