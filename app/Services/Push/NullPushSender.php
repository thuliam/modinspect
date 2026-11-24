<?php
declare(strict_types=1);
namespace App\Services\Push;

/** LS-86: sends nothing. Used until a real Web Push sender is approved; lets the planner and the job be run and tested end to end. */
final class NullPushSender implements PushSender
{
    public function send(array $subscription, string $title, string $body, string $url): string { return 'skipped'; }
}
