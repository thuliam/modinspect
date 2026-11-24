<?php
declare(strict_types=1);
namespace App\Services\Push;

/** LS-86: provider boundary. A real sender (Web Push with VAPID) needs an encryption library the owner has not approved yet, so only the null sender exists. */
interface PushSender
{
    /** @param array{endpoint:string,p256dh:string,auth:string} $subscription @return 'sent'|'gone'|'failed'|'skipped' */
    public function send(array $subscription, string $title, string $body, string $url): string;
}
