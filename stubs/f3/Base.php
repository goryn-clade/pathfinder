<?php

/**
 * F3 Base — framework hive and router.
 *
 * App-registered hive callables — typed as mixed because stubs are resolved before
 * app code is indexed. Call-site type narrowing via app PHPDoc picks up from there.
 *
 * @method mixed ccpClient()
 * @method mixed ssoClient()
 * @method mixed gitHubClient()
 * @method mixed eveScoutClient()
 * @method mixed webSocket(array<string, mixed> $options = [])
 * @method \DateTimeZone getTimeZone()
 * @method \DateTime getDateTime(string $time = 'now', ?\DateTimeZone $tz = null)
 *
 * @property mixed $DB     Pool instance registered via Pool::POOL_NAME
 * @property mixed $CACHE  Cache backend DSN or false
 *
 * @implements \ArrayAccess<string|int, mixed>
 */
final class Base extends Prefab implements \ArrayAccess {

    public function get(string $key, mixed $args = null): mixed {}
    public function set(string $key, mixed $val, int $ttl = 0): mixed {}
    public function exists(string $key, mixed &$val = null): bool {}
    public function clear(string $key): void {}
    /** @param array<string, mixed> $vars */
    public function mset(array $vars, string $prefix = '', int $ttl = 0): void {}
    public function hash(string $str): string {}
    /** @param array<string, mixed> $params */
    public function build(string $url, array $params = []): string {}
    /** @param string|array<int|string, mixed> $params */
    public function alias(string $name, string|array $params = [], ?string $query = null, ?string $fragment = null): string {}
    /** @param string|array<int, string> $pattern */
    public function route(string|array $pattern, string|callable $handler, int $ttl = 0, int $kbps = 0): void {}
    public function reroute(?string $url = null, bool $permanent = false, bool $die = true): void {}
    public function run(): void {}
    /** @param array<int, mixed>|null $trace */
    public function error(int $code, string $text = '', ?array $trace = null, int $level = 0): void {}
    public function abort(): void {}
    public function clean(string $str, ?string $tags = null): string {}

    /** @param array<int, mixed> $args */
    public function __call(string $key, array $args): mixed {}
    public function &__get(string $key): mixed {}
    public function __set(string $key, mixed $val): void {}
    public function __isset(string $key): bool {}
    public function __unset(string $key): void {}

    public function offsetExists(mixed $offset): bool {}
    public function &offsetGet(mixed $offset): mixed {}
    public function offsetSet(mixed $offset, mixed $value): void {}
    public function offsetUnset(mixed $offset): void {}
}
