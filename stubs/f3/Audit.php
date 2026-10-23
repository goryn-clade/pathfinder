<?php

/**
 * F3 Audit — input validation helpers.
 */
class Audit extends Prefab {
    public function url(string $str): bool {}
    public function email(string $str, bool $mx = true): bool {}
    public function ipv4(string $addr): bool {}
    public function ipv6(string $addr): bool {}
    public function isprivate(string $addr): bool {}
    public function isreserved(string $addr): bool {}
    public function ispublic(string $addr): bool {}
    public function isdesktop(?string $agent = null): bool {}
    public function ismobile(?string $agent = null): bool {}
    public function isbot(?string $agent = null): bool {}
    public function mod10(string $id): bool {}
}
