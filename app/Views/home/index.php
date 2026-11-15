<?php
require_once ROOT_PATH . '/app/Views/components/hw-art.php';
$counts = $counts ?? ['models' => 0, 'aliases' => 0, 'priced' => 0, 'categories' => 0];
$cats = [
    ['href' => '/price?category=cpu', 'title' => 'CPU', 'sub' => 'ซีพียู', 'art' => 'cpu'],
    ['href' => '/price?category=gpu', 'title' => 'GPU', 'sub' => 'การ์ดจอ', 'art' => 'gpu'],
    ['href' => '/price?category=ram', 'title' => 'RAM', 'sub' => 'หน่วยความจำ', 'art' => 'ram'],
    ['href' => '/price?category=storage', 'title' => 'Storage', 'sub' => 'SSD / HDD', 'art' => 'ssd'],
    ['href' => '/deal-checker', 'title' => 'Deal Checker', 'sub' => 'เทียบราคาที่เจอ', 'icon' => 'fa-balance-scale'],
    ['href' => '/methodology', 'title' => 'วิธีคำนวณ', 'sub' => 'ราคามาจากไหน', 'icon' => 'fa-calculator'],
];
$why = [
    ['icon' => 'fa-search-dollar', 'fa' => 'fa-tags', 'title' => 'ราคาที่คัดกรองแล้ว', 'text' => 'ตัดประกาศที่ผิดปกติออกก่อนคำนวณ แสดงช่วงราคาและราคากลาง'],
    ['icon' => 'fa-microchip', 'fa' => 'fa-microchip', 'title' => 'แยกรุ่นให้ชัด', 'text' => 'เช่น 3060 กับ 3060 Ti ไม่ปนกัน ไม่เฉลี่ยข้ามรุ่น'],
    ['icon' => 'fa-file-alt', 'fa' => 'fa-file-alt', 'title' => 'บอกที่มาของตัวเลข', 'text' => 'ดูจำนวนตัวอย่าง ความสดของข้อมูล และความน่าเชื่อถือ'],
    ['icon' => 'fa-shield-alt', 'fa' => 'fa-shield-alt', 'title' => 'พูดตรงเมื่อข้อมูลไม่พอ', 'text' => 'ไม่สร้างราคาตลาดปลอม ถ้ายังไม่พอจะบอกว่าไม่พอ'],
];
$steps = [
    ['fa' => 'fa-search', 'title' => 'ค้นหารุ่น', 'text' => 'พิมพ์ชื่อรุ่นหรือรหัสยอดนิยม'],
    ['fa' => 'fa-chart-line', 'title' => 'ดูช่วงราคา', 'text' => 'ช่วงราคาที่พบ ราคากลาง และจำนวนตัวอย่าง'],
    ['fa' => 'fa-balance-scale', 'title' => 'เช็กดีล', 'text' => 'ใส่ราคาที่เจอเพื่อเทียบกับตลาด'],
    ['fa' => 'fa-check-circle', 'title' => 'ตัดสินใจ', 'text' => 'ตรวจสภาพ ประกัน อุปกรณ์เอง แล้วค่อยซื้อ'],
];
$artFor = static fn(string $name): string => match (strtolower(trim($name))) { 'cpu' => 'cpu', 'gpu' => 'gpu', 'ram' => 'ram', default => 'ssd' };
// one row of five, mixing categories so the row is not five copies of the same kind
$byCat = [];
foreach (($products ?? []) as $p) { $byCat[(string) ($p['category_name'] ?? '')][] = $p; }
$featured = [];
while (count($featured) < 5 && $byCat) {
    foreach ($byCat as $k => $list) {
        $featured[] = array_shift($byCat[$k]);
        if (!$byCat[$k]) { unset($byCat[$k]); }
        if (count($featured) >= 5) { break; }
    }
}
?>
<section class="mi-hero">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-5 mi-hero-copy">
                <h1 class="reveal">เช็กราคา<br><em>อุปกรณ์ IT มือสอง</em></h1>
                <p class="mi-sub reveal" style="--d:100ms">ค้นหา <i>•</i> เทียบ <i>•</i> ตรวจสอบที่มา <i>•</i> ตัดสินใจได้คุ้มกว่า</p>
                <form class="mi-search reveal" style="--d:200ms" action="<?= $base ?>/price" method="get">
                    <label class="sr-only" for="home-search">ค้นหารุ่นสินค้า</label>
                    <div class="input-group">
                        <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-search" aria-hidden="true"></i></span></div>
                        <input id="home-search" class="form-control" name="q" placeholder="ค้นหา เช่น Ryzen 7 5700X3D หรือ RTX 3070" required>
                        <div class="input-group-append"><button class="btn btn-lime" type="submit">เช็กราคา</button></div>
                    </div>
                </form>
                <div class="mi-chips reveal" style="--d:300ms" aria-label="Search examples">
                    <span><i class="fas fa-fire" aria-hidden="true"></i> ยอดนิยม:</span>
                    <?php foreach (array_slice($featured, 0, 3) as $chip): ?>
                        <a href="<?= $base ?>/price?q=<?= rawurlencode((string) $chip['model_name']) ?>"><?= htmlspecialchars((string) $chip['model_name']) ?></a>
                    <?php endforeach; ?>
                    </div>
                <p class="mi-data-note reveal" style="--d:400ms">ยังเป็น POC: ถ้าข้อมูลราคาที่ผ่านการตรวจสอบยังไม่พอ ระบบจะแสดงหน้ารุ่นสินค้าโดยไม่สร้างราคาตลาดปลอม</p>
            </div>
            <div class="col-lg-7 mi-hero-art reveal" style="--d:150ms"><img src="<?= $base ?>/assets/brand/hero-hardware.png" width="1026" height="436" alt="โน้ตบุ๊ก คอมพิวเตอร์ตั้งโต๊ะ การ์ดจอ SSD และจอภาพ (ภาพประกอบ)"></div>
        </div>
    </div>
