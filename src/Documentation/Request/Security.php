<?php

namespace Ark4ne\OpenApi\Documentation\Request;

use Ark4ne\OpenApi\Contracts\OASSchematable;
use Ark4ne\OpenApi\Documentation\Request\Concerns\OASProxy;
use GoldSpecDigital\ObjectOrientedOAS\Objects\SecurityScheme;

/**
 * @uses OASProxy<\GoldSpecDigital\ObjectOrientedOAS\Objects\SecurityScheme>
 *
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
    use OASProxy;

    const TYPE_API_KEY = 'apiKey';
    const TYPE_HTTP = 'http';
    const TYPE_OAUTH2 = 'oauth2';
    const TYPE_OPEN_ID_CONNECT = 'openIdConnect';

    const IN_QUERY = 'query';
    const IN_HEADER = 'header';
    const IN_COOKIE = 'cookie';

    /**
     * @param mixed ...$args
     */
    public function __construct(...$args)
    {
        $this->object = new SecurityScheme(...$args);
    }

    public static function component(string $id, callable $callback): self
    {
        if (!Component::has($id, Component::SCOPE_SECURITY_SCHEMES)) {
            $component = Component::create($id, Component::SCOPE_SECURITY_SCHEMES);
            $component->object($callback(new self($id)));
        }

        return new self($id);
    }
}
