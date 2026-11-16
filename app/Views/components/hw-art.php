<?php
/**
 * Original hardware illustrations (SVG, no external images) for the public site.
 * mi_art('gpu'|'cpu'|'ram'|'ssd'|'laptop'|'tower'|'monitor'|'hero'). Gradients come from the shared <defs> in layouts/header.php.
 */
if (!function_exists('mi_art_fan')) {
    function mi_art_fan(float $x, float $y, float $r): string
    {
        $blades = '';
        for ($i = 0; $i < 7; $i++) {
            $blades .= '<path d="M0 0 C' . round($r * .15, 1) . ' ' . round(-$r * .35, 1) . ' ' . round($r * .55, 1) . ' ' . round(-$r * .8, 1) . ' ' . round($r * .15, 1) . ' ' . round(-$r * .92, 1) . ' C' . round(-$r * .1, 1) . ' ' . round(-$r * .6, 1) . ' ' . round(-$r * .1, 1) . ' ' . round(-$r * .25, 1) . ' 0 0Z" transform="rotate(' . ($i * 51.4) . ')" fill="url(#miLime)" opacity=".92"/>';
        }
        return '<g transform="translate(' . $x . ' ' . $y . ')"><circle r="' . $r . '" fill="#0c100e" stroke="#d4f139" stroke-opacity=".55" stroke-width="1.5"/>' . $blades . '<circle r="' . round($r * .16, 1) . '" fill="#0c100e" stroke="#d4f139" stroke-width="1"/></g>';
    }
}
if (!function_exists('mi_art_gpu')) {
    function mi_art_gpu(float $x = 0, float $y = 0, float $s = 1): string
    {
        return '<g transform="translate(' . $x . ' ' . $y . ') scale(' . $s . ')"><rect x="6" y="22" width="188" height="64" rx="8" fill="url(#miMetal)" stroke="#59625d"/><rect x="6" y="22" width="188" height="7" rx="3" fill="url(#miLime)" opacity=".85"/>'
            . mi_art_fan(56, 56, 24) . mi_art_fan(112, 56, 24) . mi_art_fan(162, 56, 17)
            . '<rect x="26" y="86" width="70" height="6" rx="2" fill="#8c938f"/></g>';
    }
}
if (!function_exists('mi_art_ssd')) {
    function mi_art_ssd(float $x = 0, float $y = 0, float $s = 1): string
    {
        return '<g transform="translate(' . $x . ' ' . $y . ') scale(' . $s . ')"><rect x="0" y="0" width="64" height="86" rx="7" fill="url(#miMetal)" stroke="#59625d"/><rect x="0" y="0" width="64" height="9" rx="4" fill="url(#miLime)"/><text x="32" y="50" fill="#e8ece9" font-family="Arial,sans-serif" font-size="15" font-weight="700" text-anchor="middle">SSD</text><path d="M12 68h40" stroke="#d4f139" stroke-width="2" stroke-linecap="round" opacity=".7"/></g>';
    }
}
if (!function_exists('mi_art_tower')) {
    function mi_art_tower(float $x = 0, float $y = 0, float $s = 1): string
    {
        return '<g transform="translate(' . $x . ' ' . $y . ') scale(' . $s . ')"><rect x="0" y="0" width="120" height="190" rx="9" fill="#121715" stroke="#59625d" stroke-width="1.5"/><rect x="8" y="8" width="104" height="174" rx="6" fill="#0a0d0c" stroke="#d4f139" stroke-opacity=".25"/>'
            . mi_art_fan(60, 42, 25) . mi_art_fan(60, 98, 25) . mi_art_fan(60, 154, 25) . '<rect x="0" y="0" width="120" height="190" rx="9" fill="url(#miGlow)" opacity=".5"/></g>';
    }
}
if (!function_exists('mi_art_monitor')) {
    function mi_art_monitor(float $x = 0, float $y = 0, float $s = 1): string
    {
        return '<g transform="translate(' . $x . ' ' . $y . ') scale(' . $s . ')"><rect x="0" y="0" width="190" height="112" rx="6" fill="#0b0e0d" stroke="#59625d" stroke-width="2"/><rect x="6" y="6" width="178" height="100" rx="3" fill="url(#miScreen)"/><path d="M6 106 L92 6 L130 6 L40 106Z" fill="#d4f139" opacity=".18"/><path d="M80 112h30l6 26H74z" fill="#1e2421"/><rect x="58" y="138" width="74" height="5" rx="2.5" fill="#59625d"/></g>';
    }
}
if (!function_exists('mi_art_laptop')) {
    function mi_art_laptop(float $x = 0, float $y = 0, float $s = 1): string
    {
        return '<g transform="translate(' . $x . ' ' . $y . ') scale(' . $s . ')"><path d="M24 0h152l10 100H14z" fill="#0b0e0d" stroke="#59625d" stroke-width="2"/><path d="M32 8h136l8 84H24z" fill="url(#miScreen)"/><path d="M4 100h192l-8 12H12z" fill="url(#miMetal)" stroke="#59625d"/><rect x="80" y="102" width="40" height="4" rx="2" fill="#6d7571"/></g>';
    }
}
if (!function_exists('mi_art_cpu')) {
    function mi_art_cpu(float $x = 0, float $y = 0, float $s = 1): string
    {
        return '<g transform="translate(' . $x . ' ' . $y . ') scale(' . $s . ')"><rect x="6" y="6" width="84" height="84" rx="7" fill="none" stroke="#8c938f" stroke-width="3" stroke-dasharray="2.6 4"/><rect x="14" y="14" width="68" height="68" rx="7" fill="url(#miMetal)" stroke="#59625d"/><rect x="28" y="28" width="40" height="40" rx="4" fill="#0c100e" stroke="#d4f139" stroke-width="1.5"/><text x="48" y="53" fill="#bbf246" font-family="Arial,sans-serif" font-size="13" font-weight="700" text-anchor="middle">CPU</text></g>';
    }
}
if (!function_exists('mi_art_ram')) {
    function mi_art_ram(float $x = 0, float $y = 0, float $s = 1): string
    {
        $chips = '';
        for ($i = 0; $i < 6; $i++) {
            $chips .= '<rect x="' . (18 + $i * 25) . '" y="14" width="19" height="18" rx="2" fill="#0c100e" stroke="#59625d"/>';
        }
        return '<g transform="translate(' . $x . ' ' . $y . ') scale(' . $s . ')"><rect x="0" y="0" width="176" height="46" rx="5" fill="url(#miMetal)" stroke="#59625d"/><rect x="0" y="0" width="176" height="6" rx="3" fill="url(#miLime)"/>' . $chips . '<path d="M6 40h164" stroke="#caa94a" stroke-width="3" stroke-dasharray="3 2"/></g>';
    }
}
if (!function_exists('mi_art')) {
    function mi_art(string $type, string $class = ''): string
    {
        $svg = static fn(string $vb, string $inner): string => '<svg class="mi-art ' . htmlspecialchars($class) . '" viewBox="' . $vb . '" role="img" aria-hidden="true" focusable="false">' . $inner . '</svg>';
        return match ($type) {
            'gpu' => $svg('0 0 200 110', mi_art_gpu()),
            'cpu' => $svg('0 0 96 96', mi_art_cpu()),
            'ram' => $svg('0 0 176 46', mi_art_ram()),
            'ssd', 'storage' => $svg('0 0 64 86', mi_art_ssd()),
            'laptop' => $svg('0 0 200 114', mi_art_laptop()),
            'tower' => $svg('0 0 120 190', mi_art_tower()),
            'monitor' => $svg('0 0 190 146', mi_art_monitor()),
            'hero' => $svg('0 0 640 340',
                '<ellipse cx="330" cy="318" rx="290" ry="14" fill="#d4f139" opacity=".12"/>'
                . mi_art_monitor(430, 70, 1.05) . mi_art_tower(300, 40, 1.15) . mi_art_laptop(20, 120, 1.3)
                . mi_art_gpu(250, 232, 1.2) . mi_art_ssd(500, 238, 0.9)),
            default => '',
        };
    }
}
if (!function_exists('mi_logo')) {
    /** Approved brand lockup (owner kit, references/04, cropped with black made transparent; geometry untouched). Single source for header and footer. */
    function mi_logo(string $base): string
    {
        $file = ROOT_PATH . '/public/assets/brand/modinpect-lockup-dark.png';
        return '<img class="mi-logo" src="' . $base . '/assets/brand/modinpect-lockup-dark.png?v=' . (int) @filemtime($file) . '" width="900" height="181" alt="modinpect">';
    }
}if (!function_exists('mi_art_img')) {
    /**
     * Category artwork: derived crops from the owner's approved reference where one exists (gpu, ram), otherwise the SVG drawing.
     * The crops are low resolution and temporary until proper renders are supplied. $alt is empty because the card text names the item.
     */
    function mi_art_img(string $type, string $base): string
    {
        $map = ['gpu' => 'cat-gpu.jpg', 'ram' => 'cat-ram.jpg'];
        if (isset($map[$type]) && is_file(ROOT_PATH . '/public/assets/brand/' . $map[$type])) {
            return '<img class="mi-art-img mi-art-' . $type . '" src="' . $base . '/assets/brand/' . $map[$type] . '?v=' . (int) @filemtime(ROOT_PATH . '/public/assets/brand/' . $map[$type]) . '"' . mi_img_dims(ROOT_PATH . '/public/assets/brand/' . $map[$type]) . ' alt="" loading="lazy" decoding="async">';
        }
        return mi_art($type);
    }
}
if (!function_exists('mi_img_dims')) {
    /** ' width="W" height="H"' of a local image (cached per file) so the browser reserves the space before the file loads; '' when unreadable. CSS keeps control of the rendered size. */
    function mi_img_dims(string $file): string
    {
        static $cache = [];
        if (!array_key_exists($file, $cache)) {
            $size = is_file($file) ? @getimagesize($file) : false;
            $cache[$file] = ($size && $size[0] > 0 && $size[1] > 0) ? ' width="' . (int) $size[0] . '" height="' . (int) $size[1] . '"' : '';
        }
        return $cache[$file];
    }
}
if (!function_exists('mi_product_photo')) {
    /**
     * Real photograph of the catalogue item when one is stored as public/assets/products/{slug}.jpg
     * (freely licensed files from Wikimedia Commons, credits in CREDITS.json and on the methodology page). Null when none.
     */
    function mi_product_photo(array $product, string $base, string $class = 'mi-photo'): ?string
    {
        $slug = (string) ($product['slug'] ?? '');
        $file = ROOT_PATH . '/public/assets/products/' . $slug . '.jpg';
        if ($slug === '' || !preg_match('/^[a-z0-9-]+$/', $slug) || !is_file($file)) {
            return null;
        }
        return '<img class="' . $class . '" src="' . $base . '/assets/products/' . $slug . '.jpg?v=' . (int) @filemtime($file) . '" alt="' . htmlspecialchars((string) ($product['full_name'] ?? '')) . ' (ภาพถ่ายประกอบ ดูเครดิตในหน้าวิธีคำนวณ)"' . mi_img_dims($file) . ' loading="lazy" decoding="async">';
    }
}
if (!function_exists('mi_fa')) {
    /** Maps the old Material Symbols names used by the module pages to Font Awesome 5 classes (no extra icon font is loaded). */
    function mi_fa(string $name): string
    {
        static $map = ['memory' => 'fa-microchip', 'developer_board' => 'fa-server', 'videogame_asset' => 'fa-gamepad', 'view_module' => 'fa-th-large', 'database' => 'fa-database', 'bolt' => 'fa-bolt',
            'inventory_2' => 'fa-box', 'toys' => 'fa-snowflake', 'monitor' => 'fa-desktop', 'verified' => 'fa-check-circle', 'filter_list' => 'fa-filter', 'check_circle' => 'fa-check-circle',
            'warning' => 'fa-exclamation-triangle', 'save' => 'fa-save', 'near_me' => 'fa-location-arrow', 'chevron_right' => 'fa-chevron-right', 'lock' => 'fa-lock', 'inventory' => 'fa-boxes'];
        return $map[$name] ?? 'fa-circle';
    }
}
if (!function_exists('mi_brand_logo')) {
    /** Manufacturer logo (public/assets/brands, credited on the methodology page) on a light chip, or the plain brand name when no file exists. */
    function mi_brand_logo(?string $brand, string $base, int $h = 20): string
    {
        $name = trim((string) $brand);
        if ($name === '') { return ''; }
        $key = strtolower(preg_replace('/[^a-z0-9]/i', '', $name));
        if ($key === 'generic') { return '<span>ทุกยี่ห้อ</span>'; }   // spec-class products (RAM, SSD, PSU) are not tied to a brand
        $alias = ['westerndigital' => 'wd', 'coolermaster' => 'cooler-master', 'microstarinternational' => 'msi', 'gigabytetechnology' => 'gigabyte', 'kingstontechnology' => 'kingston', 'nvidiacorporation' => 'nvidia'];
        $file = $alias[$key] ?? $key;
        $path = ROOT_PATH . '/public/assets/brands/' . $file . '.png';
        if (!preg_match('/^[a-z0-9-]+$/', $file) || !is_file($path)) { return '<span>' . htmlspecialchars($name) . '</span>'; }
        $size = @getimagesize($path);
        if ($size && $size[1] > 0 && $size[0] / $size[1] < 1.8) { $h = (int) round($h * 1.8); }   // stacked logos (mark above name) need more height to stay legible
        return '<span class="mi-brand-logo' . ($file === 'nvidia' ? ' is-dark' : '') . '"><img src="' . $base . '/assets/brands/' . $file . '.png?v=' . (int) @filemtime($path) . '"' . mi_img_dims($path) . ' alt="' . htmlspecialchars($name) . '" decoding="async" style="height:' . $h . 'px"></span>';
    }
}
