<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\JsonApiV3;

use Ark4ne\JsonApi\Http\Rules\Fields;
use Ark4ne\JsonApi\Http\Rules\Includes;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The query of the articles: `include` and `fields` validated at the revision of the request, `include_head` at the
 * head (a rule built without revision).
 */
final class ArticleIndexRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'include' => new Includes(ArticleResource::class, revision: ApiRevision::current()),
            'include_head' => new Includes(ArticleResource::class),
            'fields' => new Fields(ArticleResource::class, revision: ApiRevision::current()),
        ];
    }
}
