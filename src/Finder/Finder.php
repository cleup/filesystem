<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Finder;

use ArrayIterator;
use Cleup\Filesystem\Interfaces\FinderAttributesInterface;
use Generator;
use IteratorAggregate;
use Traversable;

/**
 * Iterable finder for directory listings.
 * Wraps a generator/iterable with filter, map, and sort operations.
 * Used by the file upload library for directory listing manipulation.
 */
class Finder implements IteratorAggregate
{
    /**
     * @param iterable $listing The underlying listing iterable.
     */
    public function __construct(
        private iterable $listing,
    ) {}

    /**
     * Filter the listing using a callback.
     *
     * @param callable $filter Filter function.
     * @return static
     */
    public function filter(callable $filter): static
    {
        $generator = (static function (iterable $listing) use ($filter): Generator {
            foreach ($listing as $item) {
                if ($filter($item)) {
                    yield $item;
                }
            }
        })($this->listing);

        return new static($generator);
    }

    /**
     * Map the listing using a callback.
     *
     * @param callable $mapper Mapping function.
     * @return static
     */
    public function map(callable $mapper): static
    {
        $generator = (static function (iterable $listing) use ($mapper): Generator {
            foreach ($listing as $item) {
                yield $mapper($item);
            }
        })($this->listing);

        return new static($generator);
    }

    /**
     * Sort the listing by path.
     *
     * @return static
     */
    public function sortByPath(): static
    {
        $listing = $this->toArray();

        usort(
            $listing,
            static fn(FinderAttributesInterface $a, FinderAttributesInterface $b): int =>
            $a->path() <=> $b->path()
        );

        return new static($listing);
    }

    /**
     * Get the iterator for the listing.
     *
     * @return Traversable
     */
    public function getIterator(): Traversable
    {
        return $this->listing instanceof Traversable
            ? $this->listing
            : new ArrayIterator($this->listing);
    }

    /**
     * Convert the listing to an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->listing instanceof Traversable
            ? iterator_to_array($this->listing, false)
            : (array) $this->listing;
    }
}
