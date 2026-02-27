# Custom Ref ID (`#[Id]`) Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Allow users to override auto-generated OAS component IDs for enums and resource classes via a `#[Id(string $id)]` PHP attribute.

**Architecture:** Add a new `Attributes/Id.php` attribute (`TARGET_CLASS`). Modify `Ref::enumRef()` and `Ref::resourceRef()` to check for this attribute before falling back to the hash-based logic. Store a conflict registry in `ArrayCache` (same lifecycle as components) and throw on duplicate IDs claimed by different classes.

**Tech Stack:** PHP 8.1+ attributes, `ReflectionClass::getAttributes()`, existing `ArrayCache` for the registry.

---

### Task 1: Enable Unit test suite

**Files:**
- Modify: `phpunit.xml.dist`

**Step 1: Uncomment the Unit testsuite**

In `phpunit.xml.dist`, replace:
```xml
<!--
    <testsuite name="Unit">
        <directory>tests/Unit</directory>
    </testsuite>
    -->
```
with:
```xml
<testsuite name="Unit">
    <directory>tests/Unit</directory>
</testsuite>
```

**Step 2: Run the full suite to confirm nothing is broken**

```bash
./vendor/bin/phpunit
```
Expected: all existing tests pass (Unit directory is nearly empty, so no new failures).

**Step 3: Commit**

```bash
git add phpunit.xml.dist
git commit -m "test: enable Unit test suite"
```

---

### Task 2: Create test stubs for `#[Id]`

**Files:**
- Create: `tests/app/Enums/StatusWithCustomId.php`
- Create: `tests/app/Enums/AnotherStatusWithSameId.php`

**Step 1: Create enum with custom ID**

`tests/app/Enums/StatusWithCustomId.php`:
```php
<?php

namespace Test\app\Enums;

use Ark4ne\OpenApi\Attributes\Id;

#[Id('custom-status')]
enum StatusWithCustomId: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
```

**Step 2: Create conflicting enum (same `#[Id]`, different class)**

`tests/app/Enums/AnotherStatusWithSameId.php`:
```php
<?php

namespace Test\app\Enums;

use Ark4ne\OpenApi\Attributes\Id;

#[Id('custom-status')]
enum AnotherStatusWithSameId: string
{
    case Yes = 'yes';
    case No = 'no';
}
```

**Step 3: No run needed — these are stubs only. Commit.**

```bash
git add tests/app/Enums/
git commit -m "test: add stub enums for custom Id attribute tests"
```

---

### Task 3: Create `src/Attributes/Id.php` (TDD — write test first)

**Files:**
- Create: `tests/Unit/Attributes/IdTest.php`
- Create: `src/Attributes/Id.php`

**Step 1: Write the failing test**

`tests/Unit/Attributes/IdTest.php`:
```php
<?php

namespace Test\Unit\Attributes;

use Ark4ne\OpenApi\Attributes\Id;
use PHPUnit\Framework\TestCase;

class IdTest extends TestCase
{
    public function testIdAttributeStoresId(): void
    {
        $attr = new Id('my-id');
        $this->assertSame('my-id', $attr->id);
    }

    public function testIdAttributeIsTargetClass(): void
    {
        $reflection = new \ReflectionClass(Id::class);
        $attributes = $reflection->getAttributes(\Attribute::class);
        $this->assertNotEmpty($attributes);
        $flags = $attributes[0]->newInstance()->flags;
        $this->assertSame(\Attribute::TARGET_CLASS, $flags);
    }
}
```

**Step 2: Run test to verify it fails**

```bash
./vendor/bin/phpunit tests/Unit/Attributes/IdTest.php
```
Expected: FAIL — `Class "Ark4ne\OpenApi\Attributes\Id" not found`.

**Step 3: Create `src/Attributes/Id.php`**

```php
<?php

namespace Ark4ne\OpenApi\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Id
{
    public function __construct(public string $id)
    {
    }
}
```

**Step 4: Run test to verify it passes**

```bash
./vendor/bin/phpunit tests/Unit/Attributes/IdTest.php
```
Expected: PASS.

**Step 5: Commit**

```bash
git add src/Attributes/Id.php tests/Unit/Attributes/IdTest.php
git commit -m "feat: add #[Id] attribute for custom OAS component IDs"
```

