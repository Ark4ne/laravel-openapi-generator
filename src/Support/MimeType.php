<?php

namespace Ark4ne\OpenApi\Support;

use GoldSpecDigital\ObjectOrientedOAS\Objects\MediaType;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Schema;
use Symfony\Component\Mime\MimeTypes;

class MimeType
{
    public static function isValidRfc6838MimeType(string $mimeType, bool $allowParameters = true): bool {
        if ($allowParameters && str_contains($mimeType, ';')) {
            $parts = explode(';', $mimeType, 2);
            $mimeType = trim($parts[0]);
        }

        $restrictedName = '[a-zA-Z0-9][a-zA-Z0-9!#$&^_.+\-]{0,126}';

        $pattern = '@^' . $restrictedName . '/' . $restrictedName . '$@';

        return (bool) preg_match($pattern, $mimeType);
    }

    public static function convert(Schema $schema, null|string|array $format): MediaType
    {
        $mediaType = MediaType::MEDIA_TYPE_APPLICATION_JSON;

        foreach ((array)$format as $guess) {
            if (self::isValidRfc6838MimeType($guess)) {
                $mediaType = $guess;
                break;
            }
        }

        return (new MediaType)
            ->mediaType($mediaType)
            ->schema($schema);
    }
}
