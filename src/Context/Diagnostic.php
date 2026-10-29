<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Context;

use Stringable;

final readonly class Diagnostic implements Stringable
{
    /**
     * @param string|null $route `GET /users/{user}`, when the diagnostic concerns one operation
     */
    public function __construct(
        public DiagnosticLevel $level,
        public Stage $stage,
        public string $message,
        public ?string $route = null,
    ) {}

    /**
     * `[warning] response GET /users/{user}: cannot sample App\Http\Resources\UserResource`
     */
    public function __toString(): string
    {
        $where = $this->route === null ? $this->stage->value : "{$this->stage->value} {$this->route}";

        return "[{$this->level->value}] {$where}: {$this->message}";
    }
}
