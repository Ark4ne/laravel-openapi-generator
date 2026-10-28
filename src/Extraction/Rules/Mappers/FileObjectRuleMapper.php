<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Rules\Mappers;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\FieldDescription;
use Ark4ne\OpenApi\Extraction\Rules\RuleInvocation;
use Ark4ne\OpenApi\Extraction\Rules\RuleMapper;
use Ark4ne\OpenApi\Support\MimeTypes;
use Ark4ne\OpenApi\Support\Properties;

/**
 * `File::types()`, `File::image()` and their bounds in kilobytes.
 */
final readonly class FileObjectRuleMapper implements RuleMapper
{
    public function handles(): array
    {
        return ['Illuminate\Validation\Rules\File'];
    }

    public function map(RuleInvocation $rule, FieldDescription $field, GenerationContext $context): FieldDescription
    {
        $file = $rule->rule;
        $field = $field->file()->schema($field->schema->type('string')->format('binary'));

        if ($file === null) {
            return $field;
        }

        // `File::types()` takes content types (`image/png`) and extensions (`png`) in one list.
        $allowed = self::strings($file, 'allowedMimetypes');
        $types = [
            ...array_filter($allowed, static fn(string $type): bool => str_contains($type, '/')),
            ...MimeTypes::of(array_values(array_filter($allowed, static fn(string $type): bool => ! str_contains($type, '/')))),
        ];

        foreach (self::strings($file, 'customRules') as $custom) {
            if (str_starts_with($custom, 'image')) {
                $types = [...$types, ...MimeTypes::of(str_contains($custom, 'allow_svg') ? FileRuleMapper::SVG : FileRuleMapper::IMAGES)];
            }
        }

        $field = $field->addContentTypes(...$types);
        $min = Properties::read($file, 'minimumFileSize');
        $max = Properties::read($file, 'maximumFileSize');
        $field = is_numeric($min) ? $field->addConditions("at least {$min} KB") : $field;

        return is_numeric($max) ? $field->addConditions("at most {$max} KB") : $field;
    }

    /**
     * @return list<string>
     */
    private static function strings(object $rule, string $property): array
    {
        $values = Properties::read($rule, $property);

        return is_array($values) ? array_values(array_filter($values, is_string(...))) : [];
    }
}
