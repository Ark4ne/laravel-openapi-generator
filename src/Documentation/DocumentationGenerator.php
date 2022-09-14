<?php

namespace Ark4ne\OpenApi\Documentation;

use Ark4ne\OpenApi\Documentation\Request\Component;
use Ark4ne\OpenApi\Documentation\Request\Content;
use Ark4ne\OpenApi\Documentation\Request\Parameter;
use Ark4ne\OpenApi\Documentation\Request\Parameters;
use Ark4ne\OpenApi\Documentation\Request\Security;
use Ark4ne\OpenApi\Errors\Log;
use Ark4ne\OpenApi\Support\Config;
use Ark4ne\OpenApi\Support\Http;
use Illuminate\Routing\Router;
use GoldSpecDigital\ObjectOrientedOAS\Objects\{
    Info,
    Operation,
    Parameter as OASParameter,
    PathItem,
    Response,
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

    public function generate(string $version, string $lang = null): void
    {
        $routes = $this->getRoutes(Config::routes());
        $ignoreVerbs = array_map('strtoupper', Config::ignoreVerbs());

        $paths = [];

        foreach ($routes as $route) {
            $entry = new DocumentationEntry($route);

            $operations = [];

            foreach ($entry->getMethods() as $method) {
                if (!in_array(strtoupper($method), $ignoreVerbs, true)) {
                    $operations[] = $this->operation($entry, $method, true);
                }
            }

            $paths[] = PathItem::create()
                ->route('/' . ltrim($entry->getRouteUri(), '/'))
                ->operations(...$operations);
        }

        $info = Info::create()
            ->title(Config::title())
            ->version($version)
            ->description(Config::description());

        $openApi = OpenApi::create()
            ->openapi(OpenApi::OPENAPI_3_0_2)
            ->info($info)
            ->paths(...$paths)
            ->tags(...array_values($this->tags))
            ->components(Component::convert());

        if (!empty($this->groups)) {
            $openApi = $openApi->x('tagGroups', array_values($this->groups));
        }

        if (!is_dir($dir = Config::outputDir()) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $dir));
        }

        $path = Config::outputFile();

        file_put_contents(
            $lang ? "$dir/$lang-$path" : "$dir/$path",
            $openApi->toJson()
        );
    }

    /**
     * @param \Ark4ne\OpenApi\Documentation\DocumentationEntry $entry
     * @param string                                           $method
     * @param bool                                             $flatParameters
     *
     * @throws \GoldSpecDigital\ObjectOrientedOAS\Exceptions\InvalidArgumentException
     * @return \GoldSpecDigital\ObjectOrientedOAS\Objects\Operation
     */
    protected function operation(DocumentationEntry $entry, string $method, bool $flatParameters): Operation
    {
        Log::info("Operation", "[$method] " . $entry->getRouteUri());

        $request = $entry->request();

        /** @var Operation $operation */
        $operation = Operation::$method();
        $operation = $operation
            ->operationId("$method:{$entry->getRouteName()}")
            ->summary($entry->getName())
            ->description($entry->getDescription())
            ->tags($this->tag($entry))
            ->parameters(
                ...(new Parameters($request->parameters()))->convert(OASParameter::IN_PATH),
                ...(new Parameters($request->headers()))->convert(OASParameter::IN_HEADER),
                ...(new Parameters($request->queries()))->convert(OASParameter::IN_QUERY, null, $flatParameters),
                ...(
            !Http::acceptBody($method)
                ? (new Parameters($request->body()))->convert(OASParameter::IN_QUERY, null, $flatParameters)
                : []
            ),
            )
            ->responses(Response::ok());

        if (Http::acceptBody($method)) {
            $operation = $operation->requestBody((new Parameters($request->body()))->convert(
                'body',
                ($request->headers()['Content-Type'] ?? null)?->enum
            ));
        }

        if (!empty($request->securities())) {
            $operation = $operation->security(
                ...collect($request->securities())
                ->map(fn(Security $security) => $security->oasRequirement())
                ->all()
            );
        }

        if (Http::canReturnContent($method)) {
            try {
                $responses[] = $this->convertResponse($entry->response());

                if ($request->hasRules()) {
                    // have rules
                    $responses[] = Response::create()
                        ->statusCode(422)
                        ->description('Unprocessable Entity');
                }

                $operation = $operation->responses(...$responses);
            } catch (\Throwable $e) {
                Log::warn('Response', 'Error when trying to generate response : ' . $e->getMessage());
            }
        }

        $this->group($entry, $operation->tags);

        return $operation;
    }

    protected function convertResponse(ResponseEntry $entry): Response
    {
        $response = Response::create()
            ->statusCode($entry->statusCode() ?: 200)
            ->description($entry->statusName() ?: '')
            ->headers(...$entry->headers());

        if ($body = $entry->body()) {
            if ($body instanceof Parameter) {
                $response = $response->content(Content::convert($body->oasSchema(), $entry->format()));
            } else {
                $response = $response->content($body);
            }
        }

        return $response;
    }

    /**
     * @param \Ark4ne\OpenApi\Documentation\DocumentationEntry $entry
     * @param string[]                                         $tags
     *
     * @return void
     */
    protected function group(DocumentationEntry $entry, array $tags): void
    {
        if ($name = $entry->getGroup()) {
            $key = strtolower($name);

            $this->groups[$key]['name'] = $name;
            $this->groups[$key]['tags'] = array_unique(array_merge($this->groups[$key]['tags'] ?? [], $tags));
        }
    }

    protected function tag(DocumentationEntry $entry): Tag
    {
        $name = $entry->getTag();

        return $this->tags[strtolower($name)] ??= Tag::create($name)->name($name);
    }
}
