<?php

declare(strict_types=1);

/*
 * Rebuilds the progress table of docs/README.md from the checklists of docs/E*.md.
 *
 * Usage: php bin/progress.php          rewrite docs/README.md
 *        php bin/progress.php --check  fail when docs/README.md is out of date
 */

$root = dirname(__DIR__);
$summaryFile = $root . '/docs/README.md';
$startMarker = '<!-- progress:start -->';
$endMarker = '<!-- progress:end -->';
$options = getopt('', ['check']);
$check = is_array($options) && array_key_exists('check', $options);

$files = glob($root . '/docs/E*.md');
if ($files === false || $files === []) {
    fwrite(STDERR, 'No epic document found in docs/.' . PHP_EOL);
    exit(1);
}
natsort($files);

$bar = static function (int $done, int $total): string {
    $filled = $total === 0 ? 0 : intdiv($done * 10, $total);

    return str_repeat('█', $filled) . str_repeat('░', 10 - $filled);
};

// Rounded down: an unfinished epic never shows a full bar or 100 %.
$percent = static fn(int $done, int $total): int => $total === 0 ? 0 : intdiv($done * 100, $total);

$rows = [];
$allDone = 0;
$allTotal = 0;

foreach ($files as $file) {
    $content = (string) file_get_contents($file);
    // Checkboxes shown as examples in fenced code blocks are not deliverables.
    $checklist = (string) preg_replace('/^(```|~~~).*?^\1[^\n]*$/ms', '', $content);
    $done = (int) preg_match_all('/^\s*[-*+] \[[xX]\](?=\s|$)/m', $checklist);
    $total = $done + (int) preg_match_all('/^\s*[-*+] \[ \](?=\s|$)/m', $checklist);
    $title = preg_match('/^# (.+)$/m', $content, $match) === 1 ? trim($match[1]) : basename($file, '.md');

    $status = match (true) {
        $total > 0 && $done === $total => 'Terminé',
        $done > 0 => 'En cours',
        default => 'À faire',
    };

    $rows[] = sprintf(
        '| [%s](%s) | %s | `%s` %d %% | %d / %d |',
        $title,
        basename($file),
        $status,
        $bar($done, $total),
        $percent($done, $total),
        $done,
        $total,
    );

    $allDone += $done;
    $allTotal += $total;
}

// \n and not PHP_EOL: .gitattributes checks docs/ out with LF on every platform.
$table = implode("\n", [
    $startMarker,
    sprintf(
        '**Avancement global : `%s` %d %% (%d / %d livrables)**',
        $bar($allDone, $allTotal),
        $percent($allDone, $allTotal),
        $allDone,
        $allTotal,
    ),
    '',
    '| Épique | Statut | Avancement | Livrables |',
    '| --- | --- | --- | --- |',
    ...$rows,
    $endMarker,
]);

$summary = (string) file_get_contents($summaryFile);
$start = strpos($summary, $startMarker);
$end = strpos($summary, $endMarker);

if ($start === false || $end === false || $end < $start) {
    fwrite(STDERR, "Progress markers not found in {$summaryFile}." . PHP_EOL);
    exit(1);
}

$updated = substr($summary, 0, $start) . $table . substr($summary, $end + strlen($endMarker));

if ($check) {
    if ($updated !== $summary) {
        fwrite(STDERR, 'docs/README.md is out of date: run `composer progress` and commit the result.' . PHP_EOL);
        exit(1);
    }

    fwrite(STDOUT, 'docs/README.md is up to date.' . PHP_EOL);
    exit(0);
}

file_put_contents($summaryFile, $updated);
fwrite(STDOUT, sprintf('Progress: %d / %d deliverables (%d %%).', $allDone, $allTotal, $percent($allDone, $allTotal)) . PHP_EOL);
