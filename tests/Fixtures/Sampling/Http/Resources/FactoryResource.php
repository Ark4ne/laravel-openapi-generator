<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Sampling\Http\Resources;

use Ark4ne\OpenApi\Attributes\ResourceFactory;
use Ark4ne\OpenApi\Tests\Fixtures\Sampling\Factories\ArticleFactory;
use Illuminate\Http\Resources\Json\JsonResource;

#[ResourceFactory(ArticleFactory::class, ["title" => "From the model factory"])]
final class FactoryResource extends JsonResource {}
