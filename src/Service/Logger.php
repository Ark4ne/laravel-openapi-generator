<?php

namespace Ark4ne\OpenApi\Service;

use Illuminate\Support\Facades\Log;

class Logger
{
    const ICONS = [
        'error' => '✖',
        'warn' => '⚠',
        'success' => '✔',
        'notice' => '⌙',
        'info' => 'ℹ',
    ];

    const MAP = [
        'error' => 'error',
        'warn' => 'warn',
        'success' => 'info',
        'notice' => 'comment',
        'info' => 'line',
    ];

    protected array $interseptors = [];
    protected array $operations = [];

    public function start(string $name, string $lvl = 'line'): void
    {
        $this->$lvl("$name ");
        $this->operations[] = ['name' => $name, 'actions' => []];
    }

    public function end(string $lvl = null, string $message = null): void
    {
        $newline = empty($this->operations) || (bool)count($this->operations[array_key_last($this->operations)]['actions']);

        array_pop($this->operations);
        if ($lvl && !($newline && $lvl === 'success')) {
            $this->$lvl($message ?? '', $newline);
        }
    }

    public function interseptor(\Closure $closure): void
    {
        $this->interseptors[] = $closure;
    }

    /**
     * @param string          $action
     * @param (string|bool)[] $args
     *
     * @return void
     */
    public function __call(string $action, array $args): void
    {
        if (!empty($this->operations)) {
            $this->operations[array_key_last($this->operations)]['actions'][] = $action;
        }
        $newline = $args[1] ?? true;
        $msg = (array)($args[0] ?? []);
        $icon = self::ICONS[$action] ?? '';
        $lvl = self::MAP[$action] ?? $action;

        $indent = ((str_repeat('    ', count($this->operations))));

        if ($icon && $msg) {
            $icon = "$icon ";
        }

        $msg = implode($icon ? "\n$indent  " : "\n$indent", array_merge(...array_map(static fn($msg) => explode("\n", $msg), $msg)));

        if ($lvl === 'error') {
            $msg = "<fg=#ff0000>$icon$msg</>";
        }
        if ($lvl === 'blue') {
            $msg = "<fg=blue>$icon$msg</>";
        }
        if ($lvl === 'warn') {
            $msg = "<fg=yellow>$icon$msg</>";
        }
        if ($lvl === 'comment') {
            $msg = "<fg=gray>$icon$msg</>";
        }
        if ($lvl === 'info') {
            $msg = "<info>$icon$msg</info>";
        }
        if ($lvl === 'line') {
            $msg = "$icon$msg";
        }
        if ($newline) {
            $msg = PHP_EOL . $indent . $msg;
        }
        foreach ($this->interseptors as $interseptor) {
            $interseptor($msg, false);
        }
    }
}
