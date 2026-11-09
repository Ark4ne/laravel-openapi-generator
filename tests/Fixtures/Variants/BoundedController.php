<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Variants;

use Ark4ne\OpenApi\Attributes\Since;
use Ark4ne\OpenApi\Attributes\Until;

/**
 * Bounds on the controller and on its actions: each bound of an action wins over the controller's, the other stays.
 */
#[Until(ApiRevision::Labels)]
final class BoundedController
{
    public function index(): void {}

    #[Since(ApiRevision::Labels)]
    public function store(): void {}

    #[Until(ApiRevision::Initial)]
    public function destroy(): void {}

    #[Since('2026-09-01')]
    public function show(): void {}
}
