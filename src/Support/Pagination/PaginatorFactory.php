<?php

namespace Ark4ne\OpenApi\Support\Pagination;

use Ark4ne\OpenApi\Support\Facades\Logger;
use Illuminate\Contracts\Pagination\CursorPaginator as CursorPaginatorContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class PaginatorFactory
{
    /**
     * Instantiate the right paginator for a given FQCN, with best-effort
     * constructor detection for unknown/custom types.
     *
     * @param class-string $class
     */
    public static function make(string $class, Collection $collection): object
    {
        if (is_a($class, LengthAwarePaginatorContract::class, true)) {
            return new $class($collection, $collection->count(), 15);
        }

        if (is_a($class, CursorPaginatorContract::class, true)) {
            CursorPaginator::currentCursorResolver(fn() => null);
            return new $class($collection, 15, null);
        }

        // Best-effort for unknown custom paginators
        try {
            $params = (new \ReflectionClass($class))->getConstructor()?->getParameters() ?? [];
            // Heuristic: 2nd param named *total* → LengthAware-style, else Cursor-style
            if (isset($params[1]) && str_contains($params[1]->getName(), 'total')) {
                return new $class($collection, $collection->count(), 15);
            }
            return new $class($collection, 15);
        } catch (\Throwable $e) {
            Logger::warn([
                "Failed to instantiate paginator [{$class}], falling back to LengthAwarePaginator.",
                $e->getMessage(),
            ]);
            return new LengthAwarePaginator($collection, $collection->count(), 15);
        }
    }

    /**
     * Extract links (first/last/prev/next) from a paginator's toArray().
     * Only returns keys that are actually present (CursorPaginator has no first/last).
     */
    public static function buildLinks(object $paginator): array
    {
        try {
            $data = $paginator->toArray();
        } catch (\Throwable) {
            return ['prev' => null, 'next' => null];
        }

        return array_filter([
            'first' => $data['first_page_url'] ?? null,
            'last'  => $data['last_page_url']  ?? null,
            'prev'  => $data['prev_page_url']  ?? null,
            'next'  => $data['next_page_url']  ?? null,
        ], fn($v) => $v !== null);
    }

    /**
     * Extract meta (everything except data and link URL keys).
     */
    public static function buildMeta(object $paginator): array
    {
        try {
            $data = $paginator->toArray();
        } catch (\Throwable) {
            return ['per_page' => 15];
        }

        return \Illuminate\Support\Arr::except($data, [
            'data',
            'first_page_url',
            'last_page_url',
            'prev_page_url',
            'next_page_url',
        ]);
    }
}
