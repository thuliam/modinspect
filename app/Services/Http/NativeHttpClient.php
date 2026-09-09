<?php
declare(strict_types=1);

namespace App\Services\Http;

use App\Contracts\HttpClientInterface;

final class NativeHttpClient implements HttpClientInterface
{
    public function postJson(string $url, array $payload, array $headers = [], int $timeoutSeconds = 10): array
    {
        $headerLines = ['Content-Type: application/json'];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headerLines),
                'content' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'timeout' => $timeoutSeconds,
                'ignore_errors' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            throw new \RuntimeException('Provider request failed or timed out');
        }

        $status = 0;
        foreach ($http_response_header ?? [] as $line) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $line, $match)) {
                $status = (int)$match[1];
                break;
            }
        }

        return ['status' => $status, 'body' => $body];
    }
}
