<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Context;

/**
 * Step of the generation that reports a diagnostic.
 */
enum Stage: string
{
    case Configuration = 'configuration';
    case Discovery = 'discovery';
    case Operation = 'operation';
    case Request = 'request';
    case Response = 'response';
    case Sampling = 'sampling';
    case Pass = 'pass';
    case Writing = 'writing';
    case Validation = 'validation';
}
