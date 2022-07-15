<?php

namespace Ark4ne\OpenApi\Descriptors\Requests;

use Illuminate\Support\Fluent;

/**
 * @property $name
 * @property $description
 * @method self name(string $name)
 * @method self description(string $description)
 */
abstract class Describable extends Fluent
{
}
