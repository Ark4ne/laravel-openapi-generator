# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

`ark4ne/laravel-openapi-generator` is a **Laravel package** that introspects an application (routes, controllers,
form requests, validation rules, resources) and writes OpenAPI documents via `php artisan openapi:generate`.

- **Architecture, patterns and conventions: `docs/architecture.md` — read it before writing any code.**
- Migration from 0.6: `UPGRADE.md` and the agent skill `skills/laravel-openapi-generator-upgrade/`. The 0.6 code lives
  on branch `v0.6` (`git show v0.6:<path>`); it is a functional reference only — never copy it as is.
- OpenAPI objects are built **only** with `ark4ne/openapi-writer`. Its public API is described by its agent skill:
  `vendor/ark4ne/openapi-writer/skills/openapi-writer/SKILL.md`. Read it before using the writer. Never add OpenAPI
  model classes to this package: writer changes go to the writer project.

Targets: PHP `^8.3`, Laravel `^13.0` (Laravel 14 as soon as it is released). Nothing obsolete or unmaintained.

## Commands

```bash
composer test            # whole PHPUnit suite (Unit, Feature, Architecture)
composer test:coverage   # coverage, fails under 90% of lines
composer stan            # PHPStan max + Larastan + phpat architecture rules
composer cs              # code style check (PER-CS); composer cs:fix to fix
composer fixtures:update # rewrite expected documents from the current output (review the diff)
composer benchmark       # a document of 500 routes: time and peak memory
composer ci              # every blocking check except coverage and mutation

vendor/bin/phpunit --filter test_name
```

## Workflow

Every change follows the same cycle: **contract** (signatures and PHPDoc, neutral bodies) → **red tests** →
**implementation**. Never implement behaviour that has no test yet.

Every public change is documented in `CHANGELOG.md`, in the agent skills (`skills/`) and, when it introduces a new
rule, in `docs/architecture.md`.

## Architecture (details in `docs/architecture.md`)

The generator **observes** the application, produces a neutral **description**, **passes** complete it, and the
writer **writes**.

- Layers, each depending only on those to its left: `Support` ← `Types` ← `Attributes` ← `Configuration` ←
  `Description` ← `Context` ← `Discovery` ← `Sampling` ← `Extraction` ← `Generation` ← `Output` ←
  `Integrations` ← `Laravel` (`Exceptions` usable everywhere). Enforced by `phpat`.
- Only `src/Laravel/` may use facades, global helpers (`config()`, `app()`, `request()`…) or the container; other
  layers get dependencies through constructors and Laravel **contracts**. Enforced by `disallowed-calls`.
- A `GenerationContext` (document configuration, `Diagnostics`, `ComponentRegistry`, locale) is passed
  explicitly; no static state, no singletons.
- Description objects (`src/Description/`) are `final readonly`, immutable, with named constructors, a replacing
  setter and an `add*` appender per collection. Schemas are writer `Schema` objects from extraction onwards.
- Extension points are interfaces with priority-ordered registries: `OperationExtractor`, `RequestExtractor`,
  `RuleMapper` (one class per validation rule), `ResponseExtractor`, `Sampler`, `Pass`, naming strategies.
- An extractor never throws for application code it does not understand: it adds a diagnostic.
- Classes are `final`, no traits in `src/`, `declare(strict_types=1)`, PHPStan max.

## Tests

- `tests/Unit/`: one test per rule mapper, extractor, sampler, pass, strategy.
- `tests/Feature/`: Orchestra Testbench scenarios, each with its fixture app in `tests/Fixtures/` and expected
  documents `tests/Feature/<Scenario>/expected/<3.0|3.1|3.2>.json`. Every generated document is validated with the
  writer's `Validator` before comparison.
- Tests use SQLite in memory. Test names are sentences (`test_max_rule_on_string_sets_max_length`).

## Language

Everything is in English, `docs/` included.
