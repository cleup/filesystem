<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Finder;

use ArrayIterator;
use Cleup\Filesystem\Interfaces\FinderAttributesInterface;
use Generator;
use IteratorAggregate;
use Traversable;

class Finder implements IteratorAggregate
{
    public function __construct(private iterable $listing) {}

    /**
     * @param callable $filter
     * @return static
     */
    public function filter($filter)
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
     * @param callable $mapper
     * @return static
     */
    public function map(callable $mapper)
    {
        $generator = (static function (iterable $listing) use ($mapper): Generator {
            foreach ($listing as $item) {
                yield $mapper($item);
            }
        })($this->listing);

        return new static($generator);
    }

    /**
     * @return static
     */
    public function sortByPath()
    {
        $listing = $this->toArray();

        usort($listing, function (FinderAttributesInterface $a, FinderAttributesInterface $b) {
            return $a->path() <=> $b->path();
        });

        return new static($listing);
    }

    /**
     * @return Traversable
     */
    public function getIterator(): Traversable
    {
        return $this->listing instanceof Traversable
            ? $this->listing
            : new ArrayIterator($this->listing);
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return $this->listing instanceof Traversable
            ? iterator_to_array($this->listing, false)
            : (array) $this->listing;
    }
}