</section>

<section class="mi-block">
    <div class="container">
        <div class="mi-head"><h2><i class="mi-slash"></i>ค้นหาตามหมวดหมู่</h2><a href="<?= $base ?>/price">ดูหมวดหมู่ทั้งหมด <i class="fas fa-arrow-right" aria-hidden="true"></i></a></div>
        <div class="row mi-cats">
            <?php foreach ($cats as $i => $c): ?>
                <div class="col-6 col-md-4 col-xl-2 mb-3">
                    <a class="mi-cat reveal" style="--d:<?= $i * 70 ?>ms" href="<?= $base . $c['href'] ?>">
                        <span class="mi-cat-art"><?= isset($c['art']) ? mi_art_img($c['art'], $base) : '<i class="fas ' . $c['icon'] . '" aria-hidden="true"></i>' ?></span>
                        <strong><?= htmlspecialchars($c['title']) ?></strong>
                        <small><?= htmlspecialchars($c['sub']) ?></small>
                        <span class="mi-go"><i class="fas fa-chevron-right" aria-hidden="true"></i></span>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="mi-block">
    <div class="container">
        <div class="mi-head"><h2><i class="mi-slash"></i>ทำไมต้อง modinpect</h2></div>
        <div class="row">
            <?php foreach ($why as $i => $w): ?>
                <div class="col-md-6 col-xl-3 mb-3">
                    <div class="mi-why reveal" style="--d:<?= $i * 80 ?>ms">
                        <span class="mi-why-ico"><i class="fas <?= $w['fa'] ?>" aria-hidden="true"></i></span>
                        <div><h3><?= htmlspecialchars($w['title']) ?></h3><p><?= htmlspecialchars($w['text']) ?></p></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="mi-block mi-brands-block" aria-label="ยี่ห้อในระบบ">
    <div class="container">
        <div class="mi-head"><h2><i class="mi-slash"></i>ยี่ห้อที่มีในระบบ</h2></div>
        <div class="mi-brands">
            <?php foreach (['AMD', 'Intel', 'NVIDIA', 'ASUS', 'MSI', 'Gigabyte', 'ASRock', 'Corsair', 'Kingston', 'Samsung', 'WD', 'Cooler Master'] as $b): ?>
                <a class="mi-brand-tile" href="<?= $base ?>/price?q=<?= rawurlencode($b) ?>" aria-label="ดูรุ่นของ <?= $b ?>"><?= mi_brand_logo($b, $base, 28) ?></a>
            <?php endforeach; ?>
        </div>
        <p class="mi-note">เครื่องหมายการค้าเป็นของเจ้าของแต่ละราย ใช้เพื่อระบุยี่ห้อของสินค้าเท่านั้น ไม่ได้หมายถึงการรับรองหรือความเกี่ยวข้อง</p>
    </div>
</section>

