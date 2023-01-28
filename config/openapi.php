<?php

use Ark4ne\OpenApi\{Contracts, Documentation, Parsers};
use Illuminate\Http;
use Symfony\Component\HttpFoundation;

return [
    /*
    |------------------------------------------------------------------
    | Output-dir
    |------------------------------------------------------------------
    |
    | Output directory.
    |
    */
    'output-dir' => storage_path('app/public/openapi'),

    /*
    |--------------------------------------------------------------------------
    | Servers
    |--------------------------------------------------------------------------
    |
    | Here you can specify global server configuration.
    | You can define multiple server configuration.
    |
    | Any part of the server URL – scheme, host name or its parts, port, subpath
    | can be parameterized using variables.
    | Variables are indicated by {curly braces} in the server url.
    |
    | eg:
    |   [
    |       'url' => 'https://{env}.myapp.com',
    |       'description' =>  config('app.name'),
    |       'variable' => [
    |           'env' => [
    |               'enum' => ['development', 'staging', 'production'],
    |               'default' => config('app.env')
    |           ]
    |       ],
    |   ],
    */
    'servers' => [
        [
            'url' => config('app.url'),
            'description' =>  config('app.name'),
            'variable' => [
                'env' => [
                    'enum' => ['development', 'staging', 'production'],
                    'default' => config('app.env')
                ]
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Versions
    |--------------------------------------------------------------------------
    |
    | Here you can specify multiple configurations for each version of your API.
    |
    */
    'versions' => [
        'v1' => [
            /*
            |------------------------------------------------------------------
            | Servers
            |------------------------------------------------------------------
            |
            | Override global servers configuration.
            |
            */
            // 'servers' => [],

            /*
            |------------------------------------------------------------------
            | Output-file
            |------------------------------------------------------------------
            |
            | Output file name.
            |
            */
            'output-file' => 'openapi-v1.json',

            /*
            |------------------------------------------------------------------
            | Title
            |------------------------------------------------------------------
            |
            | Document Title.
            |
            */
            'title' => '',

            /*
            |------------------------------------------------------------------
            | Description
            |------------------------------------------------------------------
            |
            | Document description.
            |
            */
            'description' => '',

            /*
            |------------------------------------------------------------------
            | Routes
            |------------------------------------------------------------------
            |
            | Define which routes will be processed.
            | The pattern must be a shell mask.
            |
            */
            'routes' => [
                'api/*'
            ],

            /*
            |------------------------------------------------------------------
            | Name by
            |------------------------------------------------------------------
            |
            | Name your api routes.
            |
            | You can define name by according to :
            | (WIP) - description : The description block from action method.
            | - controller : The route controller class
            | - uri : The route uri
            | - name : The route name
            |
            | You will also need to define a regex that will retrieve the name
            | of the group.
            */
            'name-by' => [
                'by' => 'name', // 'description', 'controller', 'uri, 'name'
                'regex' => '/(.+)/'
            ],

            /*
            |------------------------------------------------------------------
            | Tag by
            |------------------------------------------------------------------
            |
            | Used to group API routes.
            | You can define a tag by according to :
            | - controller : The route controller class
            | - uri : The route uri
            | - name : The route name
            |
            | You will also need to define a regex that will retrieve the name
            | of the group.
            |
            */
            'tag-by' => [
                'by' => 'controller', // 'controller', 'uri, 'name'
                'regex' => '/^App\\\\Http\\\\(\w+)Controller/'
            ],

            /*
            |------------------------------------------------------------------
            | Group by (optional)
            |------------------------------------------------------------------
            |
            | Used to group Tags and create upper level.
            |
            | Use the openapi extension "x-tagGroups".
            |
            | You can define a group by according to :
            | - controller : The route controller class
            | - uri : The route uri
            | - name : The route name
            |
            | You will also need to define a regex that will retrieve the name
            | of the group.
            |
            */
            'group-by' => [
                'by' => 'controller', // 'controller', 'uri, 'name'
                'regex' => '/^App\\\\Http\\\\(\w+)/'
            ],

            /*
            |------------------------------------------------------------------
            | Ignore verbs
            |------------------------------------------------------------------
            |
            | HTTP verbs to ignore
            |
            */
            'ignore-verbs' => [
                'HEAD'
            ],

            /*
            |------------------------------------------------------------------
            | Parameters configuration render
            |------------------------------------------------------------------
            |
            | Parameters configuration render
            |
            */
            'parameters' => [
                'query' => [
                    /*
                    |----------------------------------------------------------
                    | Flat mode
                    |----------------------------------------------------------
                    |
                    | all: flat all parameters.
                    |      ex: [filter.*.name.*: string] => filter[][name][]: string
                    | last: flat last parameters.
                    |      ex: [filter.*.name.*: string] => filter[][name]: array<string>
                    | none:
                    |      ex: [filter.*.name.*: string] => filter: array<object{name:array<string>}>
                    */
                    'flat' => 'all'
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Parses
    |--------------------------------------------------------------------------
    |
    | Define all parser used for documentate a specific class.
    |
    | For each object, only one parser will be used.
    | For instance :
    | ```
    | class MyResourceCollection extends ResourceCollection {}
    | ```
    | ResourceCollectionParser and JsonResourceParser are eligible, because
    | MyResourceCollection extends from ResourceCollection which extends from JsonResource.
    |
    | The order of the parsers will define which parser we will use:
    | 1st eligible => parser used.
    |
    | For MyResourceCollection we will therefore use ResourceCollection.
    |
    */
    'parsers' => [
        /*
        | Requests
        */
        'requests' => [
            Contracts\Documentation\DescribableRequest::class => Parsers\Requests\DescribedRequestParser::class,
            Http\Request::class => Parsers\Requests\RequestParser::class,
        ],

        /*
        | Requests Rules
        */
        'rules' => [
            /*
            | JsonApi Rules
            */
            Ark4ne\JsonApi\Requests\Rules\Includes::class => Parsers\Rules\IncludesRuleParsers::class,
            Ark4ne\JsonApi\Requests\Rules\Fields::class => Parsers\Rules\FieldsRuleParsers::class
        ],

        'responses' => [
            /*
            | JsonApi Resources & Collections
            */
            Ark4ne\JsonApi\Resources\JsonApiCollection::class => Parsers\Responses\JsonApiCollectionParser::class,
            Ark4ne\JsonApi\Resources\JsonApiResource::class => Parsers\Responses\JsonApiResourceParser::class,

            /*
            | Laravel Responses
            */
            Http\Resources\Json\ResourceCollection::class => Parsers\Responses\ResourceCollectionParser::class,
            Http\Resources\Json\JsonResource::class => Parsers\Responses\JsonResourceParser::class,
            Http\JsonResponse::class => Parsers\Responses\JsonResponseParser::class,
            Http\Response::class => Parsers\Responses\ResponseParser::class,

            /*
            | Symfony Responses
            */
            HttpFoundation\BinaryFileResponse::class => Parsers\Responses\BinaryFileResponseParser::class,
            HttpFoundation\StreamedResponse::class => Parsers\Responses\StreamedResponseParser::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Middlewares
    |--------------------------------------------------------------------------
    |
    | Apply transformers for given middlewares.
    |
    */
    'middlewares' => [
        'auth:sanctum' => [
            \Ark4ne\OpenApi\Transformers\Middlewares\ApplyBearerTokenSecurity::class,
            \Ark4ne\OpenApi\Transformers\Middlewares\ApplyXsrfSecurity::class,
        ],
        \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class => [
            \Ark4ne\OpenApi\Transformers\Middlewares\ApplyCsrfSecurity::class,
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Format
    |--------------------------------------------------------------------------
    |
    | Defines how date formats will be understood.
    |
    */
    'format' => [
        'date' => [
            'Y-m-d' => Documentation\Request\Parameter::FORMAT_DATE,
            'Y-m-d H:i:s' => Documentation\Request\Parameter::FORMAT_DATETIME,
            DateTimeInterface::ATOM => Documentation\Request\Parameter::FORMAT_DATETIME,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Connections
    |--------------------------------------------------------------------------
    |
    | Defines whether to use transactions on different database connections.
    |
    | Using transaction will create and save models via factories. The saved
    | models will be deleted at the end of the generation via a rollback.
    |
    | Without transactions, models will be created but not saved, which can
    | lead to errors during generation.
    |
    */
    'connections' => [
        'use-transaction' => true
    ],

    /*
    |--------------------------------------------------------------------------
    | Languages
    |--------------------------------------------------------------------------
    |
    | Defines in which languages the documentation will be generated.
    |
    */
    'languages' => [
        // 'en',
    ]
];
