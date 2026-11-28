<?php
declare(strict_types=1);

namespace App\Services\Auth;

/** LS-89 BE-3: time-based one-time codes (RFC 6238: HMAC-SHA1, 6 digits, 30 seconds), the format authenticator apps use. Pure functions. */
final class Totp
{
    public const PERIOD = 30;
    public const DIGITS = 6;
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function base32Encode(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }
        return $out;
    }

    public static function code(string $secretBytes, int $step): string
    {
        $hash = hash_hmac('sha1', pack('J', $step), $secretBytes, true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24) | (ord($hash[$offset + 1]) << 16) | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);
        return str_pad((string)($value % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * The time step the code belongs to (one step either side of now is accepted for clock drift), or null.
     * A step at or before $lastUsedStep is refused, so a code that was already used cannot be used again.
     */
    public static function match(string $secretBytes, string $code, int $now, ?int $lastUsedStep = null): ?int
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if (preg_match('/^\d{' . self::DIGITS . '}$/', $code) !== 1) {
            return null;
        }
        $current = intdiv($now, self::PERIOD);
        foreach ([$current, $current - 1, $current + 1] as $step) {
            if (($lastUsedStep === null || $step > $lastUsedStep) && hash_equals(self::code($secretBytes, $step), $code)) {
                return $step;
            }
        }
        return null;
    }
}
