<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Metadata\Controllers\Crud;

use Ark4ne\OpenApi\Attributes\Operation;
use Ark4ne\OpenApi\Attributes\Tag;

#[Tag('admin')]
#[Operation(description: 'Child description')]
final class UserCrudController extends BaseCrudController
{
    /**
     * {@inheritDoc}
     */
    #[Operation(summary: 'Child show')]
    #[Tag('users')]
    public function show(): void {}

    public function store(): void {}
}
