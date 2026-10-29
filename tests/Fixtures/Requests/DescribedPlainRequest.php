<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Requests;

use Ark4ne\OpenApi\Extraction\Requests\DescribesRequest;
use Ark4ne\OpenApi\Extraction\Requests\RequestDescriber;
use Illuminate\Http\Request;

/**
 * A request without validation rules, described by hand.
 */
final class DescribedPlainRequest extends Request implements DescribesRequest
{
    public function describe(RequestDescriber $request): RequestDescriber
    {
        return $request->query(['include' => 'string']);
    }
}
