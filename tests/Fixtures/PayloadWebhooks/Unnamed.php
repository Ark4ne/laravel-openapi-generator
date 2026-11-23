<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\PayloadWebhooks;

use Ark4ne\OpenApi\Attributes\Webhook;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A payload class without name: left out with a warning.
 */
#[Webhook]
final class Unnamed extends JsonResource {}
