<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\PayloadWebhooks;

use Ark4ne\OpenApi\Attributes\Webhook;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Abstract: never a webhook.
 */
#[Webhook('base')]
abstract class BasePayload extends JsonResource {}
