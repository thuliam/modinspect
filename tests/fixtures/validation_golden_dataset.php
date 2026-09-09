<?php
declare(strict_types=1);

return (static function (): array {
    $cases = [];
    $add = static function (
        string $category,
        string $title,
        string $priceText,
        ?string $expectedMarker,
        string $expectedListingType,
        string $expectedLane,
        array $expectedFlags = [],
        bool $criticalInvalid = false
    ) use (&$cases): void {
        $cases[] = [
            'id' => 'golden-' . str_pad((string)(count($cases) + 1), 3, '0', STR_PAD_LEFT),
            'category' => $category,
            'title' => $title,
            'price_text' => $priceText,
            'expected_product_marker' => $expectedMarker,
            'expected_listing_type' => $expectedListingType,
            'expected_lane' => $expectedLane,
            'expected_flags' => $expectedFlags,
            'critical_invalid' => $criticalInvalid,
        ];
    };

    $cpuValid = [
        ['5700x3d', 'AMD Ryzen 7 5700X3D มือสอง ใช้งานปกติ ประกัน %d เดือน', '฿%d'],
        ['5600', 'ขาย Ryzen 5 5600 AM4 มีกล่อง สภาพดี', '%d บาท'],
        ['5800x', 'R7 5800X used CPU only good condition', 'THB %d'],
        ['7800x3d', 'AMD 7800X3D สวย ใช้น้อย ประกันร้าน', '%d'],
        ['i512400f', 'Core i5 12400F ใช้งานปกติ', '%d'],
        ['i513600k', 'i5 13600K มือสอง กล่องครบ', '%d บาท'],
    ];
    foreach ($cpuValid as $i => [$marker, $template, $priceTemplate]) {
        for ($n = 0; $n < 4; $n++) {
            $add('cpu', sprintf($template, ($n + 1) * 2), sprintf($priceTemplate, 3000 + $i * 900 + $n * 150), $marker, 'single_item', 'green');
        }
    }

    foreach ([
        ['ขาย Ryzen 7 5700X ไม่ใช่ X3D ราคา 4300', '4300', null],
        ['AMD 5800X3D มือสอง ราคา 8200', '8200', null],
        ['Ryzen 5 5600G มี iGPU ราคา 2500', '2500', null],
        ['Intel 13600KF มือสอง ราคา 7300', '7300', null],
        ['Core i5 13400 ไม่มี F ราคา 5200', '5200', null],
        ['ขาย Ryzen 5600X CPU only 3100', '3100', null],
    ] as [$title, $price, $marker]) {
        $add('cpu', $title, $price, $marker, 'single_item', 'red', ['UNRESOLVED_PRODUCT'], true);
    }

    foreach ([
        ['Ryzen5700X3D + B550 bundle พร้อมบอร์ด', '10500', '5700x3d', ['BUNDLE'], false, 'bundle', 'amber'],
        ['CPU 5600 พร้อมบอร์ด A520 และแรม 16GB', '5200', '5600', ['BUNDLE'], false, 'bundle', 'amber'],
        ['รับซื้อ 5700x3d งบ 6000', '6000', '5700x3d', ['WANTED'], true, 'wanted', 'red'],
        ['ตามหา Ryzen 5 5600 งบ 2200', '2200', '5600', ['WANTED'], true, 'wanted', 'red'],
        ['จอง Ryzen 7 5800X มัดจำก่อน 500', '500', '5800x', ['DEPOSIT'], true, 'single_item', 'red'],
        ['ผ่อน 7800X3D ต่อเดือน 1200', '1200', '7800x3d', ['DEPOSIT'], true, 'single_item', 'red'],
        ['Core i5 12400F เปิดไม่ติด ขายเป็นอะไหล่', '1500', 'i512400f', ['DEFECTIVE'], true, 'single_item', 'red'],
        ['Ryzen 5 7600 for parts dead CPU', '1900', '7600', ['DEFECTIVE'], true, 'single_item', 'red'],
        ['ชุดคอม Ryzen 7 5700X3D RTX 3070 RAM 32GB SSD 1TB ทั้งเครื่อง', '25000', '5700x3d', ['WHOLE_PC'], true, 'whole_pc', 'red'],
        ['Gaming PC i5 13600K RTX 4070 RAM 32GB', '42000', 'i513600k', ['WHOLE_PC'], true, 'whole_pc', 'red'],
        ['Ryzen 5800X ราคา 4500 หรือรวมบอร์ด 7000', '4500 7000', '5800x', ['MULTIPLE_PRICES'], false, 'single_item', 'amber'],
        ['AMD 5700X3D ราคา 7000 ต่อรองเหลือ 6800', '7000 6800', '5700x3d', ['MULTIPLE_PRICES'], false, 'single_item', 'amber'],
    ] as [$title, $price, $marker, $flags, $critical, $type, $lane]) {
        $add('cpu', $title, $price, $marker, $type, $lane, $flags, $critical);
    }

    $gpuValid = [
        ['rtx3070', 'ขาย ASUS TUF RTX 3070 8GB ใช้งานได้ดี', '฿%d'],
        ['rtx3060ti', 'RTX3060Ti 8G สวย ประกันร้าน', '%d บาท'],
        ['rtx4070', 'NVIDIA RTX 4070 12GB มือสอง กล่องครบ', '%d'],
        ['rtx4070ti', 'Gigabyte Gaming OC RTX4070Ti 12G used', 'THB %d'],
        ['rx6600xt', 'AMD Radeon RX 6600 XT มือสอง', '%d'],
        ['rx6700xt', 'RX6700XT 12G ใช้งานปกติ', '%d บาท'],
        ['rx7800xt', 'ZOTAC เอ้ย AMD RX 7800 XT 16GB มือสอง', '%d'],
    ];
    foreach ($gpuValid as $i => [$marker, $template, $priceTemplate]) {
        for ($n = 0; $n < 4; $n++) {
            $add('gpu', $template, sprintf($priceTemplate, 5000 + $i * 1800 + $n * 250), $marker, 'single_item', 'green');
        }
    }

    foreach ([
        ['RTX 3070 Ti 8GB มือสอง ราคา 9500', '9500', null, ['WRONG_PRODUCT']],
        ['RTX 4070 SUPER 12GB ราคา 19000', '19000', null, ['UNRESOLVED_PRODUCT']],
        ['RTX 4070 Ti SUPER ราคา 26000', '26000', null, ['WRONG_PRODUCT']],
        ['RX 7900 XTX ราคา 29000', '29000', null, ['UNRESOLVED_PRODUCT']],
    ] as [$title, $price, $marker, $flags]) {
        $add('gpu', $title, $price, $marker, 'single_item', 'red', $flags, true);
    }

    foreach ([
        ['RTX 3070 Laptop GPU ถอดจาก notebook', '5000', 'rtx3070', ['MOBILE_GPU'], true, 'single_item', 'red'],
        ['Notebook RTX4070 mobile GPU ราคา 21000', '21000', 'rtx4070', ['MOBILE_GPU'], true, 'single_item', 'red'],
        ['โน้ตบุ๊ก มี RTX 3060 Ti GPU ขายอะไหล่', '7500', 'rtx3060ti', ['MOBILE_GPU', 'DEFECTIVE'], true, 'single_item', 'red'],
        ['RTX 3070 + PSU 650W bundle', '8200', 'rtx3070', ['BUNDLE'], false, 'bundle', 'amber'],
        ['RX 6700 XT พร้อม PSU power supply', '9500', 'rx6700xt', ['BUNDLE'], false, 'bundle', 'amber'],
        ['ขายทั้งเครื่อง Ryzen 5700X3D RTX 3070 RAM 32GB', '25000', 'rtx3070', ['WHOLE_PC'], true, 'whole_pc', 'red'],
        ['คอมทั้งชุด i5 12400F RTX 4070 SSD 1TB', '36000', 'rtx4070', ['WHOLE_PC'], true, 'whole_pc', 'red'],
        ['รับซื้อ RTX 3070 งบ 6500', '6500', 'rtx3070', ['WANTED'], true, 'wanted', 'red'],
        ['WTB RX 6600 XT used Thailand', '4200', 'rx6600xt', ['WANTED'], true, 'wanted', 'red'],
        ['จอง RTX 4070 มัดจำ 1000 ก่อนรับของ', '1000', 'rtx4070', ['DEPOSIT'], true, 'single_item', 'red'],
        ['RX7800XT ดาวน์ 2000 ผ่อนต่อเดือน', '2000', 'rx7800xt', ['DEPOSIT'], true, 'single_item', 'red'],
        ['RTX 3070 artifact broken for parts', '3200', 'rtx3070', ['DEFECTIVE'], true, 'single_item', 'red'],
        ['RX6700XT เสีย เปิดไม่ติด ขายซ่อม', '3500', 'rx6700xt', ['DEFECTIVE'], true, 'single_item', 'red'],
        ['RTX 4070 ราคา 16000 หรือแลก RTX 3070 เพิ่มเงิน 5000', '16000 5000', 'rtx4070', ['MULTIPLE_PRICES', 'VARIANT_AMBIGUOUS'], false, 'single_item', 'amber'],
        ['ASUS ROG STRIX RTX 4070 Ti ประกันยาว', '24500', 'rtx4070ti', ['BOARD_PARTNER_CONTEXT'], false, 'single_item', 'green'],
        ['MSI Gaming X RTX 3060 Ti กล่องครบ', '7600', 'rtx3060ti', ['BOARD_PARTNER_CONTEXT'], false, 'single_item', 'green'],
        ['GALAX RTX 3070 8GB สวย', '7800', 'rtx3070', ['BOARD_PARTNER_CONTEXT'], false, 'single_item', 'green'],
    ] as [$title, $price, $marker, $flags, $critical, $type, $lane]) {
        $add('gpu', $title, $price, $marker, $type, $lane, $flags, $critical);
    }

    for ($i = 0; $i < 5; $i++) {
        $add('cpu', 'ขาย CPU มือสอง ไม่ระบุรุ่น ราคา ' . (2500 + $i * 100), (string)(2500 + $i * 100), null, 'single_item', 'red', ['UNRESOLVED_PRODUCT'], true);
        $add('gpu', 'ขายการ์ดจอ มือสอง ไม่ระบุรุ่น ราคา ' . (4500 + $i * 100), (string)(4500 + $i * 100), null, 'single_item', 'red', ['UNRESOLVED_PRODUCT'], true);
    }

    return $cases;
})();
