<?php
declare(strict_types=1);

/**
 * Hero RGB asset builder (MODINSPECT-HERO-RGB-001).
 *
 * Input : design/hero-rgb/source.webp  (the approved 1774x887 hero picture, never modified)
 * Output: public/assets/brand/hero-rgb/
 *           original.webp      the approved picture, re-encoded for the web (fallback and reduced-motion picture)
 *           base.webp          neutral base: the lime light is desaturated, every detail and edge is kept
 *           <group>.webp       transparent lighting overlays (original colours, alpha = how lit the pixel is),
 *                              cropped to their own bounding box
 *           dim.webp           black overlay with the same alpha, used by Breathing mode
 *           layers.json        position of every cropped overlay in source pixels
 *
 * Run: php scripts/build_hero_rgb.php   (PHP 8.1+ with the GD extension and WebP support)
 *
 * How the light is found: a pixel is "lit" when its hue is lime (about 52-112 degrees), it is saturated
 * and not too dark. The weight is soft (smoothstep) so glows keep their natural falloff. Gold connectors
 * (hue below about 52) and the teal board (hue above about 112) are left alone.
 * Groups split the lit pixels with soft spatial weights that always sum to 1, so overlays never overlap twice.
 */

$root = dirname(__DIR__);
$src = $root . '/design/hero-rgb/source.webp';
$out = $root . '/public/assets/brand/hero-rgb';
if (!is_dir($out) && !mkdir($out, 0775, true)) { fwrite(STDERR, "cannot create $out\n"); exit(1); }

$im = imagecreatefromwebp($src);
if ($im === false) { fwrite(STDERR, "cannot read source\n"); exit(1); }
$W = imagesx($im); $H = imagesy($im);
echo "source {$W}x{$H}\n";

function sstep(float $a, float $b, float $x): float { if ($x <= $a) return 0.0; if ($x >= $b) return 1.0; $t = ($x - $a) / ($b - $a); return $t * $t * (3 - 2 * $t); }

// Group geometry (source pixels), measured from the image: the case fans sit in x 1500-1640,
// their centres are about y 275 / 435 / 595; the floor reflection starts near y 745.
const FAN_X0 = 1492, FAN_X1 = 1642, FAN_Y0 = 150, FAN_Y1 = 720;
const FAN_SPLIT_1 = 355, FAN_SPLIT_2 = 515, FAN_SOFT = 22;
const FLOOR_Y0 = 738, FLOOR_Y1 = 772;
const SCENE_L = [560, 860], SCENE_R = [1180, 1440];

$groups = ['fan-1', 'fan-2', 'fan-3', 'floor', 'scene-l', 'scene-c', 'scene-r'];
$layer = [];
foreach ($groups as $g) {
    $l = imagecreatetruecolor($W, $H);
    imagealphablending($l, false); imagesavealpha($l, true);
    imagefill($l, 0, 0, imagecolorallocatealpha($l, 0, 0, 0, 127));
    $layer[$g] = $l;
}
$dim = imagecreatetruecolor($W, $H);
imagealphablending($dim, false); imagesavealpha($dim, true);
imagefill($dim, 0, 0, imagecolorallocatealpha($dim, 0, 0, 0, 127));
$base = imagecreatetruecolor($W, $H);
imagealphablending($base, false);

$bbox = [];
foreach ($groups as $g) { $bbox[$g] = [$W, $H, -1, -1]; }
$lit = 0;

