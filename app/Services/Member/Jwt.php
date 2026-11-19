<?php
declare(strict_types=1);

namespace App\Services\Member;

/**
 * LS-14 Module 7: verifies an OpenID Connect id_token (RS256 only) against the provider's published keys (JWKS). Written without a library: the
 * RSA public key is rebuilt from the JWK modulus and exponent as DER and checked with openssl_verify. Any doubt is a refusal: unknown key id,
 * other algorithm (including "none"), bad signature, wrong issuer or audience, expired, issued in the future, or a nonce that does not match.
 */
final class Jwt
{
    /** seconds of clock difference tolerated between this server and the provider */
    public const LEEWAY = 120;

    /**
     * @param array{keys:list<array<string,string>>} $jwks
     * @param callable(string):bool $issuerOk
     * @return array<string,mixed> the verified claims
     * @throws \RuntimeException with a short reason code
     */
    public static function verify(string $token, array $jwks, callable $issuerOk, string $audience, ?string $nonce, ?int $now = null): array
    {
        $now ??= time();
        $parts = explode('.', $token);
        if (count($parts) !== 3) { throw new \RuntimeException('JWT_MALFORMED'); }
        $header = json_decode(self::b64($parts[0]), true);
        $claims = json_decode(self::b64($parts[1]), true);
        if (!is_array($header) || !is_array($claims)) { throw new \RuntimeException('JWT_MALFORMED'); }
        if (($header['alg'] ?? '') !== 'RS256') { throw new \RuntimeException('JWT_ALG'); }
        $key = null;
        foreach ($jwks['keys'] ?? [] as $k) {
            if (($k['kid'] ?? null) === ($header['kid'] ?? '') && ($k['kty'] ?? '') === 'RSA') { $key = $k; break; }
        }
        if ($key === null || empty($key['n']) || empty($key['e'])) { throw new \RuntimeException('JWT_KEY'); }
        $pem = self::rsaPem(self::b64($key['n']), self::b64($key['e']));
        if (openssl_verify($parts[0] . '.' . $parts[1], self::b64($parts[2]), $pem, OPENSSL_ALGO_SHA256) !== 1) { throw new \RuntimeException('JWT_SIGNATURE'); }
        if (!is_string($claims['iss'] ?? null) || !$issuerOk($claims['iss'], $claims)) { throw new \RuntimeException('JWT_ISSUER'); }
        $aud = $claims['aud'] ?? null;
        if (!(is_string($aud) ? hash_equals($audience, $aud) : (is_array($aud) && in_array($audience, $aud, true)))) { throw new \RuntimeException('JWT_AUDIENCE'); }
        if (!is_numeric($claims['exp'] ?? null) || (int)$claims['exp'] < $now - self::LEEWAY) { throw new \RuntimeException('JWT_EXPIRED'); }
        if (isset($claims['iat']) && (int)$claims['iat'] > $now + self::LEEWAY) { throw new \RuntimeException('JWT_FUTURE'); }
        if ($nonce !== null && !hash_equals($nonce, (string)($claims['nonce'] ?? ''))) { throw new \RuntimeException('JWT_NONCE'); }
        if (!is_string($claims['sub'] ?? null) || $claims['sub'] === '') { throw new \RuntimeException('JWT_SUBJECT'); }
        return $claims;
    }

    public static function b64(string $s): string
    {
        $d = base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4), true);
        return $d === false ? '' : $d;
    }

    public static function b64url(string $raw): string { return rtrim(strtr(base64_encode($raw), '+/', '-_'), '='); }

    /** PEM SubjectPublicKeyInfo for an RSA key from its raw modulus and exponent. */
    public static function rsaPem(string $n, string $e): string
    {
        $int = static fn(string $x): string => "\x02" . self::len(strlen($x = (ord($x[0] ?? "\0") > 0x7f ? "\0" . $x : $x))) . $x;
        $rsaKey = "\x30" . self::len(strlen($body = $int($n) . $int($e))) . $body;
        $algo = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00";   // rsaEncryption OID, NULL
        $bits = "\x03" . self::len(strlen($rsaKey) + 1) . "\x00" . $rsaKey;
        $spki = "\x30" . self::len(strlen($algo . $bits)) . $algo . $bits;
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    private static function len(int $l): string
    {
        if ($l < 0x80) { return chr($l); }
        $b = ''; while ($l > 0) { $b = chr($l & 0xff) . $b; $l >>= 8; }
        return chr(0x80 | strlen($b)) . $b;
    }
}
