<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Sampling\Factories;

use Ark4ne\OpenApi\Tests\Fixtures\Routing\Enums\Status;
use Ark4ne\OpenApi\Tests\Fixtures\Sampling\Models\Article;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Article>
 */
final class ArticleFactory extends Factory
{
    protected $model = Article::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(),
            'body' => null,
            'status' => Status::Published,
            'rating' => 4.5,
            'meta' => ['views' => 3],
            'published_at' => '2024-01-02 03:04:05',
        ];
    }
}
