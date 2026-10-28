<?php

declare(strict_types=1);

namespace Aenzenith\LucaEFatura\Data;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * One page of a search, with Luca's PagingResponse.
 *
 * @template TItem
 *
 * @implements IteratorAggregate<int, TItem>
 */
final class Page implements Countable, IteratorAggregate
{
    /**
     * @param  list<TItem>  $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $page,
        public readonly int $perPage,
        public readonly ?int $totalPages,
        public readonly ?int $totalRecords,
    ) {}

    /**
     * @template T
     *
     * @param  list<T>  $items
     * @param  mixed  $paging  the PagingResponse member
     * @return self<T>
     */
    public static function from(array $items, mixed $paging, int $page, int $perPage): self
    {
        $paging = is_array($paging) ? $paging : [];

        $int = static fn (string $key): ?int => isset($paging[$key]) && is_numeric($paging[$key]) ? (int) $paging[$key] : null;

        return new self(
            $items,
            $int('PageNumber') ?? $page,
            $int('RecordsPerPage') ?? $perPage,
            $int('TotalNumberOfPages'),
            $int('TotalNumberOfRecords'),
        );
    }

    public function hasMore(): bool
    {
        return $this->totalPages !== null
            ? $this->page < $this->totalPages
            : count($this->items) >= $this->perPage;
    }

    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @return Traversable<int, TItem>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }
}
