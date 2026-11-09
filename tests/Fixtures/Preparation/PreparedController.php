<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Preparation;

final class PreparedController
{
    public function show(): PreparedResource
    {
        return new PreparedResource(null);
    }

    public function store(PreparedRequest $request): PreparedResource
    {
        return new PreparedResource($request->validated());
    }
}
