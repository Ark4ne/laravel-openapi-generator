<?php

namespace Ark4ne\OpenApi\Contracts;

interface Entry
{
    public function getUri(): string;

    public function getController(): mixed;

    public function getControllerClass(): string;

    public function getAction(): string;

    public function getResponseClass(): ?string;

    /**
     * @return null|class-string<\Illuminate\Foundation\Http\FormRequest>
     */
    public function getRequestClass(): ?string;
}
