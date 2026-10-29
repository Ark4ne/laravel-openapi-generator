<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Operations;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\OperationDescription;
use Ark4ne\OpenApi\Extraction\OperationContext;

/**
 * Description (the PHPDoc text after its summary) and `@deprecated` of the action. The summary is given by the
 * `doc` summary strategy.
 */
final readonly class PhpDocOperationExtractor implements OperationExtractor
{
    public const PRIORITY = 100;

    public function priority(): int
    {
        return self::PRIORITY;
    }

    public function supports(OperationContext $operation): bool
    {
        return $operation->doc !== null;
    }

    public function extract(OperationContext $operation, OperationDescription $description, GenerationContext $context): OperationDescription
    {
        $doc = $operation->doc;

        if ($doc === null) {
            return $description;
        }

        return $description
            ->description($doc->description() ?? $description->description)
            ->deprecated($description->deprecated || $doc->isDeprecated());
    }
}
