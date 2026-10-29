<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Rules\Mappers;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\FieldDescription;
use Ark4ne\OpenApi\Extraction\Rules\RuleInvocation;
use Ark4ne\OpenApi\Extraction\Rules\RuleMapper;
use Ark4ne\OpenApi\Support\MimeTypes;

/**
 * `file`, `image` (`image:allow_svg`): an uploaded file; `image` gives its content types.
 */
final readonly class FileRuleMapper implements RuleMapper
{
    public function handles(): array
    {
        return ['file', 'image'];
    }

    public function map(RuleInvocation $rule, FieldDescription $field, GenerationContext $context): FieldDescription
    {
        $field = $field->file()->schema($field->schema->type('string')->format('binary'));

        if ($rule->name === 'file') {
            return $field;
        }

        $extensions = in_array('allow_svg', $rule->parameters, true) ? self::SVG : self::IMAGES;

        return $field->addContentTypes(...MimeTypes::of($extensions));
    }

    /**
     * Extensions accepted by the `image` rule (Laravel 12+), without and with `allow_svg`.
     */
    public const IMAGES = ['jpg', 'jpeg', 'png', 'bmp', 'gif', 'webp'];

    public const SVG = ['jpg', 'jpeg', 'png', 'bmp', 'gif', 'svg', 'webp'];
}
