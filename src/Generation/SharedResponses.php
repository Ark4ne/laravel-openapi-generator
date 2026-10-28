<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Generation;

use Ark4ne\OpenApi\Description\DocumentDescription;
use Ark4ne\OpenApi\Description\HeaderDescription;
use Ark4ne\OpenApi\Description\OperationDescription;
use Ark4ne\OpenApi\Description\ResponseDescription;
use Ark4ne\OpenApi\Description\ResponseOrigin;
use Ark4ne\OpenApiWriter\Objects\Schema;

/**
 * Adds a response shared in `components.responses` to an operation, unless the operation already has its status.
 */
final class SharedResponses
{
    /**
     * @param list<HeaderDescription> $headers
     *
     * @return array{OperationDescription, DocumentDescription}
     */
    public static function add(OperationDescription $operation, DocumentDescription $document, string $name, int $status, string $description, Schema $body, array $headers = [], string $mediaType = 'application/json'): array
    {
        foreach ($operation->responses as $response) {
            if ($response->status === $status) {
                return [$operation, $document];
            }
        }

        if (! isset($document->responses[$name])) {
            $document = $document->response($name, ResponseDescription::create($status)
                ->description($description)
                ->content($mediaType, $body)
                ->headers(...$headers)
                ->origin(ResponseOrigin::Pass));
        }

        return [$operation->addResponses(ResponseDescription::reference($status, $name)->origin(ResponseOrigin::Pass)), $document];
    }
}
