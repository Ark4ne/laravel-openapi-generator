<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Support;

use Symfony\Component\Mime\MimeTypes as SymfonyMimeTypes;

/**
 * Content types of file extensions, as Laravel's `mimes` rule matches them (`symfony/mime`).
 */
final class MimeTypes
{
    /**
     * The main content type of each extension, without duplicates: `['jpg', 'jpeg', 'png']` → `image/jpeg, image/png`.
     *
     * @param list<string> $extensions
     *
     * @return list<string>
     */
    public static function of(array $extensions): array
    {
        $types = [];

        foreach ($extensions as $extension) {
            $type = SymfonyMimeTypes::getDefault()->getMimeTypes(strtolower($extension))[0] ?? null;

            if ($type !== null) {
                $types[] = $type;
            }
        }

        return array_values(array_unique($types));
    }
}
