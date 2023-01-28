<?php

namespace Ark4ne\OpenApi\OAS\Objects;

use GoldSpecDigital\ObjectOrientedOAS\Exceptions\InvalidArgumentException;
use GoldSpecDigital\ObjectOrientedOAS\Objects\SecurityRequirement as BaseSecurityRequirement;
use GoldSpecDigital\ObjectOrientedOAS\Objects\SecurityScheme;
use GoldSpecDigital\ObjectOrientedOAS\Utilities\Arr;

/**
 * Override GoldSpecDigital\ObjectOrientedOAS\Objects\SecurityRequirement
 *
 * SecurityRequirement must can have multiple SecurityScheme.
 *
 * @property string[] $securityScheme
 */
class SecurityRequirement extends BaseSecurityRequirement
{
    /**
     * @param null|string|SecurityScheme|array<SecurityScheme|string> $securityScheme
     * @return $this
     * @throws InvalidArgumentException
     */
    public function securityScheme($securityScheme): self
    {
        if (!is_array($securityScheme)) {
            $securityScheme = [$securityScheme];
        }

        $securityScheme = array_map(function ($scheme) {
            // If a SecurityScheme instance passed in, then use its Object ID.
            if ($scheme instanceof SecurityScheme) {
                $scheme = $scheme->objectId;
            }

            // If the $securityScheme is not a string or null then thrown an exception.
            if (!is_string($scheme) && !is_null($scheme)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'The security scheme must either be an instance of [%s], a string or null.',
                        SecurityScheme::class
                    )
                );
            }

            return $scheme;
        }, $securityScheme);

        $instance = clone $this;

        $instance->securityScheme = $securityScheme;

        return $instance;
    }

    public function scopes(mixed ...$scopes): self
    {
        $instance = clone $this;

        $instance->scopes = $scopes ?: null;

        return $instance;
    }

    public function generate(): array
    {
        return Arr::filter(
            collect($this->securityScheme)
                ->mapWithKeys(fn($scheme) => [$scheme => $this->scopes[$scheme] ?? []])
                ->all()
        );
    }
}
