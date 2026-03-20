<?php

namespace Ark4ne\OpenApi\Parsers\Responses\Concerns;

use Ark4ne\OpenApi\Contracts\Entry;
use Ark4ne\OpenApi\Support\MimeType;
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
        $type = $this->getContentType($entry);

        if (MimeType::isValidRfc6838MimeType($type)) {
            return $type;
        }

        return $this->defaultContentType();
    }
}
