<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Responses;

use Ark4ne\OpenApi\Attributes\Response as ResponseAttribute;
use Ark4ne\OpenApi\Tests\Fixtures\Responses\Exceptions\ChildSeatException;
use Ark4ne\OpenApi\Tests\Fixtures\Responses\Exceptions\PaymentRequiredException;
use Ark4ne\OpenApi\Tests\Fixtures\Responses\Exceptions\ResourcefulException;
use Ark4ne\OpenApi\Tests\Fixtures\Responses\Exceptions\TeapotException;
use Ark4ne\OpenApi\Tests\Fixtures\Responses\Exceptions\UnknownException;
use Ark4ne\OpenApi\Tests\Fixtures\Responses\Http\Resources\ArticleCollection;
use Ark4ne\OpenApi\Tests\Fixtures\Responses\Http\Resources\ArticleResource;
use Ark4ne\OpenApi\Tests\Fixtures\Responses\Http\Resources\ArticleWithBrokenAuthorResource;
use Ark4ne\OpenApi\Tests\Fixtures\Responses\Http\Resources\AuthorResource;
use Ark4ne\OpenApi\Tests\Fixtures\Responses\Http\Resources\CatModelResource;
use Ark4ne\OpenApi\Tests\Fixtures\Responses\Http\Resources\CatResource;
use Ark4ne\OpenApi\Tests\Fixtures\Responses\Http\Resources\DogModelResource;
use Ark4ne\OpenApi\Tests\Fixtures\Responses\Http\Resources\DogResource;
use Ark4ne\OpenApi\Tests\Fixtures\Responses\Http\Resources\PreWrappedResource;
use Ark4ne\OpenApi\Tests\Fixtures\Responses\Http\Resources\UnwrappedResource;
use Ark4ne\OpenApi\Tests\Fixtures\Responses\Http\Resources\WrapperResource;
use Ark4ne\OpenApi\Tests\Fixtures\Sampling\Models\Article;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Validation\ValidationException;
use LogicException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedJsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * One action per kind of response; the bodies are never run.
 */
final class ResponseController
{
    public function show(): ArticleResource
    {
        throw new LogicException();
    }

    public function store(): ArticleResource
    {
        throw new LogicException();
    }

    public function index(): ArticleCollection
    {
        throw new LogicException();
    }

    /**
     * @return AnonymousResourceCollection<ArticleResource>
     */
    public function anonymous(): AnonymousResourceCollection
    {
        throw new LogicException();
    }

    #[ResponseAttribute(200, resource: ArticleResource::class, paginator: LengthAwarePaginator::class)]
    public function paginated(): AnonymousResourceCollection
    {
        throw new LogicException();
    }

    #[ResponseAttribute(200, resource: ArticleResource::class, paginator: Paginator::class)]
    public function simple(): AnonymousResourceCollection
    {
        throw new LogicException();
    }

    #[ResponseAttribute(200, resource: ArticleResource::class, paginator: CursorPaginator::class)]
    public function cursor(): AnonymousResourceCollection
    {
        throw new LogicException();
    }

    /**
     * @return LengthAwarePaginator<int, Article>
     */
    public function raw(): LengthAwarePaginator
    {
        throw new LogicException();
    }

    public function unwrapped(): UnwrappedResource
    {
        throw new LogicException();
    }

    public function preWrapped(): PreWrappedResource
    {
        throw new LogicException();
    }

    public function animal(): CatModelResource|DogModelResource
    {
        throw new LogicException();
    }

    public function brokenAuthor(): ArticleWithBrokenAuthorResource
    {
        throw new LogicException();
    }

    public function wrapper(): WrapperResource
    {
        throw new LogicException();
    }

    public function plainPaginator(): LengthAwarePaginator
    {
        throw new LogicException();
    }

    public function contractPaginator(): LengthAwarePaginatorContract
    {
        throw new LogicException();
    }

    /**
     * @throws UnprocessableEntityHttpException|ResourcefulException
     */
    public function unprocessable(): Response
    {
        throw new LogicException();
    }

    public function model(): Article
    {
        throw new LogicException();
    }

    /**
     * @return Collection<int, Article>
     */
    public function models(): Collection
    {
        throw new LogicException();
    }

    /**
     * @return array{id: int, name: string}
     */
    public function shape(): array
    {
        throw new LogicException();
    }

    public function text(): string
    {
        throw new LogicException();
    }

    public function json(): JsonResponse
    {
        throw new LogicException();
    }

    #[ResponseAttribute(200, type: 'array{ok: bool}')]
    public function declaredJson(): JsonResponse
    {
        throw new LogicException();
    }

    #[ResponseAttribute(201, type: 'array{id: int}')]
    public function createdJson(): JsonResponse
    {
        throw new LogicException();
    }

    public function destroy(): Response
    {
        throw new LogicException();
    }

    public function nothing(): void {}

    public function redirect(): RedirectResponse
    {
        throw new LogicException();
    }

    public function download(): BinaryFileResponse
    {
        throw new LogicException();
    }

    public function stream(): StreamedResponse
    {
        throw new LogicException();
    }

    public function streamJson(): StreamedJsonResponse
    {
        throw new LogicException();
    }

    #[ResponseAttribute(200, mediaType: 'text/event-stream', type: 'array{event: string, data: string}')]
    public function events(): StreamedResponse
    {
        throw new LogicException();
    }

    public function responsable(): ArticleResponsable
    {
        return new ArticleResponsable();
    }

    public function view(): View
    {
        throw new LogicException();
    }

    public function pet(): CatResource|DogResource
    {
        throw new LogicException();
    }

    #[ResponseAttribute(201, description: 'Author created', resource: AuthorResource::class, headers: ['X-Id' => 'int'], summary: 'Done', example: ['id' => 1])]
    #[ResponseAttribute(200, description: 'The article')]
    public function declared(): ArticleResource
    {
        throw new LogicException();
    }

    /**
     * @throws ModelNotFoundException|AuthorizationException|ValidationException|ConflictHttpException
     * @throws ChildSeatException|PaymentRequiredException|TeapotException|UnknownException
     */
    public function throwing(): Response
    {
        throw new LogicException();
    }

}
