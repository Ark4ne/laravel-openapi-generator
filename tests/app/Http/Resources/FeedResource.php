<?php

namespace Test\app\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @extends JsonResource<\Test\app\Models\Post|\Test\app\Models\User|array{id: string, name: string}>
 */
class FeedResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'content' => $this->resource->content,
        ];
    }
}