<section class="mi-block" id="featured">
    <div class="container">
        <div class="mi-head">
            <h2><i class="mi-slash"></i>รุ่นแนะนำ</h2>
            <div class="mi-tabs" role="tablist" aria-label="หมวดสินค้า">
                <button type="button" class="active" data-filter="all">ทั้งหมด</button>
                <?php foreach (array_unique(array_filter(array_column($featured, 'category_name'))) as $cn): ?>
                    <button type="button" data-filter="<?= htmlspecialchars(strtolower($cn)) ?>"><?= htmlspecialchars($cn) ?></button>
                <?php endforeach; ?>
            </div>
            <a href="<?= $base ?>/price">ดูรุ่นเพิ่มเติม <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
        </div>
        <?php if ($featured): ?>
            <?php if ((int) $counts['priced'] === 0): ?><p class="mi-note mi-featured-note"><i class="fas fa-info-circle" aria-hidden="true"></i> ยังไม่มีรุ่นที่มีราคาตลาดผ่านการตรวจสอบ ระบบจึงไม่แสดงราคา เปิดดูสเปกและใช้ Deal Checker ได้</p><?php endif; ?>
            <div class="row mi-featured">
                <?php foreach ($featured as $product): ?>
                    <div class="col-sm-6 col-lg-4 col-xl-5ths mb-4" data-cat="<?= htmlspecialchars(strtolower((string) ($product['category_name'] ?? ''))) ?>">
                        <?php $artType = $artFor((string) ($product['category_name'] ?? '')); require ROOT_PATH . '/app/Views/components/product-card.php'; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="mi-empty-state"><h3>ข้อมูลตลาดยังไม่เพียงพอ</h3><p>ยังไม่มีสินค้าที่พร้อมแสดง ลองค้นหารุ่นที่ต้องการหรืออ่านวิธีคำนวณราคา</p></div>
        <?php endif; ?>
    </div>
</section>

<section class="mi-block">
    <div class="container">
        <div class="mi-head"><div><h2>How it works</h2><p class="mb-0">ใช้งานง่าย 4 ขั้นตอน</p></div></div>
        <div class="row mi-steps">
            <?php foreach ($steps as $i => $s): ?>
                <div class="col-md-6 col-xl-3 mb-3">
                    <div class="mi-step reveal" style="--d:<?= $i * 80 ?>ms"><b><?= $i + 1 ?></b><i class="fas <?= $s['fa'] ?>" aria-hidden="true"></i><div><h3><?= htmlspecialchars($s['title']) ?></h3><p><?= htmlspecialchars($s['text']) ?></p></div></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="mi-block">
    <div class="container">
        <div class="mi-metrics reveal">
            <h2><i class="mi-slash"></i>modinpect ในตัวเลข</h2>
            <div class="row text-center text-md-left">
                <div class="col-6 col-md-3"><b data-count="<?= (int) $counts['models'] ?>"><?= (int) $counts['models'] ?></b><span>รุ่นในฐานข้อมูล</span></div>
                <div class="col-6 col-md-3"><b data-count="<?= (int) $counts['aliases'] ?>"><?= (int) $counts['aliases'] ?></b><span>ชื่อเรียกที่ระบบรู้จัก</span></div>
                <div class="col-6 col-md-3"><b data-count="<?= (int) $counts['categories'] ?>"><?= (int) $counts['categories'] ?></b><span>หมวดสินค้า</span></div>
                <div class="col-6 col-md-3"><b data-count="<?= (int) $counts['priced'] ?>"><?= (int) $counts['priced'] ?></b><span>รุ่นที่มีราคาผ่านการตรวจสอบ</span></div>
            </div>
            <p class="mi-note">ตัวเลขนับจากฐานข้อมูลจริง ไม่ปัดขึ้น ถ้ายังเป็น 0 คือยังไม่มีข้อมูลที่ผ่านการตรวจสอบพอจะแสดงราคา</p>
        </div>
    </div>
</section>

<section class="mi-block pb-5">
    <div class="container">
        <div class="mi-cta reveal">
            <div class="mi-cta-art"><img src="<?= $base ?>/assets/brand/cta-hardware.png" width="600" height="280" alt="" loading="lazy"></div>
            <div class="mi-cta-copy"><h2>เจอราคาประกาศแล้วไม่แน่ใจ?</h2><p>เปรียบเทียบราคาที่คุณเจอกับช่วงราคาตลาดอย่างเป็นกลาง ไม่ตัดสินผู้ขาย และไม่แทนการตรวจสภาพจริง</p></div>
            <div class="mi-cta-act"><a class="btn btn-lime" href="<?= $base ?>/deal-checker"><i class="fas fa-balance-scale" aria-hidden="true"></i> เปิด Deal Checker</a><a class="btn btn-ghost" href="<?= $base ?>/price"><i class="fas fa-search" aria-hidden="true"></i> เลือกดูรุ่น</a></div>
        </div>
    </div>
</section>
