<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Factories;

use Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Models\Post;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Models\User;
use Ark4ne\OpenApi\Tests\Fixtures\Routing\Enums\Status;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
final class PostFactory extends Factory
{
    protected $model = Post::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => 'Notes on the engine',
            'content' => 'The engine weaves algebraic patterns.',
            'status' => Status::Published,
        ];
    }
}
