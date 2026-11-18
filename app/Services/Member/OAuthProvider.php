<?php
declare(strict_types=1);

namespace App\Services\Member;

/** One login provider (Google, Microsoft, Facebook, Apple). Authorization-code flow with state, PKCE and, for OpenID Connect, a nonce. */
interface OAuthProvider
{
    public function key(): string;

    public function label(): string;

    public function authorizeUrl(string $redirectUri, string $state, string $codeChallenge, string $nonce): string;

    /** @return array{subject:string,email:?string,email_verified:bool,name:?string} @throws \RuntimeException with a short reason code */
    public function exchange(string $code, string $redirectUri, string $codeVerifier, string $nonce): array;
}
