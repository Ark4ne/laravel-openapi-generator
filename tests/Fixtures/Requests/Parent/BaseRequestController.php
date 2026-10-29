<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Requests\Parent;

use Ark4ne\OpenApi\Attributes\QueryParameter;
use Ark4ne\OpenApi\Tests\Fixtures\Routing\Enums\Status;
use Illuminate\Http\Request;

/**
 * A parent controller whose attribute names an enum imported here only.
 */
abstract class BaseRequestController
{
    #[QueryParameter('status', type: 'Status')]
    public function filtered(Request $request): void {}

    /**
     * Keeps the import used by the attribute.
     */
    public function status(): Status
    {
        return Status::Draft;
    }
}
