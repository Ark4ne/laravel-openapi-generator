<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Variants;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A form request whose rules follow the variant header (`label` from 2026-12-01, `name` before and in the head) and
 * what the integrations prepared.
 */
final class RevisionRequest extends FormRequest
{
    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        $prepared = $this->attributes->get('prepared');

        return [
            ($this->header('Api-Version') === '2026-12-01' ? 'label' : 'name') => 'required|string',
            ...(is_string($prepared) ? ['prepared_' . strtok($prepared, ':') => 'string'] : []),
        ];
    }
}
