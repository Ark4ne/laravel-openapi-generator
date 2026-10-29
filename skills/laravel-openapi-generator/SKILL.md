---
name: laravel-openapi-generator
description: Generate and maintain the OpenAPI 3.0, 3.1 and 3.2 documentation of a Laravel application with ark4ne/laravel-openapi-generator (routes, form requests, validation rules, API resources, attributes, extensions, php artisan openapi:generate and openapi:inspect). Use when code imports Ark4ne\OpenApi, when a Laravel project must produce or fix its OpenAPI or Swagger specification, or when documenting an endpoint the generator gets wrong.
---

# ark4ne/laravel-openapi-generator

A Laravel 13 package (PHP 8.3+) that **observes** the application and writes its OpenAPI documents: it reads the
routes, the PHPDoc and the signatures of the actions, the form requests and their rules, runs the API resources on
sampled models, and writes the result with `ark4ne/openapi-writer` in the OpenAPI version of each document
(3.0.x, 3.1.x or 3.2.0; 3.1.2 by default). What it cannot infer is declared with PHP attributes.

Files of this skill:

- [reference.md](reference.md): every attribute and its parameters, every configuration key, every extension point,
  the commands.
- [recipes.md](recipes.md): complete, tested examples (CRUD, declared responses and exceptions, hand-described
  requests, webhooks and callbacks, extensions, JSON:API, checking the documentation in tests).

The source is the final authority: `vendor/ark4ne/laravel-openapi-generator/src/` (`Attributes/` for the attributes,
`config/openapi.php` for the configuration, documented key by key).

Upgrading an application from 0.6 (`versions` in `config/openapi.php`, `@oa-*` tags, `DescribableRequest`): use the
`laravel-openapi-generator-upgrade` skill (`vendor/ark4ne/laravel-openapi-generator/skills/laravel-openapi-generator-upgrade/`).

## Install

```bash
composer require ark4ne/laravel-openapi-generator
php artisan vendor:publish --tag=openapi-config   # config/openapi.php
php artisan openapi:generate                      # writes openapi/openapi-v1.json on the default disk
composer require --dev opis/json-schema           # validation against the official schemas (recommended)
```

## Rules that prevent most mistakes

1. **Type the return of every action.** The responses come from it: `UserResource`,
   `AnonymousResourceCollection<UserResource>` (in `@return`), `JsonResponse`, an array shape, `CatResource|DogResource`.
   Without a return type the operation gets no response but a diagnostic; `void` gives an empty 200.
2. **Validate with a form request.** Its `rules()` give the body (or the query for `GET`, `HEAD`, `DELETE`); rules
   written in the controller (`$request->validate()`) are not read. `required`, `nullable`, `in:`, `max:`,
   `Rule::enum()`, `File`, `Password`, `date_format:`… map to schema constraints; `sometimes`, `required_if`… make
   the field optional with its condition in the description.
3. **Let the resources be sampled.** A resource is described by running `toArray()` on a sampled model: give the
   model a factory (run in a rolled back transaction), or `@property` and casts (fake values). `@mixin User` or
   `@extends JsonResource<User>` tells which model a resource receives; `#[ResourceFactory]` builds what a resource
   receives when it is not a model.
4. **Declare what cannot be inferred with attributes**, never with PHPDoc tags: `#[Response]` (status, body,
   headers, pagination), `#[RequestBody]`, `#[QueryParameter]`, `#[Header]`, `#[Security]`, `#[Tag]`,
   `#[Operation]`, `#[Exclude]`. A `#[Response]` replaces the response inferred for its status; a declared 2xx also replaces the
   successes inferred for other statuses (declare `#[Response(201)]` on an action returning a `JsonResponse`). Paginated
   collections need `#[Response(resource:, paginator:)]`: the return type does not tell the paginator.
5. **Errors come from what the action does.** Middlewares give 401, 403, 419, 429; a bound model gives 404; a form
   request gives 422; each `@throws` gives its response (`#[Response]` on the exception class, `exceptions.map`, or
   Laravel's rendering). Do not declare them by hand unless their body differs.
6. **Summaries and descriptions are in the PHPDoc**: first line, then the rest (CommonMark). Translation keys work
   with `languages`.
7. **Only the routes of `routes.include` are documented** (`api/*` by default). A route missing from the document is
   usually filtered; `openapi:inspect <route>` says whether it is in the document.
8. **Read the diagnostics.** `php artisan openapi:generate -v` lists what the generator did not understand, grouped
   by route; `openapi:inspect <route-name>` shows the operation it built and every diagnostic about it. Fix the
   cause (a type, a rule, an attribute), not the document.
9. **Never edit the generated file**: it is rewritten by every run. Change the code, the attributes or
   `config/openapi.php`.
10. **Extend through the interfaces**, listed in `extensions` of the configuration and built through the container:
    a `RuleMapper` for a custom rule name, a `ResponseExtractor` for a return type the package does not know, a `Pass`
    for a change over the whole document. A rule object can describe itself with `DescribesRule`, a request with
    `DescribesRequest`.
11. **Description objects are immutable**: every method returns a copy; always use the return value
    (`$field = $field->schema(...)`). Schemas are `Ark4ne\OpenApiWriter\Objects\Schema`.
12. **Webhook routes are documented, not served**: a `#[Webhook]` action is a real route describing the request the
    application sends. It must match `routes.include` like any route, and a middleware must refuse every request
    (`abort(404)`); registering it only in the console does not work, `route:cache` runs in the console too.

## Workflow to document an endpoint

1. Write the route, the form request, the resource and the action with its return type and its PHPDoc.
2. `php artisan openapi:inspect <route-name>`: check the parameters, the body, the responses and the diagnostics.
3. Add attributes for what is missing or wrong (see [reference.md](reference.md)).
4. `php artisan openapi:generate` and validate in CI with `--strict --dry-run` (fails on the first warning).

## When the generated document is wrong

| Symptom | Cause and fix |
| --- | --- |
| The route is missing | Not matched by `routes.include`, excluded (`routes.exclude`, `#[Exclude]`), a redirect or view route, or an internal route |
| No response, or `{}` | No return type, or an untyped `JsonResponse`: type the return, or add `#[Response(type: …)]` |
| A resource has no properties | `toArray()` failed on the sample: see the warning; give the model a factory, `@property` or a `#[ResourceFactory]` |
| A property should be optional | It is computed by `when()`, `whenLoaded()`, `mergeWhen()`: those are optional; a plain value is required |
| No body | The rules are not in a form request, or the method is `GET`/`DELETE` (the fields go to the query) |
| A list has no pagination | Declare `#[Response(resource: UserResource::class, paginator: LengthAwarePaginator::class)]` |
| Wrong security | Add the middleware to the route, or `#[Security('scheme')]`; declare custom schemes in `security_schemes` |
| A 3.0 document fails | A 3.1 or 3.2 feature (webhooks, `Tag.parent`, `$self`) in a strict document: set `strict` to `false`, use `vendor_extensions: redoc`, or choose another version |

## Versions

Write the latest model, choose the version per document (`documents.<name>.openapi`): `nullable`, several types,
`const`, `exclusiveMinimum` are converted for 3.0; webhooks need 3.1 (or `redoc` before); `Tag.parent`, `kind`,
`summary` of tags and responses, `$self`, the `query` method and `querystring` need 3.2. A strict document fails on
what its version cannot write; a lenient one drops it with a warning.
