<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Configuration;

use Ark4ne\OpenApi\Exceptions\InvalidConfigurationException;
use UnitEnum;

/**
 * The variant dimension of a document (`documents.<name>.variants`): the document is generated once per variant, and
 * once more without variant when `head` names that document.
 *
 * ```php
 * 'variants' => [
 *     'values' => App\Http\ApiRevision::class, // an enum (its cases) or a non-empty list of strings
 *     'head' => 'latest',                      // the name of the document without variant; false or null for none
 *     'header' => 'Api-Version',               // the request header that selects the variant; null for none
 * ],
 * ```
 */
final readonly class VariantsConfiguration
{
    /**
     * A name written in file names: letters, digits, `.`, `-`, `_`, starting with a letter or a digit (no path, no
     * hidden file).
     */
    private const string NAME = '/^[A-Za-z0-9][A-Za-z0-9._-]*$/';

    /**
     * A header name: an HTTP token (RFC 9110 §5.6.2).
     */
    private const string HEADER = "/^[A-Za-z0-9!#$%&'*+.^_`|~-]+$/";

    /**
     * The header parameters OpenAPI ignores, described by its own fields: not a variant header.
     */
    private const array RESERVED_HEADERS = ['accept', 'content-type', 'authorization'];

    /**
     * @param list<Variant> $values in declaration order, of one dimension, with distinct names
     * @param string|null $head the name of the document generated without variant, `null` for none
     * @param string|null $header the request header that selects the variant, `null` for none
     */
    public function __construct(
        public array $values,
        public ?string $head = null,
        public ?string $header = null,
    ) {}

    /**
     * @param array<array-key, mixed> $config the `variants` entry of a document
     *
     * @throws InvalidConfigurationException `values` is neither an enum class nor a non-empty list of strings, two
     *         variants share a name, `head` is the name of a variant, `head` is not a name, `header` is not a header
     *         name or is one OpenAPI ignores (Accept, Content-Type, Authorization)
     */
    public static function fromArray(string $key, array $config): self
    {
        $values = self::values("{$key}.values", $config['values'] ?? null);
        $names = array_map(static fn(Variant $variant): string => $variant->name, $values);

        if (array_filter($names, self::isName(...)) !== $names || count(array_unique($names)) !== count($names)) {
            throw InvalidConfigurationException::forKey("{$key}.values", 'variants of distinct names of letters, digits, ".", "-" and "_"', $names);
        }

        $head = $config['head'] ?? null;
        $head = $head === false ? null : $head;

        if ($head !== null && (! is_string($head) || ! self::isName($head) || in_array($head, $names, true))) {
            throw InvalidConfigurationException::forKey("{$key}.head", 'false or a name of letters, digits, ".", "-" and "_" that no variant has', $head);
        }

        $header = $config['header'] ?? null;

        if ($header !== null && (! is_string($header) || preg_match(self::HEADER, $header) !== 1 || in_array(strtolower($header), self::RESERVED_HEADERS, true))) {
            throw InvalidConfigurationException::forKey("{$key}.header", 'null or a header name other than Accept, Content-Type and Authorization', $header);
        }

        return new self($values, $head, $header);
    }

    /**
     * The variant of that name, `null` for an unknown name and for the name of the head.
     */
    public function named(string $name): ?Variant
    {
        foreach ($this->values as $variant) {
            if ($variant->name === $name) {
                return $variant;
            }
        }

        return null;
    }

    /**
     * The name of a generation: that of its variant, that of the head for `null` (`null` without head).
     */
    public function nameOf(?Variant $variant): ?string
    {
        return $variant->name ?? $this->head;
    }

    /**
     * The names of every generation of the document, in order: the variants, then the head when there is one.
     *
     * @return list<string>
     */
    public function names(): array
    {
        $names = array_map(static fn(Variant $variant): string => $variant->name, $this->values);

        return $this->head === null ? $names : [...$names, $this->head];
    }

    /**
     * The variants of an enum class (its cases) or of a non-empty list of strings, in declaration order.
     *
     * @return list<Variant>
     *
     * @throws InvalidConfigurationException
     */
    private static function values(string $key, mixed $values): array
    {
        if (is_string($values) && enum_exists($values) && $values::cases() !== []) {
            /** @var class-string<UnitEnum> $values */
            return array_map(Variant::ofCase(...), $values::cases(), array_keys($values::cases()));
        }

        if (is_array($values) && $values !== [] && array_is_list($values) && array_filter($values, is_string(...)) === $values) {
            /** @var list<string> $values */
            return array_map(static fn(string $value, int $position): Variant => Variant::ofString($value, $position, $key), $values, array_keys($values));
        }

        throw InvalidConfigurationException::forKey($key, 'an enum class with cases or a non-empty list of strings', $values);
    }

    private static function isName(string $name): bool
    {
        return preg_match(self::NAME, $name) === 1;
    }
}
