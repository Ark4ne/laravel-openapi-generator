<?php

namespace Ark4ne\OpenApi\Contracts;

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
     * @return class-string<\Illuminate\Http\Response>
     */
    public function getResponseClass(): string;

    /**
     * @return class-string<\Illuminate\Http\Request>
     */
    public function getRequestClass(): string;
}
