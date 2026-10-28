<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Types;

use Ark4ne\OpenApi\Tests\Fixtures\Routing\Models\Post;

/**
 * @extends CovariantResource<int, Post>
 */
final class ConcreteCovariantResource extends CovariantResource {}
