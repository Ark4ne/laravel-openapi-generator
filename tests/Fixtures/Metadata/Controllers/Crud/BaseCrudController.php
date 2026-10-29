<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Metadata\Controllers\Crud;

use Ark4ne\OpenApi\Attributes\Operation;
use Ark4ne\OpenApi\Attributes\Tag;
use Illuminate\Database\Eloquent\ModelNotFoundException;

#[Tag('crud')]
#[Operation(description: 'Base description', externalDocs: 'https://example.com/base')]
abstract class BaseCrudController
{
    /**
     * List the records.
     */
    #[Operation(summary: 'Base summary', operationId: 'crud.index')]
    #[Tag('listing')]
    public function index(): void {}

    /**
     * Show a record.
     *
     * Read from the parent.
     *
     * @throws ModelNotFoundException
     */
    #[Operation(summary: 'Base show', operationId: 'crud.show')]
    #[Tag('reading')]
    public function show(): void {}
}
