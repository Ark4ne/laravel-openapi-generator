<?php

namespace Ark4ne\OpenApi\Documentation;

use Ark4ne\OpenApi\Documentation\Request\Parameters;
use Ark4ne\OpenApi\Support\Http;
use Illuminate\Routing\Router;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
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
use RuntimeException;

class DocumentationGenerator
{
    /** @var array<string, Tag> */
    protected array $tags = [];

    /** @var array<string, Tag[]> */
    protected array $groups = [];

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
        $groupBy = $config['groupBy'] ?? null;

        $paths = [];

        foreach ($routes as $route) {
            $entry = new DocumentationEntry($route);

            $operations = [];
            foreach ($entry->getMethods() as $method) {
                $request = $this->request($entry);

                /** @var Operation $operation */
                $operation = Operation::$method();
                $operation = $operation
                    ->operationId("$method:{$entry->getName()}")
                    ->summary(Str::studly($entry->getName()))
                    ->tags($this->tag($entry->getControllerName()))
                    ->parameters(
                        ...(new Parameters($request['parameters'] ?? []))->convert(OASParameter::IN_PATH),
                        ...(new Parameters($request['headers'] ?? []))->convert(OASParameter::IN_HEADER),
                        ...(new Parameters($request['queries'] ?? []))->convert(OASParameter::IN_QUERY),
                        ...(
                    !Http::acceptBody($method)
                        ? (new Parameters($request['body'] ?? []))->convert(OASParameter::IN_QUERY)
                        : []
                    ),
                    )
                    ->responses(Response::ok());

                if (Http::acceptBody($method)) {
                    $operation = $operation->requestBody((new Parameters($request['body'] ?? []))->convert('body'));
                }

                if ($groupBy) {
                    $by = null;

                    switch ($groupBy['by']) {
                        case 'ControllerClass' :
                            $by = $entry->getControllerClass();
                    }

                    if ($by && preg_match($groupBy['regex'], $by, $match)) {
                        $this->groups($match[1], $operation->tags);
                    }
                }

                $operations[] = $operation;
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

        if (!empty($this->groups)) {
            $openApi = $openApi->x('tagGroups', array_values($this->groups));
        }

        if (!is_dir($dir = config("openapi.output-dir")) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $dir));
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

    protected function groups(string $name, array $tags): void
    {
        $key = strtolower($name);

        $this->groups[$key]['name'] = $name;
        $this->groups[$key]['tags'] = array_unique(array_merge($this->groups[$key]['tags'] ?? [], $tags));
    }

    /**
     * @param \Ark4ne\OpenApi\Documentation\DocumentationEntry $entry
     *
     * @throws \Exception
     * @return null|array{
     *     parameters?: array<string, Request\Parameter>,
     *     headers?: array<string, Request\Parameter>,
     *     body?: array<string, Request\Parameter>,
     *     queries?: array<string, Request\Parameter>
     * }
     */
    protected function request(DocumentationEntry $entry): ?array
    {
        return $this->parse(config('openapi.parsers.requests'), $entry->getRequestClass(), $entry);
    }

    protected function response(DocumentationEntry $entry)
    {
        return $this->parse(config('openapi.parsers.responses'), $entry->getResponseClass(), $entry);
    }

    /**
     * @param array<class-string>                              $parsers
     * @param mixed                                            $element
     * @param \Ark4ne\OpenApi\Documentation\DocumentationEntry $entry
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     * @return mixed
     */
    protected function parse(array $parsers, mixed $element, DocumentationEntry $entry): mixed
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
