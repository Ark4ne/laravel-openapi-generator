<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Generation;

use Ark4ne\OpenApi\Extraction\Operations\OperationExtractor;
use Ark4ne\OpenApi\Extraction\Requests\RequestExtractor;
use Ark4ne\OpenApi\Extraction\Responses\ResponseExtractor;
use Ark4ne\OpenApi\Extraction\Rules\RuleMapper;
use Ark4ne\OpenApi\Sampling\Sampler;

/**
 * What the application adds to the registries of the generator (`extensions` of the configuration, container tags):
 * the extractors and samplers join theirs by priority, the rule mappers replace the built-in ones for their rules,
 * the passes run after the built-in ones, before the cleanup.
 */
final readonly class GeneratorExtensions
{
    /**
     * @param list<OperationExtractor> $operationExtractors
     * @param list<RequestExtractor> $requestExtractors
     * @param list<RuleMapper> $ruleMappers
     * @param list<ResponseExtractor> $responseExtractors
     * @param list<Sampler> $samplers
     * @param list<Pass> $passes in order
     */
    public function __construct(
        public array $operationExtractors = [],
        public array $requestExtractors = [],
        public array $ruleMappers = [],
        public array $responseExtractors = [],
        public array $samplers = [],
        public array $passes = [],
    ) {}
}
