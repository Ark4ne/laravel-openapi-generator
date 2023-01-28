<?php

namespace Ark4ne\OpenApi\Contracts;

use Ark4ne\OpenApi\Documentation\RequestEntry;
use Ark4ne\OpenApi\Documentation\ResponseEntry;
use Ark4ne\OpenApi\Support\ArrayInsensitive;
use Ark4ne\OpenApi\Support\Reflection;
use phpDocumentor\Reflection\DocBlock;
use ReflectionMethod;

interface Entry
{
    /**
     * @return array<string>
     */
    public function getMethods(): array;

    public function getRouteUri(): string;

    public function getRouteName(): string;

    /**
     * @throws \ReflectionException
     * @return array<string, null|string>
     */
    public function getRouteParams(): array;

    public function getController(): mixed;

    public function getControllerClass(): string;

    public function getControllerName(): string;

    public function getAction(): string;

    public function getMethod(): ReflectionMethod;

    public function getDoc(): ?DocBlock;

    /**
     * @param string $tag
     *
     * @return \phpDocumentor\Reflection\DocBlock\Tags\BaseTag[]
     */
    public function getDocTag(string $tag): array;

    public function getDocDescription(): ?string;

    public function getDocResponseStatus(): ?string;

    public function getDocResponseStatusCode(): ?int;

    public function getDocResponseStatusName(): ?string;

    public function getDocResponsePaginate(): bool;

    /**
     * @return ArrayInsensitive<string, string>
     */
    public function getDocResponseHeaders(): ArrayInsensitive;

    public function getName(): string;

    public function getTag(): string;

    public function getGroup(): ?string;

    public function getDescription(): ?string;

    /**
     * @return Reflection\Type<\Illuminate\Http\Response, null>[]|Reflection\Type<\Illuminate\Http\Response, null>
     */
    public function getResponseClass(): Reflection\Type|array;

    /**
     * @return Reflection\Type<\Illuminate\Http\Request, null>
     */
    public function getRequestClass(): Reflection\Type;

    /**
     * @return string[]
     */
    public function getMiddlewares(): array;

    public function request(): RequestEntry;

    /**
     * @return ResponseEntry[]
     */
    public function response(): array;
}
