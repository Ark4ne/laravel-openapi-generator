<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Requests;

use Ark4ne\OpenApi\Tests\Fixtures\Requests\Parent\BaseRequestController;
use Illuminate\Http\Request;

/**
 * Redefines the action of its parent, without importing the enum its parent's attribute names.
 */
final class ChildRequestController extends BaseRequestController
{
    public function filtered(Request $request): void {}
}
