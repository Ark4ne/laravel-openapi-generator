<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Variants;

final class RevisionController
{
    public function show(): RevisionResource
    {
        return new RevisionResource(null);
    }

    public function store(RevisionRequest $request): RevisionResource
    {
        return new RevisionResource($request->validated());
    }
}
