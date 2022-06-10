<?php

namespace Ark4ne\OpenApi\Documentation\Request;

use GoldSpecDigital\ObjectOrientedOAS\Objects\MediaType;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Schema;

class Content
{
    public static function convert(Schema $schema, null|string|array $format): MediaType
    {
        $mediaType = MediaType::MEDIA_TYPE_APPLICATION_JSON;
        $mediaTypes = [
            MediaType::MEDIA_TYPE_APPLICATION_JSON,
            MediaType::MEDIA_TYPE_TEXT_XML,
            MediaType::MEDIA_TYPE_APPLICATION_X_WWW_FORM_URLENCODED
        ];

        foreach ($mediaTypes as $acceptable) {
            if (in_array($acceptable, (array)$format, true)) {
                $mediaType = $acceptable;
                break;
            }
        }

        return (new MediaType)
            ->mediaType($mediaType)
            ->schema($schema);
    }
}
