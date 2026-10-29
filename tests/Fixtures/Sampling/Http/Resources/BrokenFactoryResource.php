<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Sampling\Http\Resources;

use Ark4ne\OpenApi\Attributes\ResourceFactory;
use Ark4ne\OpenApi\Tests\Fixtures\Sampling\Factories\ArticleResourceFactory;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Its factory method does not exist: the next sampler is used.
 */
#[ResourceFactory(ArticleResourceFactory::class, method: 'missing')]
final class BrokenFactoryResource extends JsonResource {}
