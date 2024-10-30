<?php

namespace Ark4ne\OpenApi\Parsers\Requests\Concerns;

use Ark4ne\OpenApi\Documentation\Request\Parameter;

trait RegexParser
{
    public function parseRegex(Parameter $param, string $regex): static
    {
        $common = [
            '[a-zA-Z]+' => [['string'], ['pattern', 'alpha']],
            '[a-zA-Z0-9]+' => [['string'], ['pattern', 'alpha-numeric']],
            '[a-zA-Z\d]+' => [['string'], ['pattern', 'alpha-numeric']],
            '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}' => [['uuid']],
            '[\da-fA-F]{8}-[\da-fA-F]{4}-[\da-fA-F]{4}-[\da-fA-F]{4}-[\da-fA-F]{12}' => [['uuid']],
        ];

        foreach ($common as $pattern => $candidate) {
            if ($this->compare($pattern, $regex)) {
                $format = $candidate;
            }
        }

        if (isset($format)) {
            foreach ($format as $ins) {
                $param->{array_shift($ins)}(...$ins);
            }


            return $this;
        }

        $numericPattern = ['[0-9]+', '\d+', '[\d]+'];

        foreach ($numericPattern as $pattern) {
            if ($this->compare($pattern, $regex)) {
                $param->int();
                return $this;
            }
        }

        foreach ($numericPattern as $partOne) {
            foreach ($numericPattern as $partTwo) {
                if ($this->compare("$partOne\.$partTwo", $regex)) {
                    $param->float();
                    return $this;
                }
            }
        }

        $param->string();

        if (preg_match('/^[\w-]+(?:\|[\w-]+)+$/', $regex)) {
            $param->enum(explode("|", $regex));
        } elseif (preg_match('/^(?<delimiter>.)(?<pattern>(?:\s|.)+)\k<delimiter>(?<modifier>[gmiuxsUAJD]+)?$/', $regex, $matches)) {
            $param->pattern("regex:" . $matches['delimiter'] . $matches['pattern'] . $matches['delimiter'] . ($matches['modifier'] ?? ''));
        } else {
            $param->pattern("regex:" . ($regex[0] === $regex[strlen($regex) - 1]) ? $regex : "/$regex/");
        }

        return $this;
    }

    private function compare(string $pattern, string $regex): bool
    {
        return $pattern === $regex || $pattern === substr($regex, 1, -1);
    }
}
