<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\JsonApiRevisions;

use Ark4ne\JsonApi\Http\Rules\Revised;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A plain form request whose rules follow the revisions (`Revised`): `name` up to Initial, then `label`; `summary`
 * of at most 100 characters up to Initial, 500 after.
 */
final class ArticleRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => Revised::rules('required|string')->until(ApiRevision::Initial),
            'label' => Revised::rules('required|string')->since(ApiRevision::Labels),
            'summary' => Revised::rules('nullable|string|max:500')->was(ApiRevision::Initial, rules: 'nullable|string|max:100'),
        ];
    }
}
