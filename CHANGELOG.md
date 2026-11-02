# Changelog

All notable changes to this project are documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.1] - 2026-11-02

### Fixed

- `#[Response]`, `#[QueryParameter]`, `#[Header]` and `#[Cookie]` on a controller class were ignored: they now apply
  to each of its actions: the action replaces a response of the same status and completes a parameter of the same
  name; a success declared or inferred for the action leaves out the successes of the controller for other statuses
  ([#44](https://github.com/Ark4ne/laravel-openapi-generator/issues/44)).
- A `#[Response]` without body replaced the error body of the `@throws` of its status: it now keeps it.
- `#[QueryParameter]`, `#[Header]` and `#[Cookie]` replaced the parameter of the same name given by the rules (or by
  an attribute of the controller or the request): they now complete it. `type` replaces the schema, and `required` and
  `deprecated` are `?bool`, `null` keeping the value already described
  ([#46](https://github.com/Ark4ne/laravel-openapi-generator/issues/46)).
- A header declared again in another case (`x-tenant` after `X-Tenant`, or the `X-CSRF-TOKEN` of the `csrf`
  middleware) was a second parameter: header names are case-insensitive, so it now completes the first one, under its
  first name. Its schema replaces the first one, its description, style and explode too when it has them, and the
  header is required or deprecated when either declaration is
  ([#46](https://github.com/Ark4ne/laravel-openapi-generator/issues/46)).
- A query parameter folded into a `querystring` (3.2) lost its deprecation: the property now keeps it. An attribute
  completing such a property keeps the place of its name in `required`
  ([#46](https://github.com/Ark4ne/laravel-openapi-generator/issues/46)).

## [1.0.0] - 2026-10-29

A rewrite: see [UPGRADE.md](UPGRADE.md) to migrate from 0.6.

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
- Agent skills: `skills/laravel-openapi-generator/` (usage) and `skills/laravel-openapi-generator-upgrade/`
  (migration from 0.6).

### Changed

- Requires PHP 8.3+ and Laravel 13.
- Writes documents with `ark4ne/openapi-writer` instead of `goldspecdigital/oooas`.
- Configuration: `versions` becomes `documents`, keys in `snake_case`, every key validated when a command runs.
- `operationId` comes from a strategy: the published configuration uses `method_name`, which keeps the 0.6
  identifiers (`GET:users.index`); `name` (the default when the key is missing), `action` and `method_path` write
  unique identifiers (`users.index`).
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

[Unreleased]: https://github.com/Ark4ne/laravel-openapi-generator/compare/v1.0.1...HEAD
[1.0.1]: https://github.com/Ark4ne/laravel-openapi-generator/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/Ark4ne/laravel-openapi-generator/compare/v0.6.1...v1.0.0