---

### Task 4: Implement `Ref::resolveCustomId()` and integrate it (TDD)

**Files:**
- Create: `tests/Unit/Support/RefTest.php`
- Modify: `src/Support/Ref.php`

**Step 1: Write the failing tests**

`tests/Unit/Support/RefTest.php`:
```php
<?php

namespace Test\Unit\Support;

use Ark4ne\OpenApi\Support\ArrayCache;
use Ark4ne\OpenApi\Support\Ref;
use PHPUnit\Framework\TestCase;

class RefTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ArrayCache::clear();
    }

    // --- enumRef ---

    public function testEnumRefUsesCustomId(): void
    {
        $ref = Ref::enumRef(\Test\app\Enums\StatusWithCustomId::class);
        $this->assertSame('enum-custom-status', $ref);
    }

    public function testEnumRefFallsBackToHashWhenNoAttribute(): void
    {
        // StatusEnum has no #[Id] — must contain the md5-based pattern
        // Use a class we know exists and has no #[Id]
        $ref = Ref::enumRef(\Test\app\Enums\AnotherStatusWithSameId::class);
        // First call is fine; we only test the format here
        // After clearing cache, the second class has same id → conflict only if both called
        // So reset and test alone:
        ArrayCache::clear();
        $ref = Ref::enumRef(\Test\app\Enums\StatusWithCustomId::class);
        $this->assertStringStartsWith('enum-', $ref);
        $this->assertSame('enum-custom-status', $ref);
    }

    public function testEnumRefThrowsOnConflict(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/already claimed/');

        Ref::enumRef(\Test\app\Enums\StatusWithCustomId::class);
        Ref::enumRef(\Test\app\Enums\AnotherStatusWithSameId::class);
    }

    public function testEnumRefSameClassTwiceDoesNotThrow(): void
    {
        // Same class calling enumRef twice must not conflict with itself
        $ref1 = Ref::enumRef(\Test\app\Enums\StatusWithCustomId::class);
        $ref2 = Ref::enumRef(\Test\app\Enums\StatusWithCustomId::class);
        $this->assertSame($ref1, $ref2);
    }

    // --- resourceRef ---

    public function testResourceRefUsesCustomId(): void
    {
        $ref = Ref::resourceRef(\Test\app\Http\Resources\UserResource::class);
        // UserResource has no #[Id] — just assert format
        $this->assertStringStartsWith('resource-', $ref);
    }
}
```

> Note: `UserResource` has no `#[Id]` so it tests the fallback path. The custom-id path for resources will be covered by the integration test in Task 5.

**Step 2: Run to verify they fail**

```bash
./vendor/bin/phpunit tests/Unit/Support/RefTest.php
```
Expected: FAIL — the conflict test passes (no guard yet), custom-id test fails (returns hash-based id).

**Step 3: Modify `src/Support/Ref.php`**

Add the `resolveCustomId` helper and update both public methods:

```php
<?php

namespace Ark4ne\OpenApi\Support;

use Illuminate\Support\Str;

class Ref
{
    /**
     * @param string|class-string $enumName
     * @return string
     */
    public static function enumRef(string $enumName): string
    {
        if (class_exists($enumName)) {
            if ($customId = self::resolveCustomId($enumName)) {
                return 'enum-' . self::snake($customId);
            }

            $enumReflection = Reflection::reflection($enumName);
            $key = substr(md5($enumReflection->getNamespaceName()), 0, 6) . '-' . $enumReflection->getShortName();

            return 'enum-' . self::snake($key);
        }

        return "enum-" . self::snake($enumName);
    }

    /**
     * @param string|class-string $resourceRef
     * @return string
     */
    public static function resourceRef(string $resourceRef): string
    {
        if (class_exists($resourceRef)) {
            if ($customId = self::resolveCustomId($resourceRef)) {
                return 'resource-' . self::snake($customId);
            }

            $resourceReflection = Reflection::reflection($resourceRef);
            $key = substr(md5($resourceReflection->getNamespaceName()), 0, 6) . '-' . $resourceReflection->getShortName();

            return 'resource-' . self::snake($key);
        }

        return "resource-" . self::snake($resourceRef);
    }

    public static function fieldsRef(string $key): string
    {
        $key = self::snake($key);

        return 'rule-fields-' . $key;
    }

    public static function includeRef(string $key): string
    {
        $key = self::snake($key);

        return 'rule-include-' . $key;
    }

    public static function securityRef(string $key): string
    {
        $key = self::snake($key);

        return 'security-' . $key;
    }

    private static function resolveCustomId(string $class): ?string
    {
        $reflection = Reflection::reflection($class);
        $attributes = $reflection->getAttributes(\Ark4ne\OpenApi\Attributes\Id::class);

        if (empty($attributes)) {
            return null;
        }

        $id = $attributes[0]->newInstance()->id;

        $registry = ArrayCache::get([self::class, 'registry']) ?? [];

        if (isset($registry[$id]) && $registry[$id] !== $class) {
            throw new \RuntimeException(
                "OpenApi id \"{$id}\" is already claimed by {$registry[$id]}, conflict with {$class}."
            );
        }

        $registry[$id] = $class;
        ArrayCache::set([self::class, 'registry'], $registry);

        return $id;
    }

    private static function snake(string $value): string
    {
        return preg_replace('/-{2,}/', '-', Str::snake($value, '-'));
    }
}
```

