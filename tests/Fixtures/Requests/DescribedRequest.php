<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Requests;

use Ark4ne\OpenApi\Extraction\Requests\DescribesRequest;
use Ark4ne\OpenApi\Extraction\Requests\RequestDescriber;
use Ark4ne\OpenApiWriter\Objects\Schema;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Rules completed by a manual description.
 */
final class DescribedRequest extends FormRequest implements DescribesRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['name' => 'required|string'];
    }

    public function describe(RequestDescriber $request): RequestDescriber
    {
        return $request
            ->header('X-Tenant', 'required|uuid', 'Tenant of the request')
            ->cookie('locale', Schema::string()->enum('en', 'fr'))
            ->query(['include' => 'string|in:posts,comments'])
            ->json(['extra' => 'string'])
            ->body(['name' => 'required|string', 'payload' => 'array'], 'application/xml')
            ->bodySchema(Schema::object()->properties(Schema::string('raw')), 'text/plain')
            ->bodyDescription('The user to store');
    }
}
