<?php

namespace Ark4ne\OpenApi\Documentation;

use Ark4ne\OpenApi\Documentation\Request\Parameters;
use Ark4ne\OpenApi\Documentation\Request\Security;
use Ark4ne\OpenApi\Support\Http;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;
use GoldSpecDigital\ObjectOrientedOAS\Objects\{Info,
    MediaType,
    Operation,
    Parameter as OASParameter,
    PathItem,
    Response,
    Schema,
    SecurityRequirement,
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

    /** @var array<string, mixed> */
    protected array $config;

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
        $this->config = config("openapi.versions.$version");

        $routes = $this->getRoutes($this->config['routes']);

        $paths = [];

        foreach ($routes as $route) {
            $entry = new DocumentationEntry($route);

            $operations = [];

            foreach ($entry->getMethods() as $method) {
                $operations[] = $this->operation($entry, $method);
            }

            $paths[] = PathItem::create()
                ->route('/' . ltrim($entry->getUri(), '/'))
                ->operations(...$operations);
        }

        $info = Info::create()
            ->title($this->config['title'])
            ->version($version)
            ->description($this->config['description']);

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
            "$dir/{$this->config['output-file']}",
            $openApi->toJson()
        );
    }

    /**
     * @param \Ark4ne\OpenApi\Documentation\DocumentationEntry $entry
     * @param string                                           $method
     *
     * @throws \GoldSpecDigital\ObjectOrientedOAS\Exceptions\InvalidArgumentException
     * @return \GoldSpecDigital\ObjectOrientedOAS\Objects\Operation
     */
    protected function operation(DocumentationEntry $entry, string $method): Operation
    {
        $request = $entry->request();

        /** @var Operation $operation */
        $operation = Operation::$method();
        $operation = $operation
            ->operationId("$method:{$entry->getName()}")
            ->summary(Str::studly($entry->getName()))
            ->tags($this->tag($entry->getControllerName()))
            ->parameters(
                ...(new Parameters($request->parameters()))->convert(OASParameter::IN_PATH),
                ...(new Parameters($request->headers()))->convert(OASParameter::IN_HEADER),
                ...(new Parameters($request->queries()))->convert(OASParameter::IN_QUERY),
                ...(
            !Http::acceptBody($method)
                ? (new Parameters($request->body()))->convert(OASParameter::IN_QUERY)
                : []
            ),
            )
            ->responses(Response::ok());

        if (Http::acceptBody($method)) {
            $operation = $operation->requestBody((new Parameters($request->body()))->convert('body'));
        }

        if (!empty($request->securities())) {
            $operation->security(
                ...collect($request->securities())
                ->map(fn(Security $security) => $security->oasRequirement())
                ->all()
            );
        }

        $this->group($entry, $operation->tags);

        return $operation;
    }

    /**
     * @param \Ark4ne\OpenApi\Documentation\DocumentationEntry $entry
     * @param string[]                                         $tags
     *
     * @return void
     */
    protected function group(DocumentationEntry $entry, array $tags): void
    {
        if (!($group = $this->config['groupBy'] ?? null)) {
            return;
        }

        if (is_callable($group)) {
            $by = $group($entry);

            if ($by) {
                $this->groupBy($by, $tags);
            }

            return;
        }

        $by = match ($group['by'] ?? null) {
            'uri' => $entry->getUri(),
            'name' => $entry->getName(),
            'controller' => $entry->getControllerClass(),
            default => null
        };

        if ($by && preg_match($group['regex'], $by, $match)) {
            $this->groupBy($match[1], $tags);
        }
    }

    /**
     * @param string   $name
     * @param string[] $tags
     *
     * @return void
     */
    protected function groupBy(string $name, array $tags): void
    {
        $key = strtolower($name);

        $this->groups[$key]['name'] = $name;
        $this->groups[$key]['tags'] = array_unique(array_merge($this->groups[$key]['tags'] ?? [], $tags));
    }

    protected function tag(string $name): Tag
    {
        return $this->tags[strtolower($name)] ??= Tag::create($name)->name($name);
    }
}
