<?php

declare(strict_types=1);

/*
 * Fails when the line coverage of a Clover report is below the given threshold. The files under the excluded paths
 * are left out: the integration of the ark4ne/laravel-json-api line that is not installed.
 *
 * Usage: php bin/coverage-check.php <clover.xml> <minimum percentage> [<excluded path>...]
 */

$file = $argv[1] ?? null;
$threshold = $argv[2] ?? null;
$excluded = array_slice($argv ?? [], 3);

if ($threshold === null || !is_numeric($threshold)) {
    fwrite(STDERR, 'Usage: php bin/coverage-check.php <clover.xml> <minimum percentage> [<excluded path>...]' . PHP_EOL);
    exit(1);
}

$minimum = (float) $threshold;

if ($file === null || !is_file($file)) {
    fwrite(STDERR, "Clover report not found: " . ($file ?? '(none)') . PHP_EOL);
    exit(1);
}

$xml = simplexml_load_file($file);
if ($xml === false) {
    fwrite(STDERR, "Unreadable Clover report: {$file}" . PHP_EOL);
    exit(1);
}

$prefixes = [];

foreach ($excluded as $path) {
    $real = realpath($path);

    if ($real === false) {
        fwrite(STDERR, "Excluded path not found: {$path}" . PHP_EOL);
        exit(1);
    }

    $prefixes[] = $real . DIRECTORY_SEPARATOR;
}

$statements = 0;
$covered = 0;

$sources = $xml->xpath('//file');

foreach (is_array($sources) ? $sources : [] as $source) {
    $name = (string) $source['name'];

    foreach ($prefixes as $prefix) {
        if (str_starts_with($name, $prefix)) {
            continue 2;
        }
    }

    $statements += (int) $source->metrics['statements'];
    $covered += (int) $source->metrics['coveredstatements'];
}

if ($statements === 0) {
    fwrite(STDERR, "No executable line in {$file}: is the coverage filter right?" . PHP_EOL);
    exit(1);
}

$coverage = $covered / $statements * 100;

fwrite(STDOUT, sprintf('Line coverage: %.2f%% (%d/%d), minimum %.2f%%', $coverage, $covered, $statements, $minimum) . PHP_EOL);

exit($coverage < $minimum ? 1 : 0);
