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

    const METHODS = [
        'HEAD' => 'comment',
        'GET' => 'blue',
        'POST' => 'warn',
        'PUT' => 'ff00ff',
        'PATCH' => 'ff00ff',
        'DELETE' => 'error',
        'TRACE' => 'info',
        'OPTIONS' => 'comment',
    ];

    protected array $interceptors = [];
    protected array $operations = [];

    public function request(string $method, string $uri): void
    {
        $this->write($method, self::METHODS[$method] ?? 'comment', newline: true);
        $this->write(str_pad('', 8 - strlen($method), '.'), 'comment', newline: false);
        $this->start($uri, newline: false);
    }

    public function start(string $name, string $lvl = 'line', bool $newline = null): void
    {
        $this->$lvl("$name ", $newline);
        $this->operations[] = ['name' => $name, 'actions' => []];
    }

    public function end(string $lvl = null, string $message = null): void
    {
        $newline = empty($this->operations) || (bool) count($this->operations[array_key_last($this->operations)]['actions']);

        array_pop($this->operations);
        if ($lvl && !($newline && $lvl === 'success')) {
            $this->$lvl($message ?? '', $newline);
        }
    }

    public function interceptor(\Closure $closure): void
    {
        $this->interceptors[] = $closure;
    }

    /**
     * @param string $action
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
        $msg = (array) ($args[0] ?? []);
        $icon = self::ICONS[$action] ?? '';
        $lvl = self::MAP[$action] ?? $action;

        $indent = ((str_repeat('    ', count($this->operations))));

        if ($icon && $msg) {
            $icon = "$icon ";
        }

        $msg = implode($icon ? "\n$indent  " : "\n$indent", array_merge(...array_map(static fn ($msg) => explode("\n", $msg), $msg)));

        $this->write("$icon$msg", $lvl, $indent, $newline);
    }

    protected function write(string $message, string $color, string $indent = '', bool $newline = false)
    {
        if ($color === 'error') {
            $msg = "<fg=#ff0000>$message</>";
        }
        elseif ($color === 'blue') {
            $msg = "<fg=blue>$message</>";
        }
        elseif ($color === 'warn') {
            $msg = "<fg=yellow>$message</>";
        }
        elseif ($color === 'comment') {
            $msg = "<fg=gray>$message</>";
        }
        elseif ($color === 'info') {
            $msg = "<info>$message</info>";
        }
        elseif ($color === 'line') {
            $msg = $message;
        }
        elseif ($color) {
            $msg = "<fg=#$color>$message</>";
        }
        else {
            $msg = $message;
        }
        if ($newline) {
            $msg = PHP_EOL . $indent . $msg;
        }
        foreach ($this->interceptors as $interceptor) {
            $interceptor($msg, false);
        }
    }
}
