Laravel OpenAPI Generator
=========================

Generates OpenAPI 3.0, 3.1 and 3.2 documents from a Laravel application: routes, controllers, form requests,
validation rules and API resources.

> **Status: V1 rewrite in progress** on the `v1.0` branch. Nothing here is usable yet. For the current release,
> see the [`v0.6` branch](https://github.com/Ark4ne/laravel-openapi-generator/tree/v0.6).
> The roadmap and its progress are in [`docs/`](docs/README.md) (written in French).

## Requirements

- PHP 8.3+
- Laravel 13
- `ark4ne/openapi-writer` 1.0, which writes the documents

Optional:

- `ark4ne/laravel-json-api` ^1.9 to document JSON:API resources
- `symfony/yaml` to write YAML
- `opis/json-schema` to validate the generated documents against the official OpenAPI schemas

## What changes in V1

- `goldspecdigital/oooas` (unmaintained, OpenAPI 3.0 only) is replaced by `ark4ne/openapi-writer`: the OpenAPI
  version is chosen per document (3.0.x, 3.1.x or 3.2.0, 3.1.2 by default).
- Routes, requests, rules and responses are read by priority-ordered extractors that applications can extend.
- PHP attributes replace the `@oa-*` PHPDoc tags.
- The `versions` configuration key becomes `documents`.

An upgrade guide (`UPGRADE.md`) will ship with 1.0.0.

## Installation

```bash
composer require ark4ne/laravel-openapi-generator
php artisan vendor:publish --tag=openapi-config
php artisan openapi:generate
```

The service provider is discovered automatically. `php artisan about` shows the version, the documents and whether
the JSON:API integration is enabled.

## Commands

| Command | What it does |
| --- | --- |
| `openapi:generate` | Generates every document of the configuration, in each language |
| `openapi:inspect {route}` | Shows what the generator understood of a route (name, URI, or method and URI) and its diagnostics |

`openapi:generate` options:

| Option | Effect |
| --- | --- |
| `--document=*` | Documents to generate (all by default) |
| `--strict` | Fails on the first warning; the document is not written |
| `--dry-run` | Generates and validates, without writing |
| `--format=` | `json` or `yaml`, instead of the configured format (the file takes the extension of the format) |
| `--stdout` | Prints the document instead of writing it (one document; the diagnostics go to the error output) |

The diagnostics are grouped by route: the warnings by default, the infos with `-v`; `-q` keeps the errors only.

Exit codes: `0` every document is written, `1` a document has an error (or a warning with `--strict`) and is not
written, `2` the configuration or the options are invalid.

## Configuration

Every key of `config/openapi.php` is read when a command runs; an invalid key or value fails the command with the key,
the expected value and the received one (`openapi.documents.v1.openapi: expected one of 3.0.0, …, got "4.0"`).

| Key | Default | Meaning |
| --- | --- | --- |
| `documents.<name>` | `v1` | One OpenAPI document; the name is used by `--document` and `{document}` |
| `documents.<name>.openapi` | `3.1.2` | Version written: `3.0.0` → `3.0.4`, `3.1.0` → `3.1.2`, `3.2.0` |
| `documents.<name>.strict` | `true` | Rejects what the version cannot write (`false`: dropped with a warning) |
| `documents.<name>.vendor_extensions` | `none` | `redoc`: `x-webhooks` before 3.1, `x-tagGroups` before 3.2 |
| `documents.<name>.info` | | `title`, `version`, `summary`, `description`, `terms_of_service`, `contact`, `license` |
| `documents.<name>.self` | `null` | Public URI of the document (`$self`, 3.2) |
| `documents.<name>.external_docs` | `null` | `url` and `description` |
| `documents.<name>.servers` | `[]` | Servers of the document; the global `servers` when empty |
| `documents.<name>.routes` | `api/*` | `include`, `exclude` (masks on `uri`, `name`, `domain`, `middleware`), `ignored_methods`, `internal`, `order` (`registration` or `path`) |
| `documents.<name>.strategies` | | `summary`, `tags`, `groups`, `operation_id`, `query`: a built-in strategy (`by`) or a class implementing its interface, built through the container |
| `documents.<name>.security_schemes` | `[]` | Security Scheme Objects completing or replacing those of the middlewares |
| `documents.<name>.security` | `[]` | Schemes required by default on every operation |
| `documents.<name>.use_references` | `true` | Resources and enums as components referenced by `$ref` |
| `documents.<name>.output` | | Overrides of the global `output` |
| `documents.<name>.extensions` | `[]` | `x-*` extensions of the document |
| `servers` | `APP_URL` | Default servers: `url`, `description`, `name` (3.2), `variables` |
| `output` | | `disk` (`null`: the default disk), `directory`, `file` (`{document}`, `{locale}`), `format` (`json` or `yaml`) |
| `sampling` | | `transactions`, `connections` (drivers), `collection_size`, `seed`, `examples` |
| `date_formats` | | PHP date format → OpenAPI format of the validation rules |
| `middlewares` | `[]` | Middleware (name or class) → classes implementing `MiddlewareMapper` |
| `extensions` | `[]` | `operation_extractors`, `request_extractors`, `rule_mappers`, `response_extractors`, `samplers`, `passes`: classes resolved through the container |
| `integrations.json_api.errors` | `false` | Error responses as JSON:API error documents (with `ark4ne/laravel-json-api`) |
| `exceptions` | | `read_throws` (the `@throws` of the actions), `map` (exception class → status) |
| `languages` | `[]` | One document per locale; texts may be translation keys |

Third-party packages add extensions with container tags: `openapi.operation-extractors`,
`openapi.request-extractors`, `openapi.rule-mappers`, `openapi.response-extractors`, `openapi.samplers`,
`openapi.passes`.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).

## License

See [LICENSE](LICENSE).
