<?php

namespace Ark4ne\OpenApi\Documentation;

use Illuminate\Routing\Router;
use GoldSpecDigital\ObjectOrientedOAS\Objects\{Info,
    MediaType,
    Operation,
    Parameter as OASParameter,
    PathItem,
    Response,
    Schema,
    Tag
};
use GoldSpecDigital\ObjectOrientedOAS\OpenApi;

class DocumentationGenerator
{
    /** @var array<string, Tag> */
    protected array $tags = [];

    public function __construct(
        private Router $router
    ) {
    }

    /**
     * @param array<string> $rules
     *
     * @return iterable<\Illuminate\Routing\Route>
     */
    public function getRoutes(array $rules): iterable
    {
        return collect($this->router->getRoutes()->getRoutes())->filter(
            static fn($route) => !($route->getAction('uses') instanceof \Closure) &&
                collect($rules)->some(static fn($rule) => fnmatch($rule, $route->uri))
        );
    }

    public function generate(string $version): void
    {
        $config = config("openapi.versions.$version");

        $routes = $this->getRoutes($config['routes']);

        $paths = [];

        foreach ($routes as $route) {
            $entry = new DocumentationEntry($route);

            $operations = [];
            foreach ($entry->getMethods() as $method) {
                $request = $this->request($entry);

                /** @var Operation $operation */
                $operation = Operation::$method();
                $operations[] = $operation
                    ->operationId("$method:{$entry->getName()}")
                    ->tags($this->tag($entry->getControllerName()))
                    ->parameters(
                        ...collect($entry->getPathParameters())
                        ->map(fn(string $name) => OASParameter::path()->name($name))
                        ->values()
                        ->all(),
                        ...collect($request['headers'] ?? [])
                        ->map(fn(Request\Body\Parameter $param) => $param->convert(OASParameter::IN_HEADER))
                        ->values()
                        ->all(),
                        ...collect($request['queries'] ?? [])
                        ->map(fn(Request\Body\Parameter $param) => $param->convert(OASParameter::IN_QUERY))
                        ->values()
                        ->all(),
                        ...collect($request['body'] ?? [])
                        ->map(fn(Request\Body\Parameter $param) => $param->convert(OASParameter::IN_QUERY))
                        ->values()
                        ->all()
                    )
                    ->responses(Response::ok());
            }

            $paths[] = PathItem::create()
                ->route('/' . ltrim($entry->getUri(), '/'))
                ->operations(...$operations);
        }

        $info = Info::create()
            ->title($config['title'])
            ->version($version)
            ->description($config['title']);

        $openApi = OpenApi::create()
            ->openapi(OpenApi::OPENAPI_3_0_2)
            ->info($info)
            ->paths(...$paths)
            ->tags(...array_values($this->tags));

        if (!is_dir($dir = config("openapi.output-dir")) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $dir));
        }

        file_put_contents(
            "$dir/{$config['output-file']}",
            $openApi->toJson()
        );
    }

    protected function tag(string $name): Tag
    {
        return $this->tags[strtolower($name)] ??= Tag::create($name)->name($name);
    }

    /**
     * @param \Ark4ne\OpenApi\Documentation\DocumentationEntry $entry
     *
     * @throws \Exception
     * @return null|array{headers?: array<string, Request\Body\Parameter>, body?: array<string, Request\Body\Parameter>, queries?: array<string, Request\Body\Parameter>}
     */
    protected function request(DocumentationEntry $entry): ?array
    {
        return $this->parse(config('openapi.parsers.requests'), $entry->getRequestClass(), $entry);
    }

    protected function response(DocumentationEntry $entry)
    {
        return $this->parse(config('openapi.parsers.responses'), $entry->getResponseClass(), $entry);
    }

    protected function parse(array $parsers, mixed $element, DocumentationEntry $entry)
    {
        if (empty($element)) {
            return null;
        }

        foreach ($parsers as $for => $parser) {
            if (is_a($element, $for, true)) {
                return app()->make($parser)->parse($element, $entry);
            }
        }

        throw new \Exception("TODO: Can't parse $element");
    }
}
