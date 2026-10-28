<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Integrations;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Extraction\Responses\Paginations;
use Ark4ne\OpenApi\Extraction\Responses\ResourceFormat;
use Ark4ne\OpenApi\Extraction\Responses\ResourceSchemas;
use Ark4ne\OpenApiWriter\Objects\Schema;
use RuntimeException;

/**
 * A format of its own media type: `formatted` components, `document` bodies, `list` or `paginator` collections.
 */
final readonly class TestResourceFormat implements ResourceFormat
{
    /**
     * @param list<class-string> $resources those it supports, every resource when empty
     */
    public function __construct(
        private string $mediaType = 'application/x-test',
        private array $resources = [],
        private bool $failing = false,
    ) {}

    public function supports(string $resource): bool
    {
        return $this->resources === [] || in_array($resource, $this->resources, true);
    }

    public function mediaType(): string
    {
        return $this->mediaType;
    }

    public function component(string $resource, ResourceSchemas $resources, GenerationContext $context): Schema
    {
        return $this->failing ? throw new RuntimeException('broken') : Paginations::object(['formatted' => Schema::boolean()]);
    }

    public function document(string $resource, Schema $data, ResourceSchemas $resources, GenerationContext $context): Schema
    {
        return Paginations::object(['document' => $data]);
    }

    public function collection(string $resource, Schema $data, ?string $paginator, ResourceSchemas $resources, GenerationContext $context): Schema
    {
        return $paginator === null ? Paginations::object(['list' => Schema::array()->items($data)]) : Paginations::object(['paginator' => Schema::string()->const($paginator)]);
    }
}
