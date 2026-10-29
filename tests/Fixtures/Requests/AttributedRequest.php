<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Requests;

use Ark4ne\OpenApi\Attributes\Header;
use Ark4ne\OpenApi\Attributes\QueryParameter;
use Illuminate\Foundation\Http\FormRequest;

#[QueryParameter('locale', type: "'en'|'fr'", description: 'Language of the answer')]
#[Header('X-Api-Version', type: 'int', required: true, example: 2)]
final class AttributedRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
