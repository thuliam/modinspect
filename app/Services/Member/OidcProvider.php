<?php
declare(strict_types=1);

namespace App\Services\Member;

/**
 * OpenID Connect provider (Google; Microsoft "common", which covers personal Hotmail/Outlook accounts and work accounts). The id_token from the
 * token endpoint is verified with Jwt::verify against the provider's JWKS (cached for an hour); the access token is never stored.
 */
final class OidcProvider implements OAuthProvider
{
    /** @param callable(string,array):bool $issuerOk */
    public function __construct(
        private string $key, private string $label, private string $clientId, private string $clientSecret,
        private string $authUrl, private string $tokenUrl, private string $jwksUrl, private $issuerOk,
        private OAuthHttp $http, private ?string $cacheDir = null, private string $scope = 'openid email profile',
    ) {}

    public static function google(string $id, string $secret, OAuthHttp $http, ?string $cacheDir = null): self
    {
        return new self('google', 'Google', $id, $secret, 'https://accounts.google.com/o/oauth2/v2/auth', 'https://oauth2.googleapis.com/token',
            'https://www.googleapis.com/oauth2/v3/certs', static fn(string $iss): bool => in_array($iss, ['https://accounts.google.com', 'accounts.google.com'], true), $http, $cacheDir);
    }

    public static function microsoft(string $id, string $secret, OAuthHttp $http, ?string $cacheDir = null): self
    {
        // "common" tokens carry the account's own tenant in the issuer; it must equal the tid claim
        return new self('microsoft', 'Microsoft (Hotmail / Outlook)', $id, $secret, 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
            'https://login.microsoftonline.com/common/oauth2/v2.0/token', 'https://login.microsoftonline.com/common/discovery/v2.0/keys',
            static fn(string $iss, array $c): bool => is_string($c['tid'] ?? null) && preg_match('/^[0-9a-f-]{36}$/', $c['tid']) === 1 && $iss === 'https://login.microsoftonline.com/' . $c['tid'] . '/v2.0', $http, $cacheDir);
    }

    public function key(): string { return $this->key; }

    public function label(): string { return $this->label; }

    public function authorizeUrl(string $redirectUri, string $state, string $codeChallenge, string $nonce): string
    {
        return $this->authUrl . '?' . http_build_query([
            'client_id' => $this->clientId, 'redirect_uri' => $redirectUri, 'response_type' => 'code', 'scope' => $this->scope,
            'state' => $state, 'nonce' => $nonce, 'code_challenge' => $codeChallenge, 'code_challenge_method' => 'S256', 'prompt' => 'select_account',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function exchange(string $code, string $redirectUri, string $codeVerifier, string $nonce): array
    {
        $r = $this->http->postForm($this->tokenUrl, [
            'grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => $redirectUri,
            'client_id' => $this->clientId, 'client_secret' => $this->clientSecret, 'code_verifier' => $codeVerifier,
        ]);
        $tok = json_decode($r['body'], true);
        if ($r['status'] !== 200 || !is_array($tok) || !is_string($tok['id_token'] ?? null)) { throw new \RuntimeException('OAUTH_TOKEN'); }
        $claims = Jwt::verify($tok['id_token'], $this->jwks(), $this->issuerOk, $this->clientId, $nonce);
        $email = is_string($claims['email'] ?? null) ? strtolower($claims['email']) : null;
        // Microsoft personal accounts do not send email_verified; the address is the account itself
        $verified = $this->key === 'microsoft' ? $email !== null : filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);
        return ['subject' => $this->key . ':' . $claims['sub'], 'email' => $email, 'email_verified' => $verified, 'name' => is_string($claims['name'] ?? null) ? mb_substr($claims['name'], 0, 120) : null];
    }

    /** @return array{keys:list<array>} */
    private function jwks(): array
    {
        $file = $this->cacheDir !== null ? rtrim($this->cacheDir, '/\\') . '/jwks-' . $this->key . '.json' : null;
        if ($file !== null && is_file($file) && filemtime($file) > time() - 3600) {
            $cached = json_decode((string)file_get_contents($file), true);
            if (is_array($cached) && !empty($cached['keys'])) { return $cached; }
        }
        $r = $this->http->get($this->jwksUrl);
        $jwks = json_decode($r['body'], true);
        if ($r['status'] !== 200 || !is_array($jwks) || empty($jwks['keys'])) { throw new \RuntimeException('OAUTH_JWKS'); }
        if ($file !== null) { @mkdir(dirname($file), 0700, true); @file_put_contents($file, $r['body'], LOCK_EX); }
        return $jwks;
    }
}
