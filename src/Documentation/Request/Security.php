<?php

namespace Ark4ne\OpenApi\Documentation\Request;

use Ark4ne\OpenApi\Contracts\OASSchematable;
use GoldSpecDigital\ObjectOrientedOAS\Objects\SecurityRequirement;
use GoldSpecDigital\ObjectOrientedOAS\Objects\SecurityScheme;

/**
 * @property-read string|null                                                 $type
 * @property-read string|null                                                 $description
 * @property-read string|null                                                 $name
 * @property-read string|null                                                 $in
 * @property-read string|null                                                 $scheme
 * @property-read string|null                                                 $bearerFormat
 * @property-read \GoldSpecDigital\ObjectOrientedOAS\Objects\OAuthFlow[]|null $flows
 * @property-read string|null                                                 $openIdConnectUrl
 *
 * @method self in(?string $in)
 * @method self type(?string $type)
 * @method self name(?string $name)
 * @method self description(?string $description)
 * @method self scheme(?string $scheme)
 * @method self bearerFormat(?string $bearerFormat)
 * @method self flows(?string $flows)
 * @method self openIdConnectUrl(?string $openIdConnectUrl)
 */
class Security implements OASSchematable
{
    const TYPE_API_KEY = 'apiKey';
    const TYPE_HTTP = 'http';
    const TYPE_OAUTH2 = 'oauth2';
    const TYPE_OPEN_ID_CONNECT = 'openIdConnect';

    const IN_QUERY = 'query';
    const IN_HEADER = 'header';
    const IN_COOKIE = 'cookie';

    private SecurityScheme $scheme;

    /**
     * @param mixed ...$args
     */
    public function __construct(...$args)
    {
        $this->scheme = new SecurityScheme(...$args);
    }

    public function oasSchema(): SecurityScheme
    {
        return $this->scheme;
    }

    public function oasRequirement(): SecurityRequirement
    {
        return SecurityRequirement::create()->securityScheme($this->oasSchema());
    }

    /**
     * @param string       $name
     * @param array<mixed> $args
     *
     * @return mixed
     */
    public function __call(string $name, array $args): mixed
    {
        $this->scheme = $this->scheme->$name(...$args);

        return $this;
    }

    public function __get(string $name): mixed
    {
        return $this->scheme->$name;
    }

    public function __set(string $name, mixed $value): void
    {
        $this->scheme->$name = $value;
    }

    public function __isset(string $name): bool
    {
        return isset($this->scheme->$name);
    }
}
