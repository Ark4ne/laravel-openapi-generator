<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Feature\Responses;

use Ark4ne\OpenApi\Configuration\SamplingConfiguration;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\ResponseDescription;
use Ark4ne\OpenApi\Extraction\OperationContextFactory;
use Ark4ne\OpenApi\Extraction\Responses\OperationResponses;
use Ark4ne\OpenApi\Sampling\Samplers;
use Ark4ne\OpenApi\Tests\Feature\DocumentScenario;
use Ark4ne\OpenApi\Tests\Fixtures\Responses\ResponseController;
use Ark4ne\OpenApi\Types\TypeResolver;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * The responses of a whole application, inside one sampling session: the statuses of the v0.6 application
 * (`openapi-jsonresource.json`), and no row left in the database.
 */
#[CoversNothing]
final class ResponsesTest extends DocumentScenario
{
    private ?GenerationContext $sampled = null;

    protected function defineRoutes($router): void
    {
        $router->get('user', [ResponseController::class, 'index']);
        $router->post('user', [ResponseController::class, 'store']);
        $router->get('user/{user}', [ResponseController::class, 'show']);
        $router->put('user/{id}', [ResponseController::class, 'show']);
        $router->delete('user/{user}', [ResponseController::class, 'destroy']);
        $router->get('post', [ResponseController::class, 'paginated']);
    }

    public function test_the_statuses_of_the_v0_6_operations(): void
    {
        $statuses = [];

        foreach ($this->describe() as $label => $responses) {
            $statuses[$label] = array_map(static fn(ResponseDescription $response): int|string => $response->status, $responses);
        }

        // v0.6 answered 200 to POST; Laravel answers 201 for a resource of a model just created.
        self::assertSame([
            'GET /user' => [200],
            'POST /user' => [201],
            'GET /user/{user}' => [200],
            'PUT /user/{id}' => [200],
            'DELETE /user/{user}' => [204],
            'GET /post' => [200],
        ], $statuses);
    }

    public function test_the_resources_are_shared_components_and_nothing_is_kept(): void
    {
        $this->describe();

        self::assertSame(0, DB::table('articles')->count());
        self::assertSame(['Article', 'Author'], array_keys($this->sampled?->components->all() ?? []));
    }

    public function test_the_documents_are_those_expected(): void
    {
        $this->assertExpectedDocuments(__DIR__);
    }

    /**
     * @return array<string, list<ResponseDescription>>
     */
    private function describe(): array
    {
        $context = $this->sampled ??= $this->context(sampling: new SamplingConfiguration(examples: false));
        $types = new TypeResolver();
        $session = $this->samplingSession();
        $responses = OperationResponses::default(Samplers::default($session, $types), $types);
        $factory = new OperationContextFactory($types);
        $definitions = $this->definitions($context);

        return $session->run(static function () use ($definitions, $factory, $responses, $context): array {
            $described = [];

            foreach ($definitions as $definition) {
                $described[$definition->label()] = $responses->describe($factory->create($definition, $context), $context);
            }

            return $described;
        }, $context);
    }
}
