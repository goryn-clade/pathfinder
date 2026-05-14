<?php
namespace Exodus4D\Pathfinder\Lib;

/**
 * Authenticated encryption for ESI access/refresh tokens at rest.
 *
 * Primitive: libsodium crypto_secretbox (XSalsa20 + Poly1305).
 * Key:       32 bytes, supplied as 64 hex chars in env var TOKEN_ENCRYPTION_KEY.
 * Nonce:     24 bytes from random_bytes(), prepended to ciphertext. Never reused.
 * Format:    "v1:" + base64( nonce || ciphertext_with_mac )
 *
 * Lazy migration: decrypt() returns legacy plaintext (no "v1:" prefix) verbatim,
 * so rows from before this change keep working until the next refresh re-stores
 * them via the encrypting setter.
 *
 * Fail closed: a missing or malformed key throws. Callers treat that as "no
 * valid token" and re-prompt SSO, rather than silently storing plaintext.
 */
class TokenCipher {

    private const string VERSION_PREFIX = 'v1:';

    /**
     * Encrypt a plaintext token. Empty string passes through (no-op).
     * @throws \RuntimeException if TOKEN_ENCRYPTION_KEY is missing/malformed.
     */
    public static function encrypt(string $plaintext) : string {
        if ($plaintext === '') {
            return '';
        }
        $key   = self::getKey();
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ct    = sodium_crypto_secretbox($plaintext, $nonce, $key);
        sodium_memzero($key);
        return self::VERSION_PREFIX . base64_encode($nonce . $ct);
    }

    /**
     * Decrypt a stored token. Returns:
     *   - '' for empty input
     *   - the input verbatim if it lacks the version prefix (legacy plaintext)
     *   - the plaintext on success
     *   - '' if the blob is corrupt or fails MAC verification (wrong key / tampered)
     */
    public static function decrypt(string $blob) : string {
        if ($blob === '') {
            return '';
        }
        if (!str_starts_with($blob, self::VERSION_PREFIX)) {
            // legacy plaintext row — return as-is; will be re-stored encrypted on next refresh
            return $blob;
        }
        $raw = base64_decode(substr($blob, strlen(self::VERSION_PREFIX)), true);
        $min = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES;
        if ($raw === false || strlen($raw) < $min) {
            return '';
        }
        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ct    = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $key   = self::getKey();
        $pt    = sodium_crypto_secretbox_open($ct, $nonce, $key);
        sodium_memzero($key);
        return $pt === false ? '' : $pt;
    }

    /**
     * Load the 32-byte key from env. Hex-decoded.
     * @throws \RuntimeException
     */
    private static function getKey() : string {
        $hex = (string) Config::getEnvironmentData('TOKEN_ENCRYPTION_KEY');
        if ($hex === '') {
            throw new \RuntimeException('TOKEN_ENCRYPTION_KEY is not configured');
        }
        try {
            $key = sodium_hex2bin($hex);
        } catch (\SodiumException) {
            throw new \RuntimeException('TOKEN_ENCRYPTION_KEY must be hex-encoded');
        }
        if (strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            sodium_memzero($key);
            throw new \RuntimeException('TOKEN_ENCRYPTION_KEY must be 32 bytes (64 hex chars)');
        }
        return $key;
    }
}
