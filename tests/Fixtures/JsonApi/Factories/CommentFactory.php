<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Factories;

use Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Models\Comment;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Models\Post;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comment>
 */
final class CommentFactory extends Factory
{
    protected $model = Comment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'post_id' => Post::factory(),
            'content' => 'Brilliant.',
        ];
    }
}
