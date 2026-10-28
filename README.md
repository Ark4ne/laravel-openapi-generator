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

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).

## License

See [LICENSE](LICENSE).
