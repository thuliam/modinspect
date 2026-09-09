<?php
declare(strict_types=1);

namespace App\Contracts;

interface HttpClientInterface
{
    /**
     * @param array<string,string> $headers
     * @return array{status:int,body:string}
     */
    public function postJson(string $url, array $payload, array $headers = [], int $timeoutSeconds = 10): array;
}
