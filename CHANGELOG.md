# Changelog

All notable changes to this project are documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- Full rewrite for 1.0.0 (see `docs/`).
- Requires PHP 8.3+ and Laravel 13.
- Writes documents with `ark4ne/openapi-writer` instead of `goldspecdigital/oooas`.

### Removed

- `goldspecdigital/oooas`, `phpdocumentor/reflection-docblock`, `phpdocumentor/type-resolver` and
  `justinrainbow/json-schema` dependencies.
- Support for PHP < 8.3 and Laravel < 13.
