<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Responses;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\ResponseDescription;
use Ark4ne\OpenApi\Extraction\OperationContext;
use Ark4ne\OpenApi\Types\NamedType;
use Ark4ne\OpenApi\Types\Type;
use Ark4ne\OpenApiWriter\Objects\Schema;
use Symfony\Component\HttpFoundation\StreamedJsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A stream: binary (`stream()`), or JSON (`streamJson()`). A stream of server-sent events (`eventStream()`) is
 * declared by `#[Response(mediaType: 'text/event-stream')]`: its type does not tell it from any stream.
 */
final readonly class StreamedResponseExtractor implements ResponseExtractor
{
    public const PRIORITY = 230;

    public function priority(): int
    {
        return self::PRIORITY;
    }

    public function supports(Type $type, OperationContext $operation): bool
    {
        return $type instanceof NamedType && is_a($type->name, StreamedResponse::class, true);
    }

    public function extract(Type $type, OperationContext $operation, GenerationContext $context): array
    {
        $json = $type instanceof NamedType && is_a($type->name, StreamedJsonResponse::class, true);

        return [ResponseDescription::ok()->description('OK')->content($json ? 'application/json' : 'application/octet-stream', $json ? Schema::create() : Schema::string()->format('binary'))];
    }
}
