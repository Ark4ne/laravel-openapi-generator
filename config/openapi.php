<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Documents
    |--------------------------------------------------------------------------
    |
    | One entry per OpenAPI document to generate. The key is the document
    | name: `php artisan openapi:generate --document=v1`, `{document}` in the
    | output file name.
    |
    */
    'documents' => [
        'v1' => [
            /*
            | OpenAPI version written: 3.0.0 → 3.0.4, 3.1.0 → 3.1.2 or 3.2.0.
            | The features a version cannot express are converted when
            | possible, otherwise rejected (`strict`) or dropped with a warning.
            */
            'openapi' => '3.1.2',
            'strict' => true,

            'info' => [
                'title' => env('APP_NAME', 'API'),
                'version' => '1.0.0',
                'summary' => null,
                'description' => null,
                'terms_of_service' => null,
                'contact' => [
                    // 'name' => '', 'url' => '', 'email' => '',
                ],
                'license' => [
                    // 'name' => 'MIT', 'identifier' => 'MIT',
                ],
            ],

            /*
            | Public URI of the generated document (`$self`, written from 3.2)
            | and its external documentation.
            */
            'self' => null,
            'external_docs' => null, // ['url' => 'https://…', 'description' => '…']

            /*
            | Servers of the document; the global `servers` when empty.
            */
            'servers' => [],

            /*
            | Routes of the document: shell masks on the URI.
            */
            'routes' => [
                'include' => ['api/*'],
                'exclude' => [],
                'ignored_methods' => ['HEAD'],
            ],

            /*
            | Naming strategies. `by` is a built-in strategy or a class
            | implementing the matching interface of
            | Ark4ne\OpenApi\Extraction\Strategies.
            |
            | summary:      doc | name | uri | controller
            | tags:         controller | uri | name (with an optional `pattern`)
            | groups:       null, or like tags (`Tag.parent` in 3.2, `x-tagGroups` before)
            | operation_id: name | action | method_path
            | query:        all | last | none (how nested query fields are written)
            */
            'strategies' => [
                'summary' => ['by' => 'doc'],
                'tags' => ['by' => 'controller', 'pattern' => '/^App\\\\Http\\\\Controllers\\\\(.+)Controller$/'],
                'groups' => null,
                'operation_id' => ['by' => 'name'],
                'query' => ['by' => 'all'],
            ],

            /*
            | Security schemes completing or replacing those inferred from
            | the middlewares: OAuth2 flows and URLs (Passport), OpenID
            | Connect, and the 3.2 fields (deviceAuthorization flow,
            | oauth2_metadata_url, deprecated).
            |
            | 'passport' => ['type' => 'oauth2', 'flows' => ['authorizationCode' => [
            |     'authorizationUrl' => '/oauth/authorize', 'tokenUrl' => '/oauth/token']]],
            | 'oidc' => ['type' => 'openIdConnect', 'open_id_connect_url' => 'https://…'],
            */
            'security_schemes' => [],

            /*
            | Resources and enums as `components.schemas` referenced by `$ref`
            | (true), or written inline (false).
            */
            'use_references' => true,

            /*
            | Overrides of the global `output`.
            */
            'output' => [
                'file' => 'openapi-{document}.json',
            ],

            /*
            | `x-*` extensions of the document.
            */
            'extensions' => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Servers
    |--------------------------------------------------------------------------
    |
    | Default servers of every document. Parts of the URL can be variables:
    | `https://{env}.example.com` with `'variables' => ['env' => ['default' =>
    | 'production', 'enum' => ['staging', 'production']]]`.
    |
    */
    'servers' => [
        [
            'url' => env('APP_URL', 'http://localhost'),
            'description' => env('APP_NAME'),
            'name' => null, // written from 3.2
            'variables' => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Output
    |--------------------------------------------------------------------------
    |
    | Disk (null: the default disk), directory and file of the documents.
    | `{document}` and `{locale}` are replaced in the file name. The format is
    | `json` or `yaml` (needs symfony/yaml).
    |
    */
    'output' => [
        'disk' => null,
        'directory' => 'openapi',
        'file' => '{document}.json',
        'format' => 'json',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sampling
    |--------------------------------------------------------------------------
    |
    | Resources are documented by running them on sampled models. With
    | `transactions`, models are created by their factories inside a
    | transaction rolled back at the end of the generation, on the connections
    | whose driver is listed; otherwise they are only made, never saved.
    |
    */
    'sampling' => [
        'transactions' => true,
        'connections' => ['mariadb', 'mysql', 'pgsql', 'sqlite', 'sqlsrv'],
        'collection_size' => 2,
        'seed' => 1,
        'examples' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Date formats
    |--------------------------------------------------------------------------
    |
    | PHP date formats of the validation rules → OpenAPI string formats.
    |
    */
    'date_formats' => [
        'Y-m-d' => 'date',
        'Y-m-d H:i:s' => 'date-time',
        DateTimeInterface::ATOM => 'date-time',
    ],

    /*
    |--------------------------------------------------------------------------
    | Middlewares
    |--------------------------------------------------------------------------
    |
    | Middleware (name or class) → middleware mappers adding security, headers
    | or error responses to the operations that use it. Merged with the
    | built-in mappers (auth, auth:sanctum, signed, throttle, can…).
    |
    */
    'middlewares' => [],

    /*
    |--------------------------------------------------------------------------
    | Extensions
    |--------------------------------------------------------------------------
    |
    | Classes added to the registries of the generator, resolved through the
    | container. Each one declares its priority.
    |
    */
    'extensions' => [
        'operation_extractors' => [],
        'request_extractors' => [],
        'rule_mappers' => [],
        'response_extractors' => [],
        'samplers' => [],
        'passes' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Languages
    |--------------------------------------------------------------------------
    |
    | One document per locale; summaries, descriptions and tags may be
    | translation keys. Empty: one document in the application locale.
    |
    */
    'languages' => [],
];
