<?php

namespace Ark4ne\OpenApi\Parsers\Responses\Concerns;

use Ark4ne\OpenApi\Contracts\Entry;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Header;
use GoldSpecDigital\ObjectOrientedOAS\Objects\MediaType;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Schema;

trait Response
{
    protected function defaultContentType(): string
    {
        return MediaType::MEDIA_TYPE_TEXT_PLAIN;
    }

    protected function defaultMediaType(): string
    {
        return MediaType::MEDIA_TYPE_TEXT_PLAIN;
    }

    public function getHeaders(Entry $entry): iterable
    {
        return $entry->getDocResponseHeaders();
    }

    /**
     * @param iterable<string, mixed> $headers
     *
     * @return Header[]
     */
    public function convertHeadersToOasHeaders(iterable $headers): array
    {
        foreach ($headers as $name => $value) {
            $converted[] = Header::create($name)->schema(Schema::string())->example($value);
        }

        return $converted ?? [];
    }

    public function getContentType(Entry $entry): string
    {
        return $this->getHeaders($entry)['Content-Type'] ?? $this->defaultContentType();
    }

    public function getMediaType(Entry $entry): string
    {
        return match ($type = $this->getContentType($entry)) {
            MediaType::MEDIA_TYPE_APPLICATION_JSON,
            MediaType::MEDIA_TYPE_APPLICATION_PDF,
            MediaType::MEDIA_TYPE_IMAGE_JPEG,
            MediaType::MEDIA_TYPE_IMAGE_PNG,
            MediaType::MEDIA_TYPE_TEXT_CALENDAR,
            MediaType::MEDIA_TYPE_TEXT_PLAIN,
            MediaType::MEDIA_TYPE_TEXT_XML,
            MediaType::MEDIA_TYPE_APPLICATION_X_WWW_FORM_URLENCODED => $type,
            default => $this->defaultMediaType()
        };
    }
}
