<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Sampling\Http\Resources;

use Ark4ne\OpenApi\Attributes\ResourceFactory;
use Ark4ne\OpenApi\Tests\Fixtures\Sampling\Factories\ArticleResourceFactory;
use Illuminate\Http\Resources\Json\JsonResource;

#[ResourceFactory(ArticleResourceFactory::class)]
final class InvokedResource extends JsonResource {}
