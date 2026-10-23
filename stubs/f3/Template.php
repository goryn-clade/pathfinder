<?php

/**
 * F3 Template — templating engine.
 */
class Template extends Prefab {
    /** @param array<string, mixed>|null $hive */
    public function render(string $file, string $mime = 'text/html', ?array $hive = null, int $ttl = 0): string {}
    public function extend(string $tag, callable $func): void {}
    public function build(mixed $node): string {}
}
