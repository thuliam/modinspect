<?php
declare(strict_types=1);

// LS-4 K-4: probe a deployed site. php cli/ops_probe.php --base=https://host   (exit 0 = all rules pass, 1 = failures, 2 = usage)
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\Ops\ProbeRunner;

$base = '';
foreach (array_slice($argv, 1) as $a) { if (str_starts_with($a, '--base=')) { $base = rtrim(substr($a, 7), '/'); } }
if (!preg_match('#^https?://#', $base)) { fwrite(STDERR, "Usage: php cli/ops_probe.php --base=https://host\n"); exit(2); }
$fetch = static function (string $path) use ($base): array {
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_FOLLOWLOCATION => false]);
    $body = (string)curl_exec($ch);
    $r = [(int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE), $body, (float)curl_getinfo($ch, CURLINFO_TOTAL_TIME)];
    curl_close($ch);
    return $r;
};
$res = (new ProbeRunner($fetch))->run();
echo ($res['ok'] ? 'OK' : 'FAIL') . " ({$res['checked']} requests)\n";
foreach ($res['failures'] as $f) { echo " - $f\n"; }
exit($res['ok'] ? 0 : 1);
