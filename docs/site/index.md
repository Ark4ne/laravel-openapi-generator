---
title: Laravel OpenAPI Generator
description: The OpenAPI documents of your Laravel application, written from its routes, form requests and resources.
---

::example{input="StoreBookRequest::rules()" output="the schema of the request body"}
#input
```php
return [
    'title' => 'required|string|max:255',
    'price' => 'required|numeric|min:0',
    'tags' => 'array',
    'tags.*' => 'string|distinct',
];
```
#output
```json
{
  "type": "object",
  "required": ["title", "price"],
  "properties": {
    "title": { "type": "string", "maxLength": 255 },
    "price": { "type": "number", "minimum": 0 },
    "tags": {
      "type": "array",
      "items": { "type": "string" },
      "uniqueItems": true
    }
  }
}
```
::

## Why Laravel OpenAPI Generator

A hand-written OpenAPI file is out of date at the next pull request. Your application already says what its API
accepts and returns; the generator reads it and writes the document, in OpenAPI 3.0, 3.1 or 3.2, one document per
API or per audience.

::card-group
:::card{title="Read from the code" icon="i-lucide-scan-search" to="/laravel-openapi-generator/documenting-endpoints/how-it-works"}
Routes, middlewares, return types and PHPDoc are the documentation; attributes complete what cannot be inferred.
:::
:::card{title="Bodies from the validation rules" icon="i-lucide-list-checks" to="/laravel-openapi-generator/documenting-endpoints/requests"}
Every rule of your form requests becomes a constraint of the schema, and the 422 is documented.
:::
:::card{title="Responses from your resources" icon="i-lucide-flask-conical" to="/laravel-openapi-generator/documenting-endpoints/sampling"}
Resources run on sampled models, inside a transaction rolled back at the end: the schema is what the API returns.
:::
:::card{title="Checked in CI" icon="i-lucide-badge-check" to="/laravel-openapi-generator/workflow/continuous-integration"}
Diagnostics by route, and `--strict` fails the build when the documentation degrades.
:::
::

## Compatibility

A Laravel package: `php artisan openapi:generate` writes every document of the configuration.

::compatibility
::
