<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Sampling\Models;

use Ark4ne\OpenApi\Tests\Fixtures\Routing\Enums\Status;
use Ark4ne\OpenApi\Tests\Fixtures\Sampling\Factories\ArticleFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $title
 * @property string|null $body
 */
#[UseFactory(ArticleFactory::class)]
final class Article extends Model
{
    /** @use HasFactory<ArticleFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'status' => Status::class,
            'rating' => 'decimal:2',
            'meta' => 'array',
        ];
    }
}
