<?php
declare(strict_types=1);

namespace App\Services\Member;

/**
 * Which login providers are offered: a provider appears only when both its client id and secret are set in .env (HG-13, the owner creates the
 * apps). Apple is not offered: Sign in with Apple needs a paid Apple Developer membership (HG-15, zero-cost policy), so there is deliberately no
 * Apple class until the owner approves that cost.
 */
final class ProviderRegistry
{
    /** @param array<string,array{client_id?:string,client_secret?:string}> $cfg  @return array<string,OAuthProvider> */
    public static function fromConfig(array $cfg, OAuthHttp $http, ?string $cacheDir = null, string $facebookVersion = 'v19.0'): array
    {
        $ok = static fn(string $k): bool => trim((string)($cfg[$k]['client_id'] ?? '')) !== '' && trim((string)($cfg[$k]['client_secret'] ?? '')) !== '';
        $out = [];
        if ($ok('google')) { $out['google'] = OidcProvider::google($cfg['google']['client_id'], $cfg['google']['client_secret'], $http, $cacheDir); }
        if ($ok('microsoft')) { $out['microsoft'] = OidcProvider::microsoft($cfg['microsoft']['client_id'], $cfg['microsoft']['client_secret'], $http, $cacheDir); }
        if ($ok('facebook')) { $out['facebook'] = new FacebookProvider($cfg['facebook']['client_id'], $cfg['facebook']['client_secret'], $http, $facebookVersion); }
        return $out;
    }
}
