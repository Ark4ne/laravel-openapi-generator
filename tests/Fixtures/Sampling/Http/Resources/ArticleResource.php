<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Sampling\Http\Resources;

use Ark4ne\OpenApi\Attributes\ResourceFactory;
use Ark4ne\OpenApi\Attributes\SchemaName;
use Ark4ne\OpenApi\Tests\Fixtures\Sampling\Factories\ArticleResourceFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \Ark4ne\OpenApi\Tests\Fixtures\Sampling\Models\Article $resource
 */
#[ResourceFactory(ArticleResourceFactory::class, ["title" => "From the factory"], method: "make")]
#[SchemaName("Post")]
final class ArticleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ["id" => $this->resource->id, "title" => $this->resource->title];
    }
}
