<?php
declare(strict_types=1);

namespace App\Services\Member;

/** HTTPS calls to the login providers: certificate checked, short timeouts, no redirects followed, nothing logged. */
final class CurlOAuthHttp implements OAuthHttp
{
    public function get(string $url, array $headers = []): array { return $this->send($url, null, $headers); }

    public function postForm(string $url, array $fields, array $headers = []): array
    {
        return $this->send($url, http_build_query($fields, '', '&', PHP_QUERY_RFC3986), array_merge(['Content-Type: application/x-www-form-urlencoded'], $headers));
    }

    private function send(string $url, ?string $body, array $headers): array
    {
        if (!str_starts_with($url, 'https://')) { throw new \RuntimeException('OAUTH_HTTPS_ONLY'); }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 10, CURLOPT_HTTPHEADER => array_merge(['Accept: application/json'], $headers),
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        ]);
        if ($body !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $body); }
        $res = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($res === false) { throw new \RuntimeException('OAUTH_NETWORK'); }
        return ['status' => $status, 'body' => (string)$res];
    }
}
