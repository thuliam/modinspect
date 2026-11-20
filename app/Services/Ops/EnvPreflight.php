<?php
declare(strict_types=1);

namespace App\Services\Ops;

/**
 * LS-7 N-4: pre-deploy check of a production .env (beta checklist C-4, zero-cost policy). Pure: takes parsed values and a little file context, returns named
 * checks. A check carries a name and a plain reason only; no configured value is ever returned, so the report is safe to paste.
 */
final class EnvPreflight
{
    private const SPEND_KEYS = ['MODINSPECT_LIVE_COLLECTION_ENABLED', 'MODINSPECT_PAID_PROVIDER_CALLS_ENABLED', 'GEMINI_PROVIDER_ENABLED'];

    /**
     * @param array<string,string> $env parsed .env values
     * @param array{file_mode?:?int,public_env_exists?:bool} $ctx file_mode: permission bits of the .env (null when unknown, e.g. Windows)
     * @return array{verdict:string,fail:int,warn:int,checks:list<array{name:string,status:string,reason:string}>}
     */
    public static function evaluate(array $env, array $ctx = []): array
    {
        $checks = [];
        $add = static function (string $name, string $status, string $reason) use (&$checks): void { $checks[] = ['name' => $name, 'status' => $status, 'reason' => $reason]; };
        $val = static fn(string $k): string => trim((string)($env[$k] ?? ''));
        $truthy = static fn(string $v): bool => in_array(strtolower($v), ['1', 'true', 'yes', 'on'], true);

        $add('APP_ENV', strtolower($val('APP_ENV')) === 'production' ? 'pass' : 'fail', strtolower($val('APP_ENV')) === 'production' ? 'APP_ENV is production' : 'APP_ENV must be set explicitly to production');
        $present = array_values(array_filter(['AUTH_IDLE_TIMEOUT_SECONDS', 'AUTH_ABSOLUTE_LIFETIME_SECONDS'], static fn(string $k): bool => array_key_exists($k, $env)));
        $add('AUTH_OVERRIDES', $present === [] ? 'pass' : 'fail', $present === [] ? 'no AUTH_ session overrides' : 'remove ' . implode(', ', $present) . ' (session lifetime overrides are for development)');

        $url = $val('APP_URL');
        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        $urlOk = str_starts_with(strtolower($url), 'https://') && $host !== '' && !in_array($host, ['localhost', '127.0.0.1', '::1'], true) && !str_ends_with($host, '.localhost') && !str_ends_with($host, '.test');
        $add('APP_URL', $urlOk ? 'pass' : 'fail', $urlOk ? 'APP_URL is an https address on a real host' : 'APP_URL must be https and not a local address');

        $on = array_values(array_filter(self::SPEND_KEYS, static fn(string $k): bool => $truthy($val($k))));
        $add('SPEND_SWITCHES', $on === [] ? 'pass' : 'fail', $on === [] ? 'live collection and paid provider switches are off' : 'turn off ' . implode(', ', $on) . ' (external spend target is 0 THB until the owner approves)');

        $add('DB_PASSWORD', $val('DB_PASSWORD') !== '' ? 'pass' : 'fail', $val('DB_PASSWORD') !== '' ? 'a database password is set' : 'the database password is empty');
        $add('DB_USERNAME', strtolower($val('DB_USERNAME')) !== 'root' ? 'pass' : 'warn', strtolower($val('DB_USERNAME')) !== 'root' ? 'the application does not use the root database user' : 'use a dedicated database user with only this database');
        $add('GEMINI_API_KEY', $val('GEMINI_API_KEY') === '' ? 'pass' : 'warn', $val('GEMINI_API_KEY') === '' ? 'no provider key on the host' : 'a provider key is present; remove it unless a live run is approved');

        // LS-14: the optional features. Half-configured is a failure (a provider that looks on but cannot work, or a switch on without its safeguard).
        $halfProviders = [];
        foreach (['GOOGLE' => ['MEMBER_GOOGLE_CLIENT_ID', 'MEMBER_GOOGLE_CLIENT_SECRET'], 'MICROSOFT' => ['MEMBER_MICROSOFT_CLIENT_ID', 'MEMBER_MICROSOFT_CLIENT_SECRET'], 'FACEBOOK' => ['MEMBER_FACEBOOK_APP_ID', 'MEMBER_FACEBOOK_APP_SECRET']] as $p => [$id, $sec]) {
            if (($val($id) === '') !== ($val($sec) === '')) { $halfProviders[] = $p; }
        }
        $add('MEMBER_PROVIDERS', $halfProviders === [] ? 'pass' : 'fail', $halfProviders === [] ? 'every sign-in provider has both its id and secret, or neither' : 'set both the id and the secret (or neither) for ' . implode(', ', $halfProviders));
        $checkOn = $truthy($val('SELLER_CHECK_ENABLED'));
        $keyOk = strlen($val('SELLER_CHECK_HMAC_KEY')) >= 32;
        $add('SELLER_CHECK', !$checkOn ? 'pass' : ($keyOk ? 'warn' : 'fail'), !$checkOn ? 'the seller check is off' : ($keyOk ? 'the seller check is ON: confirm the lawyer review (HG-14) is done' : 'SELLER_CHECK_ENABLED is on but SELLER_CHECK_HMAC_KEY is missing or shorter than 32 characters'));
        $add('SELLER_LISTINGS_PUBLIC', $truthy($val('SELLER_LISTINGS_PUBLIC')) ? 'warn' : 'pass', $truthy($val('SELLER_LISTINGS_PUBLIC')) ? 'approved seller listings are public: confirm HG-16 (marketplace decision)' : 'seller listings are not public');

        // LS-24 M5/M6: the community switches. A switch on without its safeguard fails; on with it, the owner is reminded what must be approved first.
        $repOn = $truthy($val('COMMUNITY_REPORTS_ENABLED')); $repKey = strlen($val('COMMUNITY_REPORTS_HMAC_KEY')) >= 32;
        $add('COMMUNITY_REPORTS', !$repOn ? 'pass' : ($repKey ? 'warn' : 'fail'), !$repOn ? 'community reports are off' : ($repKey ? 'community reports are ON: confirm the privacy wording and who moderates (docs/COMMUNITY_REPORTS_PRIVACY_DRAFT_TH.md)' : 'COMMUNITY_REPORTS_ENABLED is on but COMMUNITY_REPORTS_HMAC_KEY is missing or shorter than 32 characters'));
        $add('COMMUNITY_FORUM', $truthy($val('COMMUNITY_FORUM_ENABLED')) ? 'warn' : 'pass', $truthy($val('COMMUNITY_FORUM_ENABLED')) ? 'the community forum is ON: confirm the community rules, the privacy wording and who moderates' : 'the community forum is off');
        // what the host must be able to do (ctx comes from the CLI on the host: loaded extensions and writable folders)
        if (isset($ctx['extensions']) && is_array($ctx['extensions'])) {
            $must = array_values(array_filter(['mbstring', 'pdo_mysql', 'json'], static fn(string $e): bool => empty($ctx['extensions'][$e])));
            $add('PHP_EXTENSIONS', $must === [] ? 'pass' : 'fail', $must === [] ? 'the required PHP extensions are loaded' : 'enable the PHP extension(s): ' . implode(', ', $must));
            $nice = array_values(array_filter(['gd_freetype' => 'GD with FreeType (share cards)', 'zip' => 'zip (batch intake from a zip file)'], static fn(string $label, string $k): bool => empty($ctx['extensions'][$k]), ARRAY_FILTER_USE_BOTH));
            $add('PHP_OPTIONAL', $nice === [] ? 'pass' : 'warn', $nice === [] ? 'GD with FreeType and zip are available' : 'not available, the matching feature stays off: ' . implode(', ', $nice));
        }
        if (isset($ctx['writable']) && is_array($ctx['writable'])) {
            $bad = array_keys(array_filter($ctx['writable'], static fn($ok): bool => !$ok));
            $add('STORAGE_WRITABLE', $bad === [] ? 'pass' : 'fail', $bad === [] ? 'the folders the application writes to are writable' : 'make these folders writable by the web user: ' . implode(', ', $bad));
        }
        $add('ENV_FILE_LOCATION', empty($ctx['public_env_exists']) ? 'pass' : 'fail', empty($ctx['public_env_exists']) ? 'no .env inside the web root' : 'an .env file is inside the web root; move it out');
        $mode = $ctx['file_mode'] ?? null;
        if ($mode !== null) {
            $add('ENV_FILE_MODE', ($mode & 0007) === 0 ? 'pass' : 'warn', ($mode & 0007) === 0 ? 'the .env is not readable by other users' : 'the .env is readable by other users; use chmod 640 or 600');
        }

        $fail = count(array_filter($checks, static fn(array $c): bool => $c['status'] === 'fail'));
        $warn = count(array_filter($checks, static fn(array $c): bool => $c['status'] === 'warn'));
        return ['verdict' => $fail > 0 ? 'FAIL' : ($warn > 0 ? 'PASS_WITH_WARNINGS' : 'PASS'), 'fail' => $fail, 'warn' => $warn, 'checks' => $checks];
    }
}
