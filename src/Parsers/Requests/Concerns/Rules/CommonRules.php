<?php

namespace Ark4ne\OpenApi\Parsers\Requests\Concerns\Rules;

use Ark4ne\OpenApi\Documentation\Request\Body\Parameter;
use Ark4ne\OpenApi\Support\Date;

trait CommonRules
{
    /**
     * @param Parameter $parameter
     */
    public function parseAccepted(Parameter $parameter): void
    {
        $parameter->string()->enum(['yes', 'on', '1', 'true']);
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseAcceptedIf(Parameter $parameter, array $parameters): void
    {
        $this->parseAccepted($parameter);
        $parameter->if(...$parameters);
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseActiveUrl(Parameter $parameter, array $parameters): void
    {
        $parameter->string()->pattern('url'); // TODO description
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseAlpha(Parameter $parameter, array $parameters): void
    {
        $parameter->string()->pattern('alpha:/[a-zA-Z]+/');
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseAlphaDash(Parameter $parameter, array $parameters): void
    {
        $parameter->string()->pattern('alpha-dash:/[\w_-]+/');
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseAlphaNum(Parameter $parameter, array $parameters): void
    {
        $parameter->string()->pattern('alpha-num:/[\w]+/');
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseArray(Parameter $parameter, array $parameters): void
    {
        // TODO : handle params
        $parameter->array();
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseBail(Parameter $parameter, array $parameters): void
    {
        // ignore: not a rule
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseBetween(Parameter $parameter, array $parameters): void
    {
        $parameter->number()->min((float)$parameters[0])->max((float)$parameters[1]);
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseBoolean(Parameter $parameter, array $parameters): void
    {
        $parameter->bool();
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseConfirmed(Parameter $parameter, array $parameters): void
    {
        // TODO desc
    }

    /**
     * @param Parameter $parameter
     */
    public function parseCurrentPassword(Parameter $parameter): void
    {
        $parameter->password(); // TODO desc
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseAfter(Parameter $parameter, array $parameters): void
    {
        $this->parseAfterOrEqual($parameter, $parameters);
        $parameter->exclusiveMin();
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseAfterOrEqual(Parameter $parameter, array $parameters): void
    {
        $this->parseDate($parameter);
        if ($pattern = $this->dateFindPattern($parameters[0])) {
            $parameter->pattern($pattern);
        }
        $parameter->min(strtotime($parameters[0]));
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseBefore(Parameter $parameter, array $parameters): void
    {
        $this->parseBeforeOrEqual($parameter, $parameters);
        $parameter->exclusiveMax();
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseBeforeOrEqual(Parameter $parameter, array $parameters): void
    {
        $this->parseDate($parameter);
        if ($pattern = $this->dateFindPattern($parameters[0])) {
            $parameter->pattern($pattern);
        }
        $parameter->max(strtotime($parameters[0]));
    }

    protected function dateFindPattern(string $date): ?string
    {
        foreach (Parameter::PATTERNS as $pattern) {
            if (Date::isValidForFormat($date, $pattern)) {
                return $pattern;
            }
        }
        foreach (config('openapi.format.date') as $pattern => $_) {
            if (Date::isValidForFormat($date, $pattern)) {
                return $pattern;
            }
        }

        return null;
    }

    /**
     * @param Parameter $parameter
     */
    public function parseDate(Parameter $parameter): void
    {
        $parameter->dateTime();
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseDateEquals(Parameter $parameter, array $parameters): void
    {
        $this->parseDate($parameter);
        $parameter
            ->min(strtotime($parameters[0]))
            ->max(strtotime($parameters[0]))
            ->exclusiveMin()
            ->exclusiveMax();
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseDateFormat(Parameter $parameter, array $parameters): void
    {
        $patterns = array_flip(Parameter::PATTERNS);
        if (isset($patterns[$parameters[0]])) {
            $parameter->{$patterns[$parameters[0]]}();
        } elseif (isset(config('openapi.format.date')[$parameters[0]])) {
            $parameter->{config('openapi.format.date')[$parameters[0]]}();
        } else {
            $parameter->string();
            $parameter->pattern($parameters[0]);
        }
    }

    /**
     * @param Parameter $parameter
     */
    public function parseDeclined(Parameter $parameter): void
    {
        $parameter->string()->enum(['no', 'off', '0', 'false']);
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseDeclinedIf(Parameter $parameter, array $parameters): void
    {
        $this->parseDeclined($parameter);
        $parameter->if(...$parameters);
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseDifferent(Parameter $parameter, array $parameters): void
    {
        $parameter->different(array_shift($parameters));
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseDigits(Parameter $parameter, array $parameters): void
    {
        $parameter->string()->pattern('digits:/[0-9]+/');

        if (isset($parameters[0])) {
            $parameter->min((int)$parameters[0]);
        }
        if (isset($parameters[1])) {
            $parameter->max((int)$parameters[1]);
        }
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseDigitsBetween(Parameter $parameter, array $parameters): void
    {
        $parameter->string()
            ->pattern("digits:/[0-9]+/\{$parameters[0], $parameters[1]}")
            ->min((int)$parameters[0])
            ->max((int)$parameters[1]);
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseDimensions(Parameter $parameter, array $parameters): void
    {
        // TODO
        // @see https://laravel.com/docs/9.x/validation#rule-dimensions
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseDistinct(Parameter $parameter, array $parameters): void
    {
        // TODO
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseEmail(Parameter $parameter, array $parameters): void
    {
        $parameter->string()->pattern('email'); // TODO format (rfc, strict, dns, spoof, filter)
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseEndsWith(Parameter $parameter, array $parameters): void
    {
        $parameter->string()->pattern("ends-with:$parameters[0]");
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseEnum(Parameter $parameter, array $parameters): void
    {
        $isNumber = array_reduce(
            $parameters,
            static fn(bool $isNumber, string $value) => $isNumber && is_numeric($value),
            true
        );

        $parameter->{$isNumber ? 'string' : 'number'}()->enum($parameters);
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseExclude(Parameter $parameter, array $parameters): void
    {
        // ignore: not a rules
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseExcludeIf(Parameter $parameter, array $parameters): void
    {
        // ignore: not a rules
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseExcludeUnless(Parameter $parameter, array $parameters): void
    {
        // ignore: not a rules
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseExcludeWithout(Parameter $parameter, array $parameters): void
    {
        // ignore: not a rules
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseExists(Parameter $parameter, array $parameters): void
    {
        $conditions = [];
        if (isset($parameters[0])) {
            $conditions[] = "for($parameters[0])";
        }
        if (isset($parameters[1])) {
            $conditions[] = "through($parameters[1])";
        }
        $parameter->string()->pattern('exists-in:' . implode(',', $conditions));
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseFile(Parameter $parameter, array $parameters): void
    {
        $parameter->binary();
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseFilled(Parameter $parameter, array $parameters): void
    {
        $parameter->nullable(false);
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseGt(Parameter $parameter, array $parameters): void
    {
        $parameter->greater(array_shift($parameters));
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseGte(Parameter $parameter, array $parameters): void
    {
        $parameter->greaterOrEquals(array_shift($parameters));
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseImage(Parameter $parameter, array $parameters): void
    {
        $parameter->binary()->pattern("type:jpg, jpeg, png, bmp, gif, svg, webp");
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseIn(Parameter $parameter, array $parameters): void
    {
        switch ($parameter->type ?? null) {
            case 'integer':
                $parameters = array_map('intval', $parameters);
                break;
            case 'number':
                $mapper = $parameter->format === Parameter::FORMAT_INTEGER || $parameter->format === Parameter::FORMAT_LONG
                    ? 'intval'
                    : 'floatval';

                $parameters = array_map($mapper, $parameters);
                break;
            default:
                $parameter->string();
        }

        $parameter->enum($parameters);
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseInArray(Parameter $parameter, array $parameters): void
    {
        // TODO
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseInteger(Parameter $parameter, array $parameters): void
    {
        $parameter->int();
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseIp(Parameter $parameter, array $parameters): void
    {
        $parameter->string()->pattern('ip');
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseIpV4(Parameter $parameter, array $parameters): void
    {
        $parameter->string()->pattern('ipv4');
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseIpV6(Parameter $parameter, array $parameters): void
    {
        $parameter->string()->pattern('ipv6');
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseMacAddress(Parameter $parameter, array $parameters): void
    {
        $parameter->string()->pattern('mac-address');
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseJson(Parameter $parameter, array $parameters): void
    {
        $parameter->string()->pattern('json');
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseLt(Parameter $parameter, array $parameters): void
    {
        $parameter->less(array_shift($parameters));
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseLte(Parameter $parameter, array $parameters): void
    {
        $parameter->lessOrEquals(array_shift($parameters));
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseMax(Parameter $parameter, array $parameters): void
    {
        $parameter->max((float)$parameters[0]);
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseMimetypes(Parameter $parameter, array $parameters): void
    {
        $parameter->pattern('mimetypes:' . implode(', ', $parameters));
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseMimes(Parameter $parameter, array $parameters): void
    {
        $parameter->pattern('mime:' . implode(', ', $parameters));
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseMin(Parameter $parameter, array $parameters): void
    {
        $parameter->min((float)$parameters[0]);
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseMultipleOf(Parameter $parameter, array $parameters): void
    {
        $parameter->multipleOf((float)$parameters[0]);
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseNotIn(Parameter $parameter, array $parameters): void
    {
        $parameter->string()->pattern('not:' . implode(',', $parameters));
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseNotRegex(Parameter $parameter, array $parameters): void
    {
        $parameter->string()->pattern('not-match:' . implode(',', $parameters));
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseNullable(Parameter $parameter, array $parameters): void
    {
        $parameter->nullable();
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseNumeric(Parameter $parameter, array $parameters): void
    {
        $parameter->number();
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parsePassword(Parameter $parameter, array $parameters): void
    {
        $parameter->password();
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parsePresent(Parameter $parameter): void
    {
        $parameter->required()->nullable();
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseProhibited(Parameter $parameter, array $parameters): void
    {
        // TODO condition
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseProhibitedIf(Parameter $parameter, array $parameters): void
    {
        $parameter->if(array_shift($parameters), $parameters ?? [], 'prohibited');
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseProhibitedUnless(Parameter $parameter, array $parameters): void
    {
        $parameter->unless(array_shift($parameters), $parameters ?? [], 'prohibited');
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseProhibits(Parameter $parameter, array $parameters): void
    {
        // TODO exclude
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseRegex(Parameter $parameter, array $parameters): void
    {
        $parameter->string()->pattern("match:$parameters[0]");
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseRequired(Parameter $parameter, array $parameters): void
    {
        $parameter->required();
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseRequiredIf(Parameter $parameter, array $parameters): void
    {
        $parameter->if(array_shift($parameters), $parameters ?? [], 'required');
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseRequiredUnless(Parameter $parameter, array $parameters): void
    {
        $parameter->unless(array_shift($parameters), $parameters ?? [], 'required');
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseRequiredWith(Parameter $parameter, array $parameters): void
    {
        $parameter->with($parameters, 'required');
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseRequiredWithAll(Parameter $parameter, array $parameters): void
    {
        $parameter->withAll($parameters, 'required');
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseRequiredWithout(Parameter $parameter, array $parameters): void
    {
        $parameter->without($parameters, 'required');
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseRequiredWithoutAll(Parameter $parameter, array $parameters): void
    {
        $parameter->withoutAll($parameters, 'required');
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseSame(Parameter $parameter, array $parameters): void
    {
        $parameter->same(array_shift($parameters));
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseSize(Parameter $parameter, array $parameters): void
    {
        match ($parameter->type) {
            Parameter::TYPE_ARRAY, Parameter::TYPE_STRING => $parameter->min((int)$parameters[0])->max((int)$parameters[0]),
            Parameter::TYPE_INTEGER, Parameter::TYPE_NUMBER => $parameter->min((float)$parameters[0])->max((float)$parameters[0]),
        };
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseSometimes(Parameter $parameter, array $parameters): void
    {
        // TODO
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseStartsWith(Parameter $parameter, array $parameters): void
    {
        $parameter->string()->pattern("starts-with:$parameters[0]");
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseString(Parameter $parameter, array $parameters): void
    {
        $parameter->string();
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseTimezone(Parameter $parameter, array $parameters): void
    {
        $parameter->string()->pattern('timezone');
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseUnique(Parameter $parameter, array $parameters): void
    {
        // TODO
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseUrl(Parameter $parameter, array $parameters): void
    {
        $parameter->string()->pattern('url');
    }

    /**
     * @param Parameter     $parameter
     * @param array<string> $parameters
     */
    public function parseUuid(Parameter $parameter, array $parameters): void
    {
        $parameter->string()->pattern('uuid');
    }
}
