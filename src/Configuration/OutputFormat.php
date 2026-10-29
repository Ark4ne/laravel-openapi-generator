<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Configuration;

enum OutputFormat: string
{
    case Json = 'json';
    case Yaml = 'yaml';
}
