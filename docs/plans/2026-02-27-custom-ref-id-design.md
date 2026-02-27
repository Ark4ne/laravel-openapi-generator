# Design — Custom Ref ID via `#[Id]` attribute

**Date:** 2026-02-27
**Scope:** enums and resource classes (`JsonApiResource`, `JsonResource`)

## Problem

`Ref::enumRef()` and `Ref::resourceRef()` generate component IDs using an MD5 hash prefix to avoid collisions between classes with the same short name in different namespaces. This produces opaque names (e.g. `enum-a3f9c2-status`) that tooling cannot interpret, and `x-name` extensions are not natively supported by most OpenAPI renderers.

## Solution

A new PHP attribute `#[Id(string $id)]` on `TARGET_CLASS` lets users explicitly set the OAS component schema ID for any enum or resource class.

## Components

### `src/Attributes/Id.php` (new)

```php
#[Attribute(Attribute::TARGET_CLASS)]
class Id {
    public function __construct(public string $id) {}
}
```

### `src/Support/Ref.php` (modified)

- `enumRef(string $enumName)` — calls `resolveCustomId()` first; falls back to hash logic if no attribute.
- `resourceRef(string $resourceRef)` — same.
- `resolveCustomId(string $class): ?string` (private) — reads `#[Id]` via reflection, checks the conflict registry, registers the mapping, returns the custom id or null.
- Prefix is preserved: `#[Id('user-status')]` on an enum → `enum-user-status`.

### Conflict registry

Stored in `ArrayCache` (key `[Ref::class, 'registry']`) so it follows the same lifecycle as `Component` cache. Maps `custom-id → class-string`. If the same id is claimed by two different classes, an exception is thrown:

```
OpenApi id "user-status" is already claimed by App\Enums\UserStatus,
conflict with App\Enums\OtherStatus.
```

## What does NOT change

`EnumToRef`, `JAResourceRef`, `FieldsRuleParsers`, `IncludesRuleParsers`, and `Component` are unaffected — they all go through `Ref::enumRef()` / `Ref::resourceRef()`.

## Usage example

```php
#[Id('user-status')]
enum UserStatus: string {
    case Active = 'active';
    case Inactive = 'inactive';
}

#[Id('user-resource')]
class UserResource extends JsonApiResource { ... }
```

Generated `$ref`: `#/components/schemas/enum-user-status`, `#/components/schemas/resource-user-resource`.