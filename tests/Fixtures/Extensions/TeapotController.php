<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Extensions;

use LogicException;

/**
 * The application the extensions document.
 */
final class TeapotController
{
    /**
     * Brew some tea.
     */
    public function brew(BrewRequest $request): Teapot
    {
        throw new LogicException();
    }
}
