<?php
declare(strict_types=1);

/**
 * LS-14 review finding: member photos and seller-report evidence (bank slips carry account numbers) live under storage/uploads/. They must be
 * git-ignored like backups, otherwise a routine `git add -A` publishes them. Every private storage folder the app writes to is checked.
 */
function ok_pi(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }
$root = dirname(__DIR__);
foreach (['storage/uploads/listings/' . str_repeat('a', 32) . '/' . str_repeat('b', 24) . '.jpg', 'storage/uploads/reports/' . str_repeat('a', 32) . '/' . str_repeat('b', 24) . '.jpg',
          'storage/backups/dump.sql', 'storage/cache/oauth/jwks-google.json', 'storage/cache/ratelimit/x.json', 'storage/logs/app.log', 'storage/sessions/sess_x', 'storage/import/test/x.csv'] as $path) {
    exec('git -C ' . escapeshellarg($root) . ' check-ignore -q -- ' . escapeshellarg($path), $o, $code);
    ok_pi($code === 0, "$path must be git-ignored");
}
// nothing under storage/uploads is tracked
exec('git -C ' . escapeshellarg($root) . ' ls-files -- storage/uploads', $tracked);
ok_pi($tracked === [], 'No uploaded file is tracked: ' . implode(', ', $tracked));
echo "private_storage_ignore_test: PASS\n";
