<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Operations;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\OperationDescription;
use Ark4ne\OpenApi\Extraction\OperationContext;

/**
 * Reads the metadata of an operation (PHPDoc, attributes, naming strategies).
 *
 * Unlike responses, every supporting extractor runs, by increasing priority: each one refines the description of
 * the previous ones, so that attributes (high priority) override the PHPDoc (low priority).
 */
interface OperationExtractor
{
    public function priority(): int;

    public function supports(OperationContext $operation): bool;

    public function extract(OperationContext $operation, OperationDescription $description, GenerationContext $context): OperationDescription;
}
