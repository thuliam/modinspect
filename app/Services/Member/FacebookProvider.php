<?php
declare(strict_types=1);

namespace App\Services\Member;

/**
 * Facebook Login (OAuth 2.0 authorization code + PKCE; Facebook's classic flow has no id_token). The access token is used once, with
 * appsecret_proof, to read id, name and email from the Graph API, and is then dropped. The Graph version is configuration.
 */
final class FacebookProvider implements OAuthProvider
{
    public function __construct(private string $clientId, private string $clientSecret, private OAuthHttp $http, private string $version = 'v19.0') {}

    public function key(): string { return 'facebook'; }

    public function label(): string { return 'Facebook'; }

    public function authorizeUrl(string $redirectUri, string $state, string $codeChallenge, string $nonce): string
    {
        return 'https://www.facebook.com/' . $this->version . '/dialog/oauth?' . http_build_query([
            'client_id' => $this->clientId, 'redirect_uri' => $redirectUri, 'response_type' => 'code', 'scope' => 'public_profile,email',
            'state' => $state, 'code_challenge' => $codeChallenge, 'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function exchange(string $code, string $redirectUri, string $codeVerifier, string $nonce): array
    {
        $r = $this->http->postForm('https://graph.facebook.com/' . $this->version . '/oauth/access_token', [
            'client_id' => $this->clientId, 'client_secret' => $this->clientSecret, 'redirect_uri' => $redirectUri, 'code' => $code, 'code_verifier' => $codeVerifier,
        ]);
        $tok = json_decode($r['body'], true);
        if ($r['status'] !== 200 || !is_string($tok['access_token'] ?? null)) { throw new \RuntimeException('OAUTH_TOKEN'); }
        $proof = hash_hmac('sha256', $tok['access_token'], $this->clientSecret);
        $me = $this->http->get('https://graph.facebook.com/' . $this->version . '/me?' . http_build_query(['fields' => 'id,name,email', 'appsecret_proof' => $proof]), ['Authorization: Bearer ' . $tok['access_token']]);
        $u = json_decode($me['body'], true);
        if ($me['status'] !== 200 || !is_array($u) || !is_string($u['id'] ?? null) || !preg_match('/^\d{1,30}$/', $u['id'])) { throw new \RuntimeException('OAUTH_PROFILE'); }
        $email = is_string($u['email'] ?? null) ? strtolower($u['email']) : null;
        return ['subject' => 'facebook:' . $u['id'], 'email' => $email, 'email_verified' => $email !== null, 'name' => is_string($u['name'] ?? null) ? mb_substr($u['name'], 0, 120) : null];
    }
}
