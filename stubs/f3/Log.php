<?php

/**
 * F3 Log — simple file logger.
 */
class Log {
    public function __construct(string $file) {}
    public function write(string $text, string $format = 'r'): void {}
    public function erase(): bool {}
}
