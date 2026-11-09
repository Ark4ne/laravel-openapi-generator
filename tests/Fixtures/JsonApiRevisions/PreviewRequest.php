<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\JsonApiRevisions;

use Ark4ne\JsonApi\Http\Rules\Revised;
use Ark4ne\OpenApi\Extraction\Requests\DescribesRequest;
use Ark4ne\OpenApi\Extraction\Requests\RequestDescriber;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A request described by hand whose rules follow the revisions: the `draft` query parameter from Labels, `legacy` up
 * to Initial.
 */
final class PreviewRequest extends FormRequest implements DescribesRequest
{
    public function describe(RequestDescriber $request): RequestDescriber
    {
        return $request
            ->query([
                'draft' => Revised::rules('boolean')->since(ApiRevision::Labels),
                'legacy' => Revised::rules('string')->until(ApiRevision::Initial),
            ]);
    }
}
