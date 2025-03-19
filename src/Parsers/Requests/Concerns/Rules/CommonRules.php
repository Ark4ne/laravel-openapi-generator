<?php

namespace Ark4ne\OpenApi\Parsers\Requests\Concerns\Rules;

use Ark4ne\OpenApi\Documentation\Request\Parameter;
use Ark4ne\OpenApi\Parsers\Requests\Concerns\RegexParser;
use Ark4ne\OpenApi\Support\Date;

trait CommonRules
{
    use RegexParser {
        parseRegex as _parseRegex;
    }

    public function parseAccepted(): void
    {
        $this->parameter->string()->enum(['yes', 'on', '1', 'true']);
    }

    /**
     * @param array<string> $parameters
     */
    public function parseAcceptedIf(array $parameters): void
    {
        $this->parseAccepted();
        $this->parameter->if(...$parameters);
    }

    /**
     * @param array<string> $parameters
     */
    public function parseActiveUrl(array $parameters): void
    {
        $this->parameter->string()->pattern('url');
    }

    /**
     * @param array<string> $parameters
     */
    public function parseAlpha(array $parameters): void
    {
        $this->parameter->string()->pattern('alpha:/[a-zA-Z]+/');
    }

    /**
     * @param array<string> $parameters
     */
    public function parseAlphaDash(array $parameters): void
    {
        $this->parameter->string()->pattern('alpha-dash:/[\w_-]+/');
    }

    /**
     * @param array<string> $parameters
     */
    public function parseAlphaNum(array $parameters): void
    {
        $this->parameter->string()->pattern('alpha-num:/[\w]+/');
    }

    /**
     * @param array<string> $parameters
     */
    public function parseArray(array $parameters): void
    {
        // TODO : handle params
        $this->parameter->array();
    }

    /**
     * @param array<string> $parameters
     */
    public function parseBail(array $parameters): void
    {
        // ignore: not a rule
    }

    /**
     * @param array<string> $parameters
     */
    public function parseBetween(array $parameters): void
    {
        $this->parameter->number()->min((float)$parameters[0])->max((float)$parameters[1]);
    }

    /**
     * @param array<string> $parameters
     */
    public function parseBoolean(array $parameters): void
    {
        $this->parameter->bool();
        $this->parameter->description('acceptable: `true`, `false`, `1`, `0`, `"1"`, `"0"`');
    }

    /**
     * @param array<string> $parameters
     */
    public function parseConfirmed(array $parameters): void
    {
        // TODO desc
    }

    public function parseCurrentPassword(): void
    {
        $this->parameter->password(); // TODO desc
    }

    /**
     * @param array<string> $parameters
     */
    public function parseAfter(array $parameters): void
    {
        $this->parseAfterOrEqual($parameters);
        $this->parameter->exclusiveMin();
    }

    /**
     * @param array<string> $parameters
     */
    public function parseAfterOrEqual(array $parameters): void
    {
        $this->parseDate();
        if (!isset($this->parameter->pattern) && $pattern = $this->dateFindPattern($parameters[0])) {
            $this->parameter->pattern($pattern);
        }
        $this->parameter->min($this->strToTime($parameters[0]));
    }

    /**
     * @param array<string> $parameters
     */
    public function parseBefore(array $parameters): void
    {
        $this->parseBeforeOrEqual($parameters);
        $this->parameter->exclusiveMax();
    }

