<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Laravel\Commands;

use Ark4ne\OpenApi\Configuration\Configuration;
use Ark4ne\OpenApi\Context\Diagnostic;
use Ark4ne\OpenApi\Description\DocumentDescription;
use Ark4ne\OpenApi\Description\OperationDescription;
use Ark4ne\OpenApi\Description\ParameterDescription;
use Ark4ne\OpenApi\Description\ResponseDescription;
use Ark4ne\OpenApi\Description\SecurityDescription;
use Ark4ne\OpenApi\Discovery\RouteDefinition;
use Ark4ne\OpenApi\Exceptions\InvalidConfigurationException;
use Ark4ne\OpenApi\Laravel\DiagnosticsOutput;
use Ark4ne\OpenApi\Laravel\GeneratorFactory;
use Ark4ne\OpenApiWriter\Enums\HttpMethod;
use Illuminate\Console\Command;
use Illuminate\Routing\Router;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * What the generator understood of a route: its operation once the passes have run (metadata, parameters, body,
 * responses, security) and every diagnostic about it, whatever the verbosity.
 *
 * The route is its name, its URI (`api/users/{user}`), or its method and URI (`GET api/users`). Exit codes: 0 the route
 * is in the document; 1 it is not; 2 the configuration or the document is invalid.
 */
#[AsCommand(name: 'openapi:inspect')]
final class InspectCommand extends Command
{
    protected $signature = 'openapi:inspect
        {route : Name of the route, its URI, or its method and URI ("GET api/users")}
        {--document= : Document to look in, the first one by default}';

    protected $description = 'Show what the generator understood of a route';

    public function handle(): int
    {
        try {
            $configuration = $this->laravel->make(Configuration::class);
            $name = $this->option('document');
            $document = $configuration->document(is_string($name) ? $name : (string) array_key_first($configuration->documents));
            $generated = $this->laravel->make(GeneratorFactory::class)->make($configuration)->generate($configuration, $document);
        } catch (InvalidConfigurationException $exception) {
            $this->components->error($exception->getMessage());

            return self::INVALID;
        }

        $route = $this->argument('route');
        $route = is_string($route) ? $route : '';
        $targets = $this->targets($route);
        $operations = array_values(array_filter($generated->description->operations, static fn(OperationDescription $operation): bool => self::matches($operation, $targets)));

        if ($operations === []) {
            $this->components->error("{$route} is not in the document {$document->name}");

            return self::FAILURE;
        }

        foreach ($operations as $operation) {
            $label = RouteDefinition::labelOf($operation->method, $operation->path);
            $this->show($operation, $label, $generated->description);
            // The extraction labels a diagnostic with the route as it was (`route`), before a pass renamed its path.
            $labels = [$label, $operation->route ?? $label];
            (new DiagnosticsOutput($this->output))->print(array_filter($generated->context->diagnostics->all(), static fn(Diagnostic $diagnostic): bool => in_array($diagnostic->route, $labels, true)), all: true);
        }

        return self::SUCCESS;
    }

    private function show(OperationDescription $operation, string $label, DocumentDescription $document): void
    {
        $this->newLine();
        $this->components->twoColumnDetail("<fg=green;options=bold>{$label}</>");

        foreach (['Operation id' => $operation->operationId, 'Summary' => $operation->summary, 'Description' => $operation->description, 'Tags' => implode(', ', $operation->tags)] as $field => $value) {
            if ($value !== null && $value !== '') {
                $this->components->twoColumnDetail($field, $value);
            }
        }

        if ($operation->deprecated) {
            $this->components->twoColumnDetail('Deprecated', 'yes');
        }

        if ($operation->security !== null) {
            $this->components->twoColumnDetail('Security', $operation->security === [] ? 'none' : implode(' | ', array_map(static fn(SecurityDescription $security): string => implode(' + ', array_keys($security->schemes)), $operation->security)));
        }

        foreach ([...$document->pathParameters[$operation->path] ?? [], ...$operation->request->parameters] as $parameter) {
            $this->components->twoColumnDetail(self::parameter($parameter), (string) json_encode($parameter->schema->toArray()));
        }

        if ($operation->request->body !== null) {
            $this->components->twoColumnDetail('Body' . ($operation->request->body->required ? ' (required)' : ''), implode(', ', array_keys($operation->request->body->contents)));
        }

        foreach ($operation->responses as $response) {
            $this->components->twoColumnDetail(self::response($response, $document), '');
        }
    }

    private static function parameter(ParameterDescription $parameter): string
    {
        return "{$parameter->name} ({$parameter->in->value}" . ($parameter->required ? ', required)' : ')');
    }

    private static function response(ResponseDescription $response, DocumentDescription $document): string
    {
        $shown = $response->reference === null ? $response : $document->responses[$response->reference] ?? $response;
        $types = array_keys([...$shown->contents, ...$shown->itemContents]);

        return trim("{$response->status} {$shown->description}") . ($types === [] ? '' : ': ' . implode(', ', $types));
    }

    /**
     * The methods and URIs the argument designates: those of the named route, else the URI with its method.
     *
     * @return list<array{?string, string}> method (any when null) and OpenAPI path
     */
    private function targets(string $route): array
    {
        $routes = $this->laravel->make(Router::class)->getRoutes();
        // A route named after it was registered is not in the lookup yet.
        $routes->refreshNameLookups();
        $named = $routes->getByName($route);

        if ($named !== null) {
            $targets = [];

            foreach ($named->methods() as $method) {
                foreach (self::paths($named->uri()) as $path) {
                    $targets[] = [$method, $path];
                }
            }

            return $targets;
        }

        $parts = explode(' ', trim($route), 2);

        return count($parts) === 2 ? [[$parts[0], self::path($parts[1])]] : [[null, self::path($parts[0])]];
    }

    /**
     * @param list<array{?string, string}> $targets
     */
    private static function matches(OperationDescription $operation, array $targets): bool
    {
        $method = $operation->method instanceof HttpMethod ? $operation->method->value : $operation->method;

        foreach ($targets as [$targetMethod, $path]) {
            if ($path === $operation->path && ($targetMethod === null || strcasecmp($targetMethod, $method) === 0)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The paths of a route URI: with its optional parameters, then without each trailing one, as the routes are
     * collected (`api/users/{user?}` → `/api/users/{user}`, `/api/users`).
     *
     * @return list<string>
     */
    private static function paths(string $uri): array
    {
        $paths = [self::path($uri)];

        while (preg_match('#/\{[^}]+\?\}$#', $uri) === 1) {
            $uri = (string) preg_replace('#/\{[^}]+\?\}$#', '', $uri);
            $paths[] = self::path($uri);
        }

        return $paths;
    }

    /**
     * `api/users/{user?}` → `/api/users/{user}`.
     */
    private static function path(string $uri): string
    {
        return '/' . ltrim(str_replace('?}', '}', trim($uri)), '/');
    }
}
