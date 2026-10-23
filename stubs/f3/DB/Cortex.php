<?php

namespace DB;

/**
 * F3 DB\Cortex — ActiveRecord ORM.
 *
 * Dynamic model properties are accessed via __get/__set (ActiveRecord pattern).
 * App-specific subclass methods (getCharacter(), getData(), etc.) exist on
 * concrete model classes, not here — those errors belong to Phase 2 return-type fixes.
 */
class Cortex {

    public function __construct(mixed $db = null, ?string $table = null, mixed $fluid = null, int $ttl = 0) {}

    /**
     * @param array<mixed>|null $filter
     * @param array<string, mixed>|null $options
     */
    public function find(mixed $filter = null, ?array $options = null, int $ttl = 0): \DB\CortexCollection|false {}

    /**
     * @param array<mixed>|null $filter
     * @param array<string, mixed>|null $options
     */
    public function findone(mixed $filter = null, ?array $options = null, int $ttl = 0): static|false {}

    /**
     * @param array<mixed>|null $filter
     * @param array<string, mixed>|null $options
     */
    public function load(mixed $filter = null, ?array $options = null, int $ttl = 0): static {}

    /**
     * @param array<mixed>|null $filter
     * @param array<string, mixed>|null $options
     * @return array<int, static>|false
     */
    public function afind(mixed $filter = null, ?array $options = null, int $ttl = 0, int $rel_depths = 1): array|false {}

    public function save(): static|false {}
    public function erase(mixed $filter = null): int {}
    public function insert(): static {}
    public function update(): static {}

    /**
     * @param array<mixed>|null $filter
     * @param array<string, mixed>|null $options
     */
    public function count(mixed $filter = null, ?array $options = null, int $ttl = 60): int {}
    public function loaded(): bool {}
    public function dry(): bool {}
    /** @return array<string, mixed> */
    public function cast(mixed $obj = null, int $rel_depths = 1): array {}

    public function skip(int $ofs = 1): static|false {}
    public function first(): static|false {}
    public function last(): static|false {}
    public function next(): static|false {}

    public function copyto(string $key, int $relDepth = 0): void {}
    public function copyfrom(string $key, mixed $fields = null): void {}
    public function exists(string $key, bool $relField = false): bool {}
    public function clear(string $key): void {}
    public function changed(?string $key = null): bool {}
    /** @param array<string> $fields */
    public function fields(array $fields = [], bool $exclude = false): static {}
    public function getTable(): string {}
    public function has(string $key, mixed $filter, mixed $options = null): static {}
    public function filter(string $key, mixed $filter = null, mixed $option = null): static {}
    public function clearFilter(?string $key = null): void {}
    /**
     * @param array<mixed> $filters
     * @return array<mixed>
     */
    public function mergeFilter(array $filters, string $glue = 'and'): array {}

    public function __get(string $key): mixed {}
    public function __set(string $key, mixed $val): void {}
    public function __isset(string $key): bool {}
    public function __unset(string $key): void {}
}
