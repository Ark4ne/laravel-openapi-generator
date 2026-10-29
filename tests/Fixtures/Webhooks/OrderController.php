<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Webhooks;

use Ark4ne\OpenApi\Attributes\Callback;
use Ark4ne\OpenApi\Tests\Fixtures\Responses\Http\Resources\ArticleResource;

final class OrderController
{
    #[Callback('orderCreated', url: '{$request.body#/callbackUrl}', webhook: 'order.created')]
    #[Callback('orderShipped', url: '{$request.body#/callbackUrl}', type: 'array{id: int, carrier: string}', description: 'The order was shipped')]
    #[Callback('orderShipped', url: '{$request.body#/callbackUrl}', method: 'PUT', type: 'array{id: int}', status: 204)]
    #[Callback('article', url: '{$request.query.notify}', resource: ArticleResource::class)]
    public function store(): void {}

    #[Callback('missing', url: '{$request.body#/callbackUrl}', webhook: 'order.unknown')]
    #[Callback('invalid', url: '{$request.body#/callbackUrl}', webhook: 'order.created', type: 'int')]
    public function update(): void {}
}
