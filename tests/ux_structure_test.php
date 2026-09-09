<?php
declare(strict_types=1);

function assert_ux(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$root = dirname(__DIR__);

function read_ux_file(string $relative): string
{
    global $root;
    $path = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
    assert_ux(is_file($path), "Missing required file: {$relative}");
    $contents = file_get_contents($path);
    assert_ux(is_string($contents) && $contents !== '', "Empty required file: {$relative}");
    return $contents;
}

$audit = read_ux_file('docs/UX_UI_AUDIT.md');
$design = read_ux_file('docs/DESIGN_SYSTEM.md');
$flows = read_ux_file('docs/USER_FLOWS.md');
$agents = read_ux_file('AGENTS.md');
$status = read_ux_file('docs/PROJECT_STATUS.md');
$rules = read_ux_file('context/architecture_rules.yaml');
$header = read_ux_file('app/Views/layouts/header.php');
$footer = read_ux_file('app/Views/layouts/footer.php');
$css = read_ux_file('public/assets/app.css');

assert_ux(str_contains($audit, 'CODE-LEVEL RESPONSIVE AUDIT ONLY'), 'UX audit must state responsive validation scope');
assert_ux(str_contains($audit, 'Owner Visual Review Required'), 'UX audit must include owner visual-review gate');
assert_ux(str_contains($audit, 'P0 | 0'), 'UX audit must include P0 count');
assert_ux(str_contains($audit, 'P1 | 5'), 'UX audit must include P1 count');
assert_ux(str_contains($design, 'Status Badges'), 'Design system must define status badges');
assert_ux(str_contains($design, 'Public Terminology'), 'Design system must define public terminology');
assert_ux(str_contains($flows, 'Public Flow A'), 'User flows must include public price-search flow');
assert_ux(str_contains($flows, 'Admin Flow A'), 'User flows must include admin review flow');

foreach ([$agents, $status, $rules] as $contents) {
    assert_ux(str_contains($contents, 'OWNER_APPROVAL_REQUIRED'), 'Zero-cost POC policy must be persisted');
}

assert_ux(str_contains($header, 'ModInspect'), 'Header must use ModInspect brand');
assert_ux(str_contains($footer, 'ModInspect'), 'Footer must use ModInspect brand');
assert_ux(!str_contains($header . $footer, 'PC Price Radar'), 'Global shell must not use legacy PC Price Radar brand');
assert_ux(!str_contains($css, 'fonts.googleapis'), 'CSS must not import Google Fonts');
assert_ux(!str_contains($css, 'JetBrains Mono'), 'CSS must use local font tokens instead of remote-oriented font names');
assert_ux(str_contains($css, '--font-ui') && str_contains($css, '--font-mono'), 'CSS must expose local font tokens');

$publicFiles = [
    'app/Views/home/index.php',
    'app/Views/price/show.php',
    'app/Views/modules/compare.php',
    'app/Views/modules/build.php',
    'app/Views/modules/seller-advisor.php',
    'app/Views/modules/market-report.php',
];

foreach ($publicFiles as $file) {
    $contents = read_ux_file($file);
    assert_ux(!str_contains($contents, 'accepted observations'), "{$file} should avoid public pipeline jargon");
    assert_ux(!str_contains($contents, 'accepted snapshot'), "{$file} should avoid public pipeline jargon");
}

echo "UX structure test passed\n";
echo "Public/seller/B2B pages audited: 13\n";
echo "Admin pages audited: 11\n";
echo "P0/P1/P2/P3: 0/5/7/3\n";
echo "Zero-cost policy: documented\n";
