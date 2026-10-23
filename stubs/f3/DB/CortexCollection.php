<?php

namespace DB;

/**
 * F3 DB\CortexCollection — collection of Cortex models.
 *
 * Extends ArrayIterator (already has ArrayAccess/Iterator/Countable).
 * Using plain non-generic form avoids PHPStan inferring intersection types
 * on Cortex::find() return values — that was the source of the
 * "(CortexCollection&iterable<Model>)|false" errors in Phase 0.
 *
 * @extends \ArrayIterator<int, \DB\Cortex>
 */
class CortexCollection extends \ArrayIterator {

    public function __construct() {}

    /** @param array<int, \DB\Cortex> $models */
    public function setModels(array $models, bool $init = true): void {}
    public function add(\DB\Cortex $model): void {}
    public function hasChanged(): bool {}
    public function getRelSet(string $key): mixed {}
    public function setRelSet(string $key, mixed $set): void {}
    public function hasRelSet(string $key): bool {}
    /** @return array<mixed> */
    public function expose(): array {}
    /**
     * @param array<int|string, mixed> $keys
     * @return array<mixed>
     */
    public function getSubset(string $prop, array $keys): array {}
    /** @return array<mixed> */
    public function getAll(string $prop, bool $raw = false): array {}
    /** @return array<int, array<string, mixed>> */
    public function castAll(int $rel_depths = 1): array {}
    /** @return array<string|int, mixed> */
    public function getBy(string $index, bool $nested = false): array {}
    public function orderBy(string $cond): void {}
    public function slice(int $offset, ?int $limit = null): void {}
    public function contains(mixed $val, string $key = '_id'): bool {}
}
