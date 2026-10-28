<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Responses;

use Ark4ne\OpenApiWriter\Objects\Schema;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;

/**
 * The JSON shapes of the paginators of Laravel 13, as a resource collection writes them (`data`, `links`, `meta`)
 * and as a paginator returned as it is writes them.
 */
final class Paginations
{
    /**
     * Whether a class is a paginator Laravel knows (contract or implementation).
     */
    public static function supports(string $class): bool
    {
        return is_a($class, Paginator::class, true) || is_a($class, CursorPaginator::class, true);
    }

    /**
     * `data`, `links` and `meta` of a paginated resource collection.
     */
    public static function resource(string $paginator, Schema $items): Schema
    {
        $links = self::object(['first' => self::url(), 'last' => self::url(), 'prev' => self::url(), 'next' => self::url()]);

        $meta = match (true) {
            is_a($paginator, LengthAwarePaginator::class, true) => self::object([
                'current_page' => Schema::integer(),
                'from' => Schema::integer()->nullable(),
                'last_page' => Schema::integer(),
                'links' => self::links(),
                'path' => self::url(),
                'per_page' => Schema::integer(),
                'to' => Schema::integer()->nullable(),
                'total' => Schema::integer(),
            ]),
            is_a($paginator, CursorPaginator::class, true) => self::object([
                'path' => self::url(),
                'per_page' => Schema::integer(),
                'next_cursor' => Schema::string()->nullable(),
                'prev_cursor' => Schema::string()->nullable(),
            ]),
            default => self::object([
                'current_page' => Schema::integer(),
                'current_page_url' => self::url(),
                'from' => Schema::integer()->nullable(),
                'path' => self::url(),
                'per_page' => Schema::integer(),
                'to' => Schema::integer()->nullable(),
            ]),
        };

        return self::object(['data' => Schema::array()->items($items), 'links' => $links, 'meta' => $meta]);
    }

    /**
     * A paginator returned as it is (`toArray()`).
     */
    public static function raw(string $paginator, Schema $items): Schema
    {
        $data = Schema::array()->items($items);

        return match (true) {
            is_a($paginator, LengthAwarePaginator::class, true) => self::object([
                'current_page' => Schema::integer(),
                'data' => $data,
                'first_page_url' => self::url(),
                'from' => Schema::integer()->nullable(),
                'last_page' => Schema::integer(),
                'last_page_url' => self::url(),
                'links' => self::links(),
                'next_page_url' => self::url(),
                'path' => self::url(),
                'per_page' => Schema::integer(),
                'prev_page_url' => self::url(),
                'to' => Schema::integer()->nullable(),
                'total' => Schema::integer(),
            ]),
            is_a($paginator, CursorPaginator::class, true) => self::object([
                'data' => $data,
                'path' => self::url(),
                'per_page' => Schema::integer(),
                'next_cursor' => Schema::string()->nullable(),
                'next_page_url' => self::url(),
                'prev_cursor' => Schema::string()->nullable(),
                'prev_page_url' => self::url(),
            ]),
            default => self::object([
                'current_page' => Schema::integer(),
                'current_page_url' => self::url(),
                'data' => $data,
                'first_page_url' => self::url(),
                'from' => Schema::integer()->nullable(),
                'next_page_url' => self::url(),
                'path' => self::url(),
                'per_page' => Schema::integer(),
                'prev_page_url' => self::url(),
                'to' => Schema::integer()->nullable(),
            ]),
        };
    }

    private static function links(): Schema
    {
        return Schema::array()->items(self::object([
            'url' => self::url(),
            'label' => Schema::string(),
            'page' => Schema::integer()->nullable(),
            'active' => Schema::boolean(),
        ]));
    }

    private static function url(): Schema
    {
        return Schema::string()->nullable();
    }

    /**
     * @param array<string, Schema> $properties all required
     */
    public static function object(array $properties): Schema
    {
        $schema = Schema::object();

        foreach ($properties as $name => $property) {
            $schema = $schema->addProperties($property->objectId($name));
        }

        return $properties === [] ? $schema : $schema->required(...array_keys($properties));
    }
}
