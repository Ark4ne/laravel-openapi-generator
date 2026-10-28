<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Rules\Mappers;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\FieldDescription;
use Ark4ne\OpenApi\Extraction\Rules\RuleInvocation;
use Ark4ne\OpenApi\Extraction\Rules\RuleMapper;
use Ark4ne\OpenApi\Extraction\Rules\SchemaTypes;

/**
 * `date_format`: the OpenAPI format of `date_formats` (`date`, `date-time`), else a sentence.
 */
final readonly class DateFormatRuleMapper implements RuleMapper
{
    public function handles(): array
    {
        return ['date_format'];
    }

    public function map(RuleInvocation $rule, FieldDescription $field, GenerationContext $context): FieldDescription
    {
        $schema = SchemaTypes::ensure($field->schema, 'string');
        $formats = $context->configuration->dateFormats === [] ? self::FORMATS : $context->configuration->dateFormats;

        foreach ($rule->parameters as $format) {
            if (isset($formats[$format])) {
                return $field->schema($schema->format($formats[$format]));
            }
        }

        return $field->schema($schema)->addConditions('format ' . SchemaTypes::list($rule->parameters));
    }

    /**
     * Defaults of `date_formats` when the configuration gives none.
     */
    public const FORMATS = ['Y-m-d' => 'date', 'Y-m-d H:i:s' => 'date-time', 'Y-m-d\\TH:i:sP' => 'date-time'];
}
