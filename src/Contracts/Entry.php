<?php

namespace Ark4ne\OpenApi\Contracts;

use Ark4ne\OpenApi\Support\Reflection\Type;

interface Entry
{
    public function getUri(): string;

    /**
     * @return array<string, null|string>
     */
    public function getPathParameters(): array;

    public function getController(): mixed;

    public function getControllerClass(): string;

    public function getAction(): string;

    /**
     * @return Type<\Illuminate\Http\Response, mixed>
     */
    public function getResponseClass(): Type;

    /**
     * @return Type<\Illuminate\Http\Request, null>
     */
    public function getRequestClass(): Type;
}
