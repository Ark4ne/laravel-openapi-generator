<?php

namespace Ark4ne\OpenApi\Documentation\Request;

use Ark4ne\OpenApi\Contracts\OASSchematable;
use Ark4ne\OpenApi\Documentation\Request\Concerns\OASProxy;
use Ark4ne\OpenApi\OAS\Objects\SecurityRequirement as OASSecurityRequirement;

/**
 * @uses OASProxy<\Ark4ne\OpenApi\OAS\Objects\SecurityRequirement>
 */
class SecurityRequirement implements OASSchematable
{
    use OASProxy;

    /**
     * @param mixed ...$args
     */
    public function __construct(...$args)
    {
        $this->object = new OASSecurityRequirement(...$args);
    }

    public function addSecurity(Security $security): self
    {
        $this->securityScheme(array_merge($this->securityScheme ?? [], [$security->objectId]));

        return $this;
    }
}
