<?php

/**
 * F3 Cache — cache backend abstraction.
 */
class Cache extends Prefab {
    public function exists(string $key, mixed &$val = null): bool {}
    public function set(string $key, mixed $val, int $ttl = 0): mixed {}
    public function get(string $key): mixed {}
    public function clear(string $key): bool {}
    public function reset(?string $suffix = null): bool {}
}
