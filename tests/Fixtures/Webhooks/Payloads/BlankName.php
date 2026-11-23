<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Webhooks\Payloads;

use Ark4ne\OpenApi\Attributes\Webhook;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A blank name: left out like a missing one.
 */
#[Webhook(' ')]
final class BlankName extends JsonResource {}
