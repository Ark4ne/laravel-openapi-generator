<?php

use Ark4ne\OpenApi\{Contracts, Documentation, Parsers};
use Illuminate\Http;
use Symfony\Component\HttpFoundation;

return [
    'output-dir' => storage_path('app/public/openapi'),
    'versions' => [
        'v1' => [
            'output-file' => 'openapi-v1.json',

            'title' => '',

            'description' => '',

            'routes' => [
                'api/*'
            ],
        ],
    ],

    'parsers' => [
        /*
         * Requests
         */
        'requests' => [
            Contracts\Documentation\DescribableRequest::class => Parsers\Requests\DescribedRequestParser::class,
            Http\Request::class => Parsers\Requests\RequestParser::class,
        ],

        /*
         * Requests Rules
         */
        'rules' => [
            // TODO

            // Ark4ne\JsonApi\Support\Includes::class => OpenApi\__Custom\Parsers\Requests\Rules\RuleInclude::class
        ],

        'responses' => [
            /*
             * Laravel Responses
             */
            Http\Resources\Json\ResourceCollection::class => Parsers\Responses\ResourceCollectionParser::class,
            Http\Resources\Json\JsonResource::class => Parsers\Responses\JsonResourceParser::class,
            Http\JsonResponse::class => Parsers\Responses\JsonResponseParser::class,
            Http\Response::class => Parsers\Responses\ResponseParser::class,

            /*
             * Symfony Responses
             */
            HttpFoundation\BinaryFileResponse::class => Parsers\Responses\BinaryFileResponseParser::class,
            HttpFoundation\StreamedResponse::class => Parsers\Responses\StreamedResponseParser::class,
        ],
    ],

    'format' => [
        'date' => [
            'Y-m-d' => Documentation\Request\Parameter::FORMAT_DATE,
            'Y-m-d H:i:s' => Documentation\Request\Parameter::FORMAT_DATETIME,
            DateTimeInterface::ATOM => Documentation\Request\Parameter::FORMAT_DATETIME,
        ],
    ],
];
