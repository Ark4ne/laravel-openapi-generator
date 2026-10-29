<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Rules\Mappers;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\FieldDescription;
use Ark4ne\OpenApi\Extraction\Rules\RuleInvocation;
use Ark4ne\OpenApi\Extraction\Rules\RuleMapper;
use Ark4ne\OpenApi\Extraction\Rules\SchemaTypes;
use Ark4ne\OpenApi\Support\MimeTypes;

/**
 * `mimes`, `mimetypes`: a file with these content types; `extensions`: a file, with a sentence.
 */
final readonly class MimesRuleMapper implements RuleMapper
{
    public function handles(): array
    {
        return ['mimes', 'mimetypes', 'extensions'];
    }

    public function map(RuleInvocation $rule, FieldDescription $field, GenerationContext $context): FieldDescription
    {
        $field = $field->file()->schema($field->schema->type('string')->format('binary'));

        return match ($rule->name) {
            'mimes' => $field->addContentTypes(...MimeTypes::of($rule->parameters)),
            'mimetypes' => $field->addContentTypes(...$rule->parameters),
            default => $field->addConditions('extension ' . SchemaTypes::list($rule->parameters)),
        };
    }
}
