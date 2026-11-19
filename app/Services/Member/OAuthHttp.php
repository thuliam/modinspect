<?php
declare(strict_types=1);

namespace App\Services\Member;

/** The two calls an OAuth login makes to a provider. Tests replace it with a fake; production uses CurlOAuthHttp. */
interface OAuthHttp
{
    /** @return array{status:int,body:string} */
    public function get(string $url, array $headers = []): array;

    /** @param array<string,string> $fields form-encoded body @return array{status:int,body:string} */
    public function postForm(string $url, array $fields, array $headers = []): array;
}
