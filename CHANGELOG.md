# Changelog

All notable changes to this project are documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

1.0.0, a rewrite: see [UPGRADE.md](UPGRADE.md) to migrate from 0.6. This section becomes `## [1.0.0] - <date>` when
the version is tagged.

### Added

- OpenAPI 3.0.0 → 3.0.4, 3.1.0 → 3.1.2 and 3.2.0, chosen per document (`documents.<name>.openapi`), strict or
  lenient, with the `redoc` vendor extensions (`x-webhooks`, `x-tagGroups`) for older versions.
- PHP attributes: `#[Operation]`, `#[Tag]`, `#[Response]` (repeatable, on actions and exception classes),
  `#[RequestBody]`, `#[QueryParameter]`, `#[PathParameter]`, `#[Header]`, `#[Cookie]`, `#[Security]`, `#[Exclude]`,
  `#[Webhook]`, `#[Callback]`, `#[ResourceFactory]`, `#[SchemaName]`.
- Responses inferred from the return types (resources, collections, paginators, models, array shapes, unions with a
  discriminator, files, streams, server-sent events, redirects, views), resources described by running them on
  sampled models inside a rolled back transaction.
- Error responses: 401, 403, 404, 419, 422 and 429 from the middlewares, the bindings and the validation, and from
  the `@throws` of the actions (`exceptions.read_throws`, `exceptions.map`).
- Security schemes from the middlewares (Sanctum, Passport, basic, signed URLs, abilities and scopes) and
  `security_schemes`.
- Validation rules of Laravel 13 mapped to schema constraints, one `RuleMapper` per rule; `DescribesRule` and
  `DescribesRequest` for rules and requests of the application.
- Webhooks and callbacks.
- JSON:API integration (`ark4ne/laravel-json-api` ^1.9): resource objects, `included`, `include` and `fields[type]`
  parameters, optional JSON:API error documents (`integrations.json_api.errors`).
- Extension points built through the container: operation, request and response extractors, rule mappers,
  samplers, passes, middleware mappers, naming strategies; container tags for packages.
- `openapi:generate` (`--document`, `--strict`, `--dry-run`, `--format`, `--stdout`, documented exit codes) and
  `openapi:inspect {route}`; diagnostics grouped by route; `about` entry.
- One document per language (`languages`), JSON or YAML output, `{document}` and `{locale}` in the file name.
- An agent skill in `skills/laravel-openapi-generator/`.

### Changed

- Requires PHP 8.3+ and Laravel 13.
- Writes documents with `ark4ne/openapi-writer` instead of `goldspecdigital/oooas`.
- Configuration: `versions` becomes `documents`, keys in `snake_case`, every key validated when a command runs.
- `operationId` is unique (`users.index`) instead of `"<method>:<route name>"`.
- Components are named after their class (`User`), not `resource-<hash>-user-resource`.
- PHPDoc types are read with `phpstan/phpdoc-parser` (generics, array shapes, conditional types).

### Removed

- The `@oa-*` PHPDoc tags, replaced by attributes.
- The `parsers` configuration and the middleware transformers, replaced by the extension points.
- `goldspecdigital/oooas`, `phpdocumentor/reflection-docblock`, `phpdocumentor/type-resolver` and
  `justinrainbow/json-schema` dependencies.
- Support for PHP < 8.3 and Laravel < 13.

### Fixed

- `#[Response(statusText: 'Created')]` wrote a 200; the 422 had no schema; optional path parameters (`{id?}`) were
  ignored; state was shared between the documents of one run.