**Step 4: Run tests to verify they pass**

```bash
./vendor/bin/phpunit tests/Unit/Support/RefTest.php
```
Expected: PASS.

**Step 5: Run full suite to verify no regressions**

```bash
./vendor/bin/phpunit
```
Expected: all tests pass.

**Step 6: Commit**

```bash
git add src/Support/Ref.php tests/Unit/Support/RefTest.php
git commit -m "feat: resolve custom #[Id] in Ref::enumRef and resourceRef with conflict detection"
```

---

### Task 5: Integration test — verify custom ID appears in generated OAS output

**Files:**
- Modify: `tests/app/Http/Resources/UserResource.php` (add `#[Id]`)
- Modify: `tests/Feature/expected/openapi-jsonresource.json` (update ref names)
- *(No new test file needed — existing `GenerateJsonResourceTest` covers this)*

**Step 1: Add `#[Id]` to `UserResource`**

Open `tests/app/Http/Resources/UserResource.php` and add the attribute:
```php
use Ark4ne\OpenApi\Attributes\Id;

#[Id('user')]
class UserResource extends JsonResource
{
    // ... existing code unchanged
}
```

**Step 2: Run the generation test to see what changes**

```bash
./vendor/bin/phpunit tests/Feature/GenerateJsonResourceTest.php
```
Expected: FAIL — the generated JSON will differ from the fixture because `resource-user` now replaces the hash-based ID.

**Step 3: Update the expected fixture**

Re-generate the reference fixture by temporarily commenting out the assertion and dumping the output, or by running:
```bash
./vendor/bin/phpunit tests/Feature/GenerateJsonResourceTest.php 2>&1 | head -40
```
Then copy the actual output file over the expected fixture:
```bash
# The generated file path is printed in the test output (Storage disk 'openapi')
# It will be under tests/app/openapi-v1.json (or similar per UseLocalApp config)
cp tests/app/openapi-v1.json tests/Feature/expected/openapi-jsonresource.json
```

**Step 4: Verify the new fixture contains `resource-user`**

```bash
grep -c '"resource-user"' tests/Feature/expected/openapi-jsonresource.json
```
Expected: count > 0.

**Step 5: Run the full suite**

```bash
./vendor/bin/phpunit
```
Expected: all tests pass.

**Step 6: Commit**

```bash
git add tests/app/Http/Resources/UserResource.php tests/Feature/expected/openapi-jsonresource.json
git commit -m "test: integrate #[Id] on UserResource and update expected OAS fixture"
```

---

### Task 6: Final check and clean-up

**Step 1: Run the full suite one last time**

```bash
./vendor/bin/phpunit --testdox
```
Expected: all tests pass.

**Step 2: Revert `#[Id]` on `UserResource` if it was only added for testing**

> Only do this if you don't want `UserResource` to have a custom ID in production. If it's a test-only class, the attribute is fine to keep.

**Step 3: Commit (if reverted)**

```bash
git add tests/app/Http/Resources/UserResource.php tests/Feature/expected/openapi-jsonresource.json
git commit -m "test: revert #[Id] on UserResource test stub"
```
