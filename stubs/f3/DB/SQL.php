<?php

namespace DB;

/**
 * F3 DB\SQL — PDO database wrapper.
 */
class SQL {
    /** @param array<int, mixed>|null $options */
    public function __construct(string $dsn, ?string $user = null, ?string $pw = null, ?array $options = null) {}
    /**
     * @param string|array<int, string> $cmds
     * @param array<mixed>|null $args
     * @return array<mixed>|int|false
     */
    public function exec(string|array $cmds, mixed $args = null, int $ttl = 0, bool $log = true, bool $stamp = false): array|int|false {}
    public function begin(): bool {}
    public function rollback(): bool {}
    public function commit(): bool {}
    public function trans(): bool {}
    public function count(): int {}
    public function log(bool $flag = true): string|bool {}
    public function exists(string $table): bool {}
    /** @return array<mixed>|false */
    public function schema(string $table, mixed $fields = null, int $ttl = 0): array|false {}
    public function quote(mixed $val, int $type = \PDO::PARAM_STR): string {}
    public function pdo(): \PDO {}
    public function driver(): string {}
    public function version(): string {}
    public function name(): string {}
    /** @param array<int, mixed> $args */
    public function __call(string $func, array $args): mixed {}
}
