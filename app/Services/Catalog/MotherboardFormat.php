<?php
declare(strict_types=1);

namespace App\Services\Catalog;

/**
 * LS-73: the one format of a motherboard product: brand + model, with socket, chipset, memory type and form factor as columns of `products`.
 * parse() returns only what a text states (a chipset is read from the model name only when it is one of the standard chipset names); check() refuses impossible
 * combinations (an AM5 board is DDR5 only, an AM4 board DDR4 only) and unknown spellings; form factors are normalised to ATX, Micro-ATX, Mini-ITX, E-ATX.
 */
final class MotherboardFormat
{
    public const SOCKETS = ['AM4', 'AM5', 'LGA1700', 'LGA1851', 'LGA1200', 'LGA1151', 'LGA1150', 'LGA2066', 'TR4', 'sTRX4', 'sTR5'];
    public const MEMORY = ['DDR3', 'DDR4', 'DDR5'];
    public const FORM_FACTORS = ['ATX', 'Micro-ATX', 'Mini-ITX', 'E-ATX'];
    /** socket => the only memory types its boards can have */
    private const SOCKET_MEMORY = ['AM4' => ['DDR4'], 'AM5' => ['DDR5'], 'LGA1851' => ['DDR5'], 'LGA1200' => ['DDR4'], 'LGA1151' => ['DDR4', 'DDR3']];

    public static function formFactor(string $raw): ?string
    {
        $k = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $raw) ?? '');
        return match ($k) { 'ATX' => 'ATX', 'MATX', 'MICROATX', 'MICROATX' => 'Micro-ATX', 'ITX', 'MINIITX', 'MITX' => 'Mini-ITX', 'EATX', 'EXTENDEDATX' => 'E-ATX', default => null };
    }

    /** the chipset a model name states, for example "TUF Gaming B450-PLUS II" -> B450, "PRO B650M-A WIFI" -> B650, "Z790-PLUS" -> Z790 */
    public static function chipsetFromName(string $text): ?string
    {
        if (preg_match('/\b([ABHQWXZ])(\d{2,3})(E)?(?=M?\b|M?[- ]|$)/i', $text, $m)) { return strtoupper($m[1]) . $m[2] . (isset($m[3]) ? 'E' : ''); }
        return null;
    }

    public static function check(array $f): ?string
    {
        $sock = (string) ($f['socket'] ?? ''); $mem = (string) ($f['memory_type'] ?? ''); $chip = (string) ($f['chipset'] ?? ''); $ff = (string) ($f['form_factor'] ?? '');
        if (!in_array($sock, self::SOCKETS, true)) { return 'socket must be one of ' . implode(', ', self::SOCKETS); }
        if (!in_array($mem, self::MEMORY, true)) { return 'memory_type must be DDR3, DDR4 or DDR5'; }
        if (isset(self::SOCKET_MEMORY[$sock]) && !in_array($mem, self::SOCKET_MEMORY[$sock], true)) { return "a $sock board cannot use $mem"; }
        if ($chip === '' || !preg_match('/^[ABHQWXZ]\d{2,3}E?$/', $chip)) { return 'chipset must look like B650, Z790 or X870E'; }
        if (!in_array($ff, self::FORM_FACTORS, true)) { return 'form_factor must be ATX, Micro-ATX, Mini-ITX or E-ATX'; }
        return null;
    }

    public static function summary(array $f): string
    {
        return implode(' · ', ['ซ็อกเก็ต ' . $f['socket'], 'ชิปเซ็ต ' . $f['chipset'], $f['memory_type'], $f['form_factor']]);
    }
}
