---
name: laravel-openapi-generator-upgrade
description: Migrate a Laravel application from ark4ne/laravel-openapi-generator 0.6 to 1.0 (configuration, @oa-* PHPDoc tags to attributes, #[Response], #[Id], DescribableRequest, custom rule parsers, response parsers, middleware transformers, translation keys) and check the regenerated OpenAPI document. Use when composer.json requires ark4ne/laravel-openapi-generator below 1.0, when config/openapi.php has a versions key, when code uses @oa- tags or Ark4ne\OpenApi\Contracts, or when asked to upgrade that package.
---

# Upgrading ark4ne/laravel-openapi-generator from 0.6 to 1.0

1.0 is a rewrite. The configuration file, the `@oa-*` PHPDoc tags, the parser and transformer classes and the
`DescribableRequest` contract of 0.6 are gone; attributes, extractors and mappers replace them. 1.0 rejects a 0.6
configuration loudly but **silently ignores a leftover `@oa-*` tag**: the migration is done when nothing of 0.6 is
left, not when the command runs.

Files of this skill:

- [mapping.md](mapping.md): every 0.6 key, tag, attribute, class and translation key, and what replaces it; what
  changes in the generated document.
- [examples.md](examples.md): 0.6 code next to its 1.0 port, tested (tags and `#[Response]`, `DescribableRequest`,
  rule parsers, middleware transformers, translation keys).

For how 1.0 works (every attribute, configuration key and extension point), use the `laravel-openapi-generator`
skill: `vendor/ark4ne/laravel-openapi-generator/skills/laravel-openapi-generator/`. The package's `UPGRADE.md` is the
human version of this skill.

## Procedure

Work through the steps in order and keep the application test suite green after each one.

### 1. Baseline (still on 0.6)

```bash
php artisan openapi:generate
```

Copy the generated documents out of the output directory (`output-dir` on `output-disk`, one file per version and
language): they are the reference for step 9. Note the PHP and Laravel versions: 1.0 needs PHP 8.3+ and Laravel 13;
upgrade them first if needed, as a separate change.

### 2. Inventory

List everything to port before changing anything:

```bash
grep -rn "@oa-" app/ routes/                                   # PHPDoc tags
grep -rln "Ark4ne\\\\OpenApi" app/ config/ routes/ bootstrap/  # every file using the package
grep -rn "DescribableRequest\|CustomRuleParser\|RequestParserContract\|ResponseParserContract\|Transformer" app/
grep -rn "#\[\(Response\|Id\|ResourceFactory\|Request\)\b" app/
grep -rn "openapi\.requests\." lang/ resources/lang/ 2>/dev/null  # translation keys of 0.6
```

Read `config/openapi.php`: note every key that differs from the 0.6 defaults, every entry of `parsers` and
`middlewares` that is not a built-in class of `Ark4ne\OpenApi\…`.

### 3. Upgrade the package

```bash
composer require ark4ne/laravel-openapi-generator:^1.0
mv config/openapi.php config/openapi.v06.php
php artisan vendor:publish --tag=openapi-config
composer require --dev opis/json-schema   # validation against the official OpenAPI schemas
```

### 4. Configuration

Carry each value of `config/openapi.v06.php` to `config/openapi.php` with [mapping.md](mapping.md#configuration),
then delete `config/openapi.v06.php`. Edit the published file rather than writing a new one: it documents every key.
Decide the summary strategy now ([Summaries and descriptions](mapping.md#summaries-and-descriptions)): `doc` (the
1.0 default) is recommended. Check with:

```bash
php artisan openapi:generate --dry-run
```

Exit code 2 means an invalid key or value: the message names the key, the expected value and the received one.

### 5. PHPDoc tags and attributes

Replace every `@oa-*` tag, every 0.6 `#[Response]` argument, `#[Id]` and `#[Request]`
([mapping.md](mapping.md#phpdoc-tags), [example](examples.md#phpdoc-tags-and-response-attributes)). Then the first
`grep` of step 2 must return nothing.

### 6. Requests, rules and responses

Port, in this order, each class found in step 2 ([mapping.md](mapping.md#classes-and-contracts)):

1. `DescribableRequest` → `DescribesRequest` ([example](examples.md#described-requests)); remove from `describe()`
   what `rules()` already gives.
2. Rule parsers → `DescribesRule` on rules of the application, `RuleMapper` for rules of packages
   ([example](examples.md#custom-rule-parsers)).
3. Request and response parsers → `RequestExtractor` and `ResponseExtractor`. First check whether 1.0 already
   handles the case without them (it reads far more than 0.6: unions, array shapes, paginators, files, streams,
   `Responsable`): delete the parser, regenerate, and port it only if the output is worse.
4. Register what remains in `extensions` of `config/openapi.php`.

### 7. Middlewares

Delete the built-in transformers from `middlewares`; port the others to `MiddlewareMapper`
([example](examples.md#middleware-transformers)), or replace them by `security_schemes` and `#[Security]`.

### 8. Translations

If `languages` is set and the lang files use the `openapi.requests.*` keys, port them
([mapping.md](mapping.md#translations), [example](examples.md#translation-keys-of-06)).

### 9. Regenerate and compare

```bash
php artisan openapi:generate -v
```

1. Read every diagnostic. Fix the cause in the code (a return type, a rule, an attribute), not in the document.
2. Compare the operations with the baseline: the same routes must be documented. With `jq`:

   ```bash
   jq -r '.paths | to_entries[] | .key as $p | .value | keys[] | select(test("^(get|put|post|delete|patch|options|head|query)$")) | "\(.) \($p)"' baseline.json | sort > before.txt
   jq -r '.paths | to_entries[] | .key as $p | .value | keys[] | select(test("^(get|put|post|delete|patch|options|head|query)$")) | "\(.) \($p)"' openapi/openapi-v1.json | sort > after.txt
   diff before.txt after.txt
   ```

   A missing route: `php artisan openapi:inspect <route-name>` says why (filter, exclusion, internal route).
3. For each operation that differs, `php artisan openapi:inspect <route-name>` shows what 1.0 understood. Classify
   each difference: expected ([mapping.md](mapping.md#what-changes-in-the-generated-document)), an improvement, or a
   regression to fix with an attribute.
4. Validate: `php artisan openapi:generate --strict --dry-run` must succeed; add it to the CI.

### 10. Clean up

- `grep -rn "Ark4ne\\\\OpenApi" app/` lists only 1.0 classes (`Attributes\…`, `Extraction\…`, `Generation\…`,
  `Description\…`, `Context\…`): no `Contracts\`, `Descriptors\`, `Documentation\`, `Parsers\`, `Transformers\`,
  `Support\` namespace remains.
- No `@oa-` tag remains; `goldspecdigital/oooas` is gone from `composer.json` unless the application uses it.
- Clients generated from the document: `operationId` values changed (`users.index` instead of `GET:users.index`);
  warn their owners, or keep the old ids with `#[Operation(operationId: 'GET:users.index')]` where it matters.

## Rules

1. **Never keep a 0.6 construct "for later"**: 1.0 ignores `@oa-*` tags without a word, never reads `#[Id]` or
   `#[Request]` (their classes are gone, and PHP only checks an attribute when it is instantiated), and drops a
   `#[Response(statusCode: …)]` with a warning (`invalid #[…\Response] …, attribute ignored`; `--strict` fails on it).
2. **Prefer inference over declaration.** 0.6 needed `@oa-response-status 201` on a `POST`, `#[Id]` for readable
   names and parsers for many return types; 1.0 infers most of it. Remove a declaration, regenerate, and keep it only
   if the document is worse without it.
3. **Do not port the built-in classes of 0.6** (`Parsers\*`, `Transformers\Middlewares\*`, the JSON:API parsers):
   1.0 registers its own. Port only the classes of the application.
4. **Extensions are immutable and never throw**: return the new description; for code they do not understand, add a
   diagnostic (`$context->diagnostics->warning(Stage::Request, '…')`, `Ark4ne\OpenApi\Context\Stage`).
5. **Never edit the generated file**, and never hand-merge the baseline into it: change the code, the attributes or
   the configuration and regenerate.
6. **Commit step by step** (configuration, tags, requests, extensions, translations) so that each diff of the
   generated document can be traced to its cause.
