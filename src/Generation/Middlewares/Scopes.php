<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Generation\Middlewares;

use Ark4ne\OpenApi\Description\SecurityDescription;
use Ark4ne\OpenApiWriter\Objects\OAuthFlow;
use Ark4ne\OpenApiWriter\Objects\OAuthFlows;

/**
 * Puts scopes on the requirements of a scheme.
 */
final class Scopes
{
    /**
     * The requirements, those of the scheme repeated for each alternative set of scopes; the scheme alone when no
     * requirement names it.
     *
     * @param list<SecurityDescription> $security
     * @param list<list<string>> $alternatives
     *
     * @return list<SecurityDescription>
     */
    public static function apply(array $security, string $scheme, array $alternatives): array
    {
        $applied = [];
        $found = false;

        foreach ($security as $requirement) {
            if (! array_key_exists($scheme, $requirement->schemes)) {
                $applied[] = $requirement;

                continue;
            }

            $found = true;

            foreach ($alternatives as $scopes) {
                $applied[] = $requirement->addSchemes([$scheme => $scopes]);
            }
        }

        if (! $found) {
            foreach ($alternatives as $scopes) {
                $applied[] = SecurityDescription::create([$scheme => $scopes]);
            }
        }

        return $applied;
    }

    /**
     * The flows with these scopes declared, named after themselves when they have no description yet.
     *
     * @param list<string> $scopes
     */
    public static function declare(OAuthFlows $flows, array $scopes): OAuthFlows
    {
        $named = array_combine($scopes, $scopes);

        return $flows
            ->implicit(self::flow($flows->implicit, $named))
            ->password(self::flow($flows->password, $named))
            ->clientCredentials(self::flow($flows->clientCredentials, $named))
            ->authorizationCode(self::flow($flows->authorizationCode, $named));
    }

    /**
     * @param array<string, string> $scopes
     */
    private static function flow(?OAuthFlow $flow, array $scopes): ?OAuthFlow
    {
        return $flow?->scopes([...$scopes, ...$flow->scopes ?? []]);
    }
}