    /**
     * @param array<string> $parameters
     */
    public function parseBeforeOrEqual(array $parameters): void
    {
        $this->parseDate();
        if (!isset($this->parameter->pattern) && $pattern = $this->dateFindPattern($parameters[0])) {
            $this->parameter->pattern($pattern);
        }
        $this->parameter->max($this->strToTime($parameters[0]));
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

    protected function getDateFormat(): ?string
    {
        foreach ($this->rules as $entry) {
            if ($entry['rule'] === 'DateFormat') {
                return $entry['parameters'][0] ?? null;
            }
        }

        return null;
    }

    protected function getDatePattern(): ?string
    {
        if ($format = $this->getDateFormat()) {
            $patterns = config('openapi.format.date');

            return $patterns[$format] ?? null;
        }

        return null;
    }

    protected function strToTime(string $datetime): int
    {
        return ($format = $this->getDateFormat())
            ? \DateTime::createFromFormat($format, $datetime)->getTimestamp()
            : strtotime($datetime);
    }

    public function parseDate(): void
    {
        if ($pattern = $this->getDatePattern()) {
            $this->parameter->$pattern();
        } else {
            $this->parameter->dateTime();
        }
    }

    /**
     * @param array<string> $parameters
     */
    public function parseDateEquals(array $parameters): void
    {
        $this->parseDate();
        $this->parameter
            ->min($this->strToTime($parameters[0]))
            ->max($this->strToTime($parameters[0]))
            ->exclusiveMin()
            ->exclusiveMax();
    }

    /**
     * @param array<string> $parameters
     */
    public function parseDateFormat(array $parameters): void
    {
        $patterns = array_flip(Parameter::PATTERNS);
        if (isset($patterns[$parameters[0]])) {
            $this->parameter->{$patterns[$parameters[0]]}();
        } elseif (isset(config('openapi.format.date')[$parameters[0]])) {
            $this->parameter->{config('openapi.format.date')[$parameters[0]]}();
        } else {
            $this->parameter->string();
            $this->parameter->pattern($parameters[0]);
        }
    }

    public function parseDeclined(): void
    {
        $this->parameter->string()->enum(['no', 'off', '0', 'false']);
    }

    /**
     * @param array<string> $parameters
     */
    public function parseDeclinedIf(array $parameters): void
    {
        $this->parseDeclined();
        $this->parameter->if(...$parameters);
    }

    /**
     * @param array<string> $parameters
     */
    public function parseDifferent(array $parameters): void
    {
        $this->parameter->different(array_shift($parameters));
    }

    /**
     * @param array<string> $parameters
     */
    public function parseDigits(array $parameters): void
    {
        $this->parameter->string(false);

        if ($this->parameter->type === Parameter::TYPE_INTEGER || $this->parameter->type === Parameter::TYPE_NUMBER) {
            if (isset($parameters[0], $parameters[1])) {
                $this->parameter->min(10 ** (int)$parameters[0]);
                $this->parameter->max(10 ** ((int)$parameters[1] + 1) - 1);
            } else if (isset($parameters[0])) {
                $this->parameter->min(10 ** (int)$parameters[0]);
                $this->parameter->max(10 ** ((int)$parameters[0] + 1) - 1);
            }
        } else if (isset($parameters[0], $parameters[1])) {
            $this->parameter->pattern('digits:/[0-9]+/\{' . $parameters[0] . ', ' . $parameters[1] . '}');
            $this->parameter->min((int)$parameters[0]);
            $this->parameter->max((int)$parameters[1]);
        }
        else if (isset($parameters[0])) {
            $this->parameter->pattern('digits:/[0-9]+/\{' . $parameters[0] .'}');
            $this->parameter->min((int)$parameters[0]);
        }
    }

    /**
     * @param array<string> $parameters
     */
    public function parseDigitsBetween(array $parameters): void
    {
        $this->parseDigits($parameters);
    }

    /**
     * @param array<string> $parameters
     */
    public function parseDimensions(array $parameters): void
    {
        // TODO
        // @see https://laravel.com/docs/9.x/validation#rule-dimensions
    }

    /**
     * @param array<string> $parameters
     */
    public function parseDistinct(array $parameters): void
    {
        // TODO
    }

    /**
     * @param array<string> $parameters
     */
    public function parseEmail(array $parameters): void
    {
        $this->parameter->string()->pattern('email'); // TODO format (rfc, strict, dns, spoof, filter)
    }

    /**
     * @param array<string> $parameters
     */
    public function parseEndsWith(array $parameters): void
    {
        $this->parameter->string()->pattern("ends-with:$parameters[0]");
    }

    /**
     * @param array<string> $parameters
     */
    public function parseEnum(array $parameters): void
    {
        $isNumber = array_reduce(
            $parameters,
            static fn(bool $isNumber, string $value) => $isNumber && is_numeric($value),
            true
        );

        $this->parameter->{$isNumber ? 'string' : 'number'}()->enum($parameters);
    }

    /**
     * @param array<string> $parameters
     */
    public function parseExclude(array $parameters): void
    {
        // TODO: description
    }

    /**
     * @param array<string> $parameters
     */
    public function parseExcludeIf(array $parameters): void
    {
        $this->parameter->unless(array_shift($parameters), $parameters ?? [], 'available');
    }

    /**
     * @param array<string> $parameters
     */
    public function parseExcludeUnless(array $parameters): void
    {
        $this->parameter->if(array_shift($parameters), $parameters ?? [], 'available');
    }

    /**
     * @param array<string> $parameters
     */
    public function parseExcludeWithout(array $parameters): void
    {
        $this->parameter->with($parameters ?? [], 'available');
    }

    /**
     * @param array<string> $parameters
     */
    public function parseExists(array $parameters): void
    {
        $conditions = [];
        if (isset($parameters[0])) {
            $conditions[] = "for($parameters[0])";
        }
        if (isset($parameters[1])) {
            $conditions[] = "through($parameters[1])";
        }
        $this->parameter->string()->pattern('exists-in:' . implode(',', $conditions));
    }

    /**
     * @param array<string> $parameters
     */
    public function parseFile(array $parameters): void
    {
        $this->parameter->binary();
    }

    /**
     * @param array<string> $parameters
     */
    public function parseFilled(array $parameters): void
    {
        $this->parameter->nullable(false);
    }

    /**
     * @param array<string> $parameters
     */
    public function parseGt(array $parameters): void
    {
        $this->parameter->greater(array_shift($parameters));
    }

    /**
     * @param array<string> $parameters
     */
    public function parseGte(array $parameters): void
    {
        $this->parameter->greaterOrEquals(array_shift($parameters));
    }

    /**
     * @param array<string> $parameters
     */
    public function parseImage(array $parameters): void
    {
        $this->parameter->binary()->pattern("type:jpg, jpeg, png, bmp, gif, svg, webp");
    }

    /**
     * @param array<string> $parameters
     */
    public function parseIn(array $parameters): void
    {
        switch ($this->parameter->type ?? null) {
            case 'integer':
                $parameters = array_map('intval', $parameters);
                break;
            case 'number':
                $mapper = $this->parameter->format === Parameter::FORMAT_INTEGER || $this->parameter->format === Parameter::FORMAT_LONG
                    ? 'intval'
                    : 'floatval';

                $parameters = array_map($mapper, $parameters);
                break;
            default:
                $this->parameter->string();
        }

        $this->parameter->enum($parameters);
    }

    /**
     * @param array<string> $parameters
     */
    public function parseInArray(array $parameters): void
    {
        // TODO
    }

    /**
     * @param array<string> $parameters
     */
    public function parseInteger(array $parameters): void
    {
        $this->parameter->int();
    }

    /**
     * @param array<string> $parameters
     */
    public function parseIp(array $parameters): void
    {
        $this->parameter->string()->pattern('ip');
    }

    /**
     * @param array<string> $parameters
     */
    public function parseIpV4(array $parameters): void
    {
        $this->parameter->string()->pattern('ipv4');
    }

    /**
     * @param array<string> $parameters
     */
    public function parseIpV6(array $parameters): void
    {
        $this->parameter->string()->pattern('ipv6');
    }

    /**
     * @param array<string> $parameters
     */
    public function parseMacAddress(array $parameters): void
    {
        $this->parameter->string()->pattern('mac-address');
    }

    /**
     * @param array<string> $parameters
     */
    public function parseJson(array $parameters): void
    {
        $this->parameter->string()->pattern('json');
    }

    /**
     * @param array<string> $parameters
     */
    public function parseLt(array $parameters): void
    {
        $this->parameter->less(array_shift($parameters));
    }

    /**
     * @param array<string> $parameters
     */
    public function parseLte(array $parameters): void
    {
        $this->parameter->lessOrEquals(array_shift($parameters));
    }

    /**
     * @param array<string> $parameters
     */
    public function parseMax(array $parameters): void
    {
        $this->parameter->max((float)$parameters[0]);
    }

    /**
     * @param array<string> $parameters
     */
    public function parseMimetypes(array $parameters): void
    {
        $this->parameter->pattern('mimetypes:' . implode(', ', $parameters));
    }

    /**
     * @param array<string> $parameters
     */
    public function parseMimes(array $parameters): void
    {
        $this->parameter->pattern('mime:' . implode(', ', $parameters));
    }

    /**
     * @param array<string> $parameters
     */
    public function parseMin(array $parameters): void
    {
        $this->parameter->min((float)$parameters[0]);
    }

    /**
     * @param array<string> $parameters
     */
    public function parseMultipleOf(array $parameters): void
    {
        $this->parameter->multipleOf((float)$parameters[0]);
    }

    /**
     * @param array<string> $parameters
     */
    public function parseNotIn(array $parameters): void
    {
        $this->parameter->string()->pattern('not:' . implode(',', $parameters));
    }

    /**
     * @param array<string> $parameters
     */
    public function parseNotRegex(array $parameters): void
    {
        $this->parameter->string()->pattern('not-match:' . implode(',', $parameters));
    }

    /**
     * @param array<string> $parameters
     */
    public function parseNullable(array $parameters): void
    {
        $this->parameter->nullable();
    }

    /**
     * @param array<string> $parameters
     */
    public function parseNumeric(array $parameters): void
    {
        $this->parameter->number();
    }

    /**
     * @param array<string> $parameters
     */
    public function parsePassword(array $parameters): void
    {
        $this->parameter->password();
    }

    /**
     * @param array<string> $parameters
     */
    public function parsePresent(): void
    {
        $this->parameter->required()->nullable();
    }

    public function parseProhibited(): void
    {
        $this->parameter->description("The field under validation must be empty or not present.");
    }

    /**
     * @param array<string> $parameters
     */
    public function parseProhibitedIf(array $parameters): void
    {
        $this->parameter->if(array_shift($parameters), $parameters ?? [], 'prohibited');
    }

    /**
     * @param array<string> $parameters
     */
    public function parseProhibitedUnless(array $parameters): void
    {
        $this->parameter->unless(array_shift($parameters), $parameters ?? [], 'prohibited');
    }

    /**
     * @param array<string> $parameters
     */
    public function parseProhibits(array $parameters): void
    {
        $this->parameter->description(
            "If the field under validation is present, `" .
            implode('`, `', $parameters) .
            "` can't be present, even if empty."
        );
    }

    public function parseRegex(array $parameters): void
    {
        $this->_parseRegex($this->parameter, $parameters[0]);
    }

    /**
     * @param array<string> $parameters
     */
    public function parseRequired(array $parameters): void
    {
        $this->parameter->required();
    }

    /**
     * @param array<string> $parameters
     */
    public function parseRequiredIf(array $parameters): void
    {
        $this->parameter->if(array_shift($parameters), $parameters ?? [], 'required');
    }

    /**
     * @param array<string> $parameters
     */
    public function parseRequiredUnless(array $parameters): void
    {
        $this->parameter->unless(array_shift($parameters), $parameters ?? [], 'required');
    }

    /**
     * @param array<string> $parameters
     */
    public function parseRequiredWith(array $parameters): void
    {
        $this->parameter->with($parameters, 'required');
    }

    /**
     * @param array<string> $parameters
     */
    public function parseRequiredWithAll(array $parameters): void
    {
        $this->parameter->withAll($parameters, 'required');
    }

    /**
     * @param array<string> $parameters
     */
    public function parseRequiredWithout(array $parameters): void
    {
        $this->parameter->without($parameters, 'required');
    }

    /**
     * @param array<string> $parameters
     */
    public function parseRequiredWithoutAll(array $parameters): void
    {
        $this->parameter->withoutAll($parameters, 'required');
    }

    /**
     * @param array<string> $parameters
     */
    public function parseSame(array $parameters): void
    {
        $this->parameter->same(array_shift($parameters));
    }

    /**
     * @param array<string> $parameters
     */
    public function parseSize(array $parameters): void
    {
        match ($this->parameter->type) {
            Parameter::TYPE_ARRAY, Parameter::TYPE_STRING => $this->parameter->min((int)$parameters[0])->max((int)$parameters[0]),
            Parameter::TYPE_INTEGER, Parameter::TYPE_NUMBER => $this->parameter->min((float)$parameters[0])->max((float)$parameters[0]),
        };
    }

    /**
     * @param array<string> $parameters
     */
    public function parseSometimes(array $parameters): void
    {
        // TODO
    }

    /**
     * @param array<string> $parameters
     */
    public function parseStartsWith(array $parameters): void
    {
        $this->parameter->string()->pattern("starts-with:$parameters[0]");
    }

    /**
     * @param array<string> $parameters
     */
    public function parseString(array $parameters): void
    {
        $this->parameter->string();
    }

    /**
     * @param array<string> $parameters
     */
    public function parseTimezone(array $parameters): void
    {
        $this->parameter->string()->pattern('timezone');
    }

    /**
     * @param array<string> $parameters
     */
    public function parseUnique(array $parameters): void
    {
        // TODO
    }

    /**
     * @param array<string> $parameters
     */
    public function parseUrl(array $parameters): void
    {
        $this->parameter->string()->pattern('url');
    }

    public function parseUuid(): void
    {
        $this->parameter->uuid();
    }
}
