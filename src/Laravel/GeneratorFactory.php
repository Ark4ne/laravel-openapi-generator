<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Laravel;

use Ark4ne\OpenApi\Configuration\Configuration;
use Ark4ne\OpenApi\Generation\Generator;
use Ark4ne\OpenApi\Integrations\BuiltInIntegrations;
use Ark4ne\OpenApi\Sampling\DatabaseSamplingSession;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Routing\Router;

/**
 * Builds the generator of a configuration read once: its sampling session, the built-in integrations and the
 * extensions of the application, resolved through the container.
 */
final readonly class GeneratorFactory
{
    public function __construct(
        private Container $container,
        private Repository $config,
    ) {}

    /**
     * @throws \Ark4ne\OpenApi\Exceptions\InvalidConfigurationException when an extension is of the wrong kind
     */
    public function make(Configuration $configuration): Generator
    {
        $default = $this->config->get('database.default');
        // The default connection: the others may not be reachable where the documents are generated.
        $session = new DatabaseSamplingSession($this->container->make(ConnectionResolverInterface::class), is_string($default) ? [$default] : [], $configuration->sampling);
        $translator = $this->container->bound(Translator::class) ? $this->container->make(Translator::class) : null;
        $extensions = (new ExtensionsResolver($this->container))->resolve($configuration->extensions);

        return Generator::make($this->container->make(Router::class), $this->container, $session, $translator, BuiltInIntegrations::all($configuration->integrations->jsonApiErrors), $extensions);
    }
}
