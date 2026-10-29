<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Requests;

use Ark4ne\OpenApi\Tests\Fixtures\Routing\Models\Post;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LogicException;

/**
 * Rules that read the bound route parameter and get a dependency by method injection.
 */
final class UpdatePostRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(Repository $config): array
    {
        $post = $this->route('post');

        if (! $post instanceof Post) {
            throw new LogicException('The post is not bound');
        }

        return [
            'slug' => ['string', Rule::unique('posts', 'slug')->ignore($post)],
            'title' => 'string|max:' . (is_int($config->get('posts.title')) ? $config->get('posts.title') : 100),
        ];
    }
}