for ($y = 0; $y < $H; $y++) {
    $wFloor = sstep(FLOOR_Y0, FLOOR_Y1, $y);
    $fanY = sstep(FAN_Y0 - 20, FAN_Y0 + 20, $y) * (1 - sstep(FAN_Y1 - 20, FAN_Y1 + 20, $y));
    $f1y = 1 - sstep(FAN_SPLIT_1 - FAN_SOFT, FAN_SPLIT_1 + FAN_SOFT, $y);
    $f3y = sstep(FAN_SPLIT_2 - FAN_SOFT, FAN_SPLIT_2 + FAN_SOFT, $y);
    $f2y = max(0.0, 1 - $f1y - $f3y);
    for ($x = 0; $x < $W; $x++) {
        $c = imagecolorat($im, $x, $y);
        $r = ($c >> 16) & 255; $g = ($c >> 8) & 255; $b = $c & 255;
        $mx = max($r, $g, $b); $mn = min($r, $g, $b); $d = $mx - $mn;
        $s = $mx ? $d / $mx : 0.0; $v = $mx / 255;
        $hu = 0.0;
        if ($d > 0) {
            if ($mx === $r) { $hu = 60 * fmod(($g - $b) / $d, 6); }
            elseif ($mx === $g) { $hu = 60 * (($b - $r) / $d + 2); }
            else { $hu = 60 * (($r - $g) / $d + 4); }
            if ($hu < 0) { $hu += 360; }
        }
        $m = sstep(52, 66, $hu) * (1 - sstep(100, 114, $hu)) * sstep(0.22, 0.5, $s) * sstep(0.07, 0.24, $v);

        // neutral base: pull the lime out, keep luminance (and so every edge and texture)
        $luma = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
        $wn = min(1.0, max(0.0, ($m - 0.08) / 0.55));
        $nr = (int) round($r + ($luma - $r) * $wn);
        $ng = (int) round($g + ($luma - $g) * $wn);
        $nb = (int) round($b + ($luma - $b) * $wn);
        imagesetpixel($base, $x, $y, ($nr << 16) | ($ng << 8) | $nb);

        if ($m < 0.012) { continue; }
        $lit++;
        // spatial group weights (sum to 1)
        $inFan = sstep(FAN_X0 - 10, FAN_X0 + 10, $x) * (1 - sstep(FAN_X1 - 10, FAN_X1 + 10, $x)) * $fanY * (1 - $wFloor);
        $w = [
            'fan-1' => $inFan * $f1y, 'fan-2' => $inFan * $f2y, 'fan-3' => $inFan * $f3y,
            'floor' => $wFloor,
        ];
        $rest = max(0.0, 1 - $w['fan-1'] - $w['fan-2'] - $w['fan-3'] - $w['floor']);
        $wl = 1 - sstep(SCENE_L[0], SCENE_L[1], $x);
        $wr = sstep(SCENE_R[0], SCENE_R[1], $x);
        $w['scene-l'] = $rest * $wl; $w['scene-r'] = $rest * $wr; $w['scene-c'] = $rest * max(0.0, 1 - $wl - $wr);

        $maxA = 0.0;
        foreach ($w as $name => $wg) {
            $a = $m * $wg;
            if ($a < 0.01) { continue; }
            $maxA = max($maxA, $a);
            imagesetpixel($layer[$name], $x, $y, ($r << 16) | ($g << 8) | $b | ((int) round((1 - $a) * 127) << 24));
            $bb = &$bbox[$name];
            if ($x < $bb[0]) $bb[0] = $x; if ($y < $bb[1]) $bb[1] = $y; if ($x > $bb[2]) $bb[2] = $x; if ($y > $bb[3]) $bb[3] = $y;
            unset($bb);
        }
        imagesetpixel($dim, $x, $y, (int) round((1 - $m) * 127) << 24);
    }
}
echo "lit pixels: $lit\n";

$meta = ['width' => $W, 'height' => $H, 'layers' => []];
foreach ($groups as $g) {
    [$x0, $y0, $x1, $y1] = $bbox[$g];
    if ($x1 < 0) { echo "group $g is empty\n"; continue; }
    $cw = $x1 - $x0 + 1; $ch = $y1 - $y0 + 1;
    $crop = imagecrop($layer[$g], ['x' => $x0, 'y' => $y0, 'width' => $cw, 'height' => $ch]);
    imagesavealpha($crop, true);
    imagewebp($crop, "$out/$g.webp", 82);
    $meta['layers'][$g] = ['x' => $x0, 'y' => $y0, 'w' => $cw, 'h' => $ch, 'bytes' => filesize("$out/$g.webp")];
    echo sprintf("%-8s x%-5d y%-5d %4dx%-4d %7d bytes\n", $g, $x0, $y0, $cw, $ch, $meta['layers'][$g]['bytes']);
}
imagesavealpha($dim, true);
imagewebp($dim, "$out/dim.webp", 80);
imagewebp($base, "$out/base.webp", 86);
imagewebp($im, "$out/original.webp", 86);
foreach (['dim', 'base', 'original'] as $n) { $meta[$n . '_bytes'] = filesize("$out/$n.webp"); echo "$n " . $meta[$n . '_bytes'] . " bytes\n"; }
file_put_contents("$out/layers.json", json_encode($meta, JSON_PRETTY_PRINT) . "\n");

// debug preview (not shipped): lime pulled to blue on every overlay, to eyeball registration and bleed
if (($argv[1] ?? '') === '--preview') {
    $prev = imagecreatetruecolor($W, $H);
    imagecopy($prev, $base, 0, 0, 0, 0, $W, $H);
    imagealphablending($prev, true);
    foreach ($groups as $gi => $g) {
        if (!isset($meta['layers'][$g])) continue;
        $L = $meta['layers'][$g];
        $tmp = imagecreatefromwebp("$out/$g.webp");
        imagealphablending($tmp, false);
        // crude hue shift lime(68) -> by rotating channels per group, only for the preview
        for ($yy = 0; $yy < $L['h']; $yy++) for ($xx = 0; $xx < $L['w']; $xx++) {
            $p = imagecolorat($tmp, $xx, $yy); $a = ($p >> 24) & 127;
            $r = ($p >> 16) & 255; $g2 = ($p >> 8) & 255; $b = $p & 255;
            $rot = [[$b, $r, $g2], [$g2, $b, $r], [$r, $b, $g2]][$gi % 3];
            imagesetpixel($tmp, $xx, $yy, ($rot[0] << 16) | ($rot[1] << 8) | $rot[2] | ($a << 24));
        }
        imagealphablending($tmp, true);
        imagecopy($prev, $tmp, $L['x'], $L['y'], 0, 0, $L['w'], $L['h']);
    }
    imagepng($prev, sys_get_temp_dir() . '/hero_rgb_preview.png');
    echo "preview: " . sys_get_temp_dir() . "/hero_rgb_preview.png\n";
}
