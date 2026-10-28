<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Feature\Metadata;

use Ark4ne\OpenApi\Configuration\StrategyConfiguration;
use Ark4ne\OpenApi\Description\OperationDescription;
use Ark4ne\OpenApi\Extraction\OperationContextFactory;
use Ark4ne\OpenApi\Extraction\Operations\OperationExtractors;
use Ark4ne\OpenApi\Extraction\Strategies\BuiltInStrategies;
use Ark4ne\OpenApi\Tests\TestCase;
use Ark4ne\OpenApi\Types\TypeResolver;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Metadata of the operations of a fixture application, with the default strategies of `config/openapi.php`. The
 * expected documents come with the writing (E8).
 */
#[CoversNothing]
final class MetadataTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        require dirname(__DIR__, 2) . '/Fixtures/Metadata/routes.php';
    }

    public function test_the_metadata_of_each_operation_is_extracted(): void
    {
        $summary = [];

        foreach ($this->describe() as $description) {
            $summary[(string) $description->route] = [
                'summary' => $description->summary,
                'description' => $description->description,
                'tags' => $description->tags,
                'operationId' => $description->operationId,
                'deprecated' => $description->deprecated,
            ];
        }

        self::assertSame([
            'GET /api/users' => ['summary' => 'List the users.', 'description' => 'Sorted by name.', 'tags' => ['User', 'users'], 'operationId' => 'users.index', 'deprecated' => false],
            'GET /api/users/{user}' => ['summary' => 'Show one user', 'description' => 'Default description of the controller', 'tags' => ['User', 'users'], 'operationId' => 'users.find', 'deprecated' => true],
            'POST /api/users' => ['summary' => 'Store a user.', 'description' => 'Default description of the controller', 'tags' => ['accounts', 'users', 'admin'], 'operationId' => 'users.store', 'deprecated' => false],
            'GET /api/users/legacy' => ['summary' => null, 'description' => 'Default description of the controller', 'tags' => ['User', 'users'], 'operationId' => 'getApiUsersLegacy', 'deprecated' => true],
            'POST /api/users/{user}/restore' => ['summary' => 'Restored.', 'description' => 'Default description of the controller', 'tags' => ['User', 'users'], 'operationId' => 'postApiUsersByUserRestore', 'deprecated' => false],
            'GET /api/admin/reports' => ['summary' => null, 'description' => null, 'tags' => ['Admin\Report'], 'operationId' => 'admin.reports.index', 'deprecated' => false],
            'GET /api/ping' => ['summary' => 'Ping the API.', 'description' => null, 'tags' => [], 'operationId' => 'getApiPing', 'deprecated' => false],
        ], $summary);
    }

    /**
     * @return list<OperationDescription>
     */
    private function describe(): array
    {
        $context = $this->context();
        $document = $context->document;
        $factory = new OperationContextFactory(new TypeResolver());
        $tagsPattern = '/^Ark4ne\\\\OpenApi\\\\Tests\\\\Fixtures\\\\Metadata\\\\Controllers\\\\(.+)Controller$/';
        $extractors = OperationExtractors::default(
            BuiltInStrategies::summary($document->summary),
            BuiltInStrategies::tags(new StrategyConfiguration('controller', ['pattern' => $tagsPattern])),
            BuiltInStrategies::operationId($document->operationId),
        );
        $descriptions = [];

        foreach ($this->definitions($context) as $definition) {
            $operation = $factory->create($definition, $context);
            $descriptions[] = $extractors->extract($operation, $operation->describe(), $context);
        }

        self::assertDiagnostics([], $context);

        return $descriptions;
    }
}
