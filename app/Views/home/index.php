<?php
require_once ROOT_PATH . '/app/Views/components/hw-art.php';
$counts = $counts ?? ['models' => 0, 'aliases' => 0, 'priced' => 0, 'categories' => 0];
$cats = [
    ['href' => '/price?category=cpu', 'title' => 'CPU', 'sub' => 'ซีพียู', 'art' => 'cpu'],
    ['href' => '/price?category=gpu', 'title' => 'GPU', 'sub' => 'การ์ดจอ', 'art' => 'gpu'],
    ['href' => '/price?category=ram', 'title' => 'RAM', 'sub' => 'หน่วยความจำ', 'art' => 'ram'],
    ['href' => '/price?category=storage', 'title' => 'Storage', 'sub' => 'SSD / HDD', 'art' => 'ssd'],
    ['href' => '/deal-checker', 'title' => 'Deal Checker', 'sub' => 'เทียบราคาที่เจอ', 'icon' => 'fa-balance-scale'],
    ['href' => '/condition-guide', 'title' => 'เช็กสภาพ', 'sub' => 'คู่มือก่อนจ่ายเงิน', 'icon' => 'fa-clipboard-check'],
];
$why = [
    ['icon' => 'fa-tags', 'fa' => 'fa-tags', 'title' => 'ราคาที่คัดกรองแล้ว', 'text' => 'ตัดประกาศที่ผิดปกติออกก่อนคำนวณ แสดงช่วงราคาและราคากลาง'],
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
$faq = [
    ['ราคาที่แสดงเป็นราคาขายจริงหรือไม่', 'เป็นราคาประกาศขายมือสองที่ผ่านการตรวจสอบ ไม่ใช่ราคาปิดดีล และอาจต่างตามสภาพ ประกัน และอุปกรณ์ที่ให้มา'],
    ['ทำไมบางรุ่นไม่มีช่วงราคา', 'เมื่อข้อมูลที่ผ่านการตรวจสอบยังไม่พอ ระบบจะไม่แสดงช่วงราคา และไม่สร้างราคาตลาดปลอมขึ้นมา คุณยังเปิดดูสเปกและใช้ Deal Checker ได้'],
    ['ตัวเลขราคามาจากไหน', 'มาจากประกาศขายที่ตัดรายการผิดปกติออกก่อนคำนวณ แล้วแสดงช่วงราคา ราคากลาง จำนวนตัวอย่าง และความน่าเชื่อถือของข้อมูล วิธีคำนวณเต็มอยู่ในหน้าวิธีคำนวณ'],
    ['ต้องตรวจสภาพสินค้าเองหรือไม่', 'ต้องตรวจเสมอ ราคาในระบบบอกกรอบตลาดเท่านั้น ไม่แทนการดูสภาพ ประกัน และอุปกรณ์ประกอบของชิ้นที่คุณจะซื้อ ดูคู่มือเช็กสภาพก่อนจ่ายเงินได้ในเมนูเครื่องมือ'],
    ['ระบบแสดงข้อมูลผู้ขายหรือไม่', 'ไม่แสดงชื่อผู้ขาย ข้อความประกาศ หรือข้อมูลติดต่อ หลักฐานที่แสดงมีเฉพาะวันที่ ราคา และประเภทหลักฐาน'],
];
$catRows = [
    ['CPU', 'ซีพียู', 'cpu'], ['GPU', 'การ์ดจอ', 'gpu'], ['Motherboard', 'เมนบอร์ด', 'motherboard'], ['RAM', 'หน่วยความจำ', 'ram'],
    ['Storage', 'SSD และ HDD', 'storage'], ['PSU', 'พาวเวอร์ซัพพลาย', 'psu'], ['Case', 'เคสคอมพิวเตอร์', 'case'], ['Cooling', 'ชุดระบายความร้อน', 'cooling'],
];
$thMonths = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
$thDate = static function (string $d) use ($thMonths): string { $t = strtotime($d); return $t ? (int) date('j', $t) . ' ' . $thMonths[(int) date('n', $t) - 1] . ' ' . ((int) date('Y', $t) + 543) : ''; };
$articles = $articles ?? [];
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
    <?php // LS-38: wavy background (after the Aceternity wavy-background), CSS only: four sliding wave bands, the whole group colour-cycles through the RGB spectrum (ROG Aura Sync style) with a hue-rotate filter. Static lime when the visitor asks for reduced motion. ?>
    <div class="mi-waves" aria-hidden="true">
        <?php foreach ([[120, '#bbf246', 24, ''], [90, '#22d3ee', 30, ' mi-wave-rev'], [150, '#e879f9', 38, ''], [70, '#fb923c', 46, ' mi-wave-rev']] as $wi => $w):
            $d = 'M0 160'; for ($x = 0; $x < 2880; $x += 720) { $d .= ' C' . ($x + 180) . ' ' . (160 - $w[0]) . ' ' . ($x + 540) . ' ' . (160 + $w[0]) . ' ' . ($x + 720) . ' 160'; } ?>
            <svg class="mi-wave<?= $w[3] ?>" style="--c:<?= $w[1] ?>;--t:<?= $w[2] ?>s;--y:<?= 22 + $wi * 14 ?>%" viewBox="0 0 2880 320" preserveAspectRatio="none" focusable="false"><path d="<?= $d ?>"/></svg>
        <?php endforeach; ?>
    </div>
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
                <p class="mi-data-note reveal" style="--d:400ms">อยู่ระหว่างทดลอง: ถ้าข้อมูลราคาที่ผ่านการตรวจสอบยังไม่พอ ระบบจะแสดงหน้ารุ่นสินค้าโดยไม่สร้างราคาตลาดปลอม</p>
            </div>
            <div class="col-lg-7 mi-hero-art reveal" style="--d:150ms"><img src="<?= $base ?>/assets/brand/hero-hardware.png" fetchpriority="high" width="1026" height="436" alt="โน้ตบุ๊ก คอมพิวเตอร์ตั้งโต๊ะ การ์ดจอ SSD และจอภาพ (ภาพประกอบ)"></div>
        </div>
    </div>
</section>

<?php // LS-37: the page skeleton follows the KYMA reference (numbered sections, a label rail on the left, hairline-separated rows, a process line, an accordion, a closing call to action); content is only what this site can stand behind ?>
<section class="mi-strip-wrap" aria-label="ข้อมูลในระบบ">
    <div class="container">
        <ul class="mi-strip list-unstyled">
            <li><b><?= (int) $counts['models'] ?></b><span>รุ่นในฐานข้อมูล</span></li>
            <li><b><?= (int) $counts['aliases'] ?></b><span>ชื่อเรียกที่ระบบรู้จัก</span></li>
            <li><b><?= (int) $counts['categories'] ?></b><span>หมวดสินค้า</span></li>
            <li><b><?= (int) $counts['priced'] ?></b><span>รุ่นที่มีช่วงราคาอ้างอิง</span></li>
        </ul>
        <p class="mi-note">ตัวเลขนับจากฐานข้อมูลจริง ไม่ปัดขึ้น รุ่นที่ยังไม่มีข้อมูลที่ผ่านการตรวจสอบพอจะไม่แสดงราคา</p>
    </div>
</section>

<section class="mi-sec" id="about" aria-labelledby="h-about">
    <div class="container"><div class="row">
        <div class="col-lg-3 mi-rail"><span class="mi-idx">[02]</span><span class="mi-rail-name">หลักการ</span></div>
        <div class="col-lg-9">
            <h2 id="h-about">ดูราคาจากประกาศจริงที่ผ่านการตรวจสอบ ไม่ใช่ราคาที่เดาเอา</h2>
            <div class="row mi-principles">
                <?php foreach ($why as $i => $w): ?>
                    <div class="col-md-6"><div class="mi-principle"><b><?= sprintf('%02d', $i + 1) ?></b><div><h3><?= htmlspecialchars($w['title']) ?></h3><p><?= htmlspecialchars($w['text']) ?></p></div></div></div>
                <?php endforeach; ?>
            </div>
        </div>
    </div></div>
</section>

<section class="mi-sec" id="deal" aria-labelledby="h-deal">
    <div class="container"><div class="row">
        <div class="col-lg-3 mi-rail"><span class="mi-idx">[03]</span><span class="mi-rail-name">เช็กดีล</span></div>
        <div class="col-lg-9">
            <h2 id="h-deal">ราคาที่คุณเจอ แพงไปหรือเปล่า</h2>
            <p class="mi-lead">ใส่ราคาประกาศที่เจอ แล้วเทียบกับช่วงราคาตลาดอย่างเป็นกลาง ไม่ตัดสินผู้ขาย และไม่แทนการตรวจสภาพจริง</p>
            <form class="mi-deal-inline" action="<?= $base ?>/deal-checker" method="get">
                <label for="home-price">ราคาที่พบ (บาท)</label>
                <div class="input-group">
                    <input id="home-price" class="form-control" name="user_price" type="number" inputmode="numeric" min="1" max="10000000" step="1" placeholder="เช่น 7900" required>
                    <div class="input-group-append"><button class="btn btn-lime" type="submit">เปิด Deal Checker</button></div>
                </div>
            </form>
            <p class="mi-note"><a href="<?= $base ?>/read-listing">หรือวางประกาศทั้งข้อความให้ระบบอ่านรุ่นและราคา <i class="fas fa-arrow-right" aria-hidden="true"></i></a></p>
        </div>
    </div></div>
</section>

<section class="mi-sec" id="categories" aria-labelledby="h-cats">
    <div class="container"><div class="row">
        <div class="col-lg-3 mi-rail"><span class="mi-idx">[04]</span><span class="mi-rail-name">หมวดสินค้า</span></div>
        <div class="col-lg-9">
            <h2 id="h-cats">เลือกดูช่วงราคาตามหมวด</h2>
            <ul class="mi-table list-unstyled">
                <?php foreach ($catRows as $i => $r): ?>
                    <li><a class="mi-row" href="<?= $base ?>/price?category=<?= $r[2] ?>"><span class="mi-row-id"><?= sprintf('%02d', $i + 1) ?></span><strong><?= $r[0] ?></strong><span class="mi-row-sub"><?= $r[1] ?></span><span class="mi-row-go"><i class="fas fa-arrow-right" aria-hidden="true"></i><span class="sr-only">ดู <?= $r[0] ?></span></span></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div></div>
</section>

<section class="mi-sec" id="how" aria-labelledby="h-how">
    <div class="container"><div class="row">
        <div class="col-lg-3 mi-rail"><span class="mi-idx">[05]</span><span class="mi-rail-name">ขั้นตอน</span></div>
        <div class="col-lg-9">
            <h2 id="h-how">ใช้งานง่าย 4 ขั้นตอน</h2>
            <ol class="mi-timeline list-unstyled">
                <?php foreach ($steps as $i => $s): ?>
                    <li><span class="mi-timeline-n">ขั้นที่ <?= $i + 1 ?></span><h3><?= htmlspecialchars($s['title']) ?></h3><p><?= htmlspecialchars($s['text']) ?></p></li>
                <?php endforeach; ?>
            </ol>
        </div>
    </div></div>
</section>

<section class="mi-sec" id="featured" aria-labelledby="h-feat">
    <div class="container"><div class="row">
        <div class="col-lg-3 mi-rail"><span class="mi-idx">[06]</span><span class="mi-rail-name">รุ่นแนะนำ</span></div>
        <div class="col-lg-9">
            <div class="mi-head">
                <h2 id="h-feat">รุ่นที่คนค้นหาบ่อย</h2>
                <div class="mi-tabs" role="group" aria-label="หมวดสินค้า">
                    <button type="button" class="active" data-filter="all" aria-pressed="true">ทั้งหมด</button>
                    <?php foreach (array_unique(array_filter(array_column($featured, 'category_name'))) as $cn): ?>
                        <button type="button" data-filter="<?= htmlspecialchars(strtolower($cn)) ?>" aria-pressed="false"><?= htmlspecialchars($cn) ?></button>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php if ($featured): ?>
                <?php if ((int) $counts['priced'] === 0): ?><p class="mi-note mi-featured-note"><i class="fas fa-info-circle" aria-hidden="true"></i> ยังไม่มีรุ่นที่มีราคาตลาดผ่านการตรวจสอบ ระบบจึงไม่แสดงราคา เปิดดูสเปกและใช้ Deal Checker ได้</p><?php endif; ?>
                <div class="row mi-featured">
                    <?php foreach (array_slice($featured, 0, 4) as $product): ?>
                        <div class="col-sm-6 mb-4" data-cat="<?= htmlspecialchars(strtolower((string) ($product['category_name'] ?? ''))) ?>">
                            <?php $artType = $artFor((string) ($product['category_name'] ?? '')); require ROOT_PATH . '/app/Views/components/product-card.php'; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="mi-note"><a href="<?= $base ?>/price">ดูรุ่นเพิ่มเติม <i class="fas fa-arrow-right" aria-hidden="true"></i></a></p>
            <?php else: ?>
                <div class="mi-empty-state"><h3>ข้อมูลตลาดยังไม่เพียงพอ</h3><p>ยังไม่มีสินค้าที่พร้อมแสดง ลองค้นหารุ่นที่ต้องการหรืออ่านวิธีคำนวณราคา</p></div>
            <?php endif; ?>
        </div>
    </div></div>
</section>

<section class="mi-sec mi-brands-block" id="brands" aria-labelledby="h-brands">
    <div class="container"><div class="row">
        <div class="col-lg-3 mi-rail"><span class="mi-idx">[07]</span><span class="mi-rail-name">ยี่ห้อ</span></div>
        <div class="col-lg-9">
            <h2 id="h-brands">ยี่ห้อที่มีในระบบ</h2>
            <div class="mi-brands">
                <?php foreach ($brands ?? [] as $b): ?>
                    <a class="mi-brand-tile" href="<?= $base ?>/price?q=<?= rawurlencode($b) ?>" aria-label="ดูรุ่นของ <?= $b ?>"><?= mi_brand_logo($b, $base, 28) ?></a>
                <?php endforeach; ?>
            </div>
            <p class="mi-note">เครื่องหมายการค้าเป็นของเจ้าของแต่ละราย ใช้เพื่อระบุยี่ห้อของสินค้าเท่านั้น ไม่ได้หมายถึงการรับรองหรือความเกี่ยวข้อง</p>
        </div>
    </div></div>
</section>

<?php if ($articles): ?>
<section class="mi-sec" id="articles" aria-labelledby="h-articles">
    <div class="container"><div class="row">
        <div class="col-lg-3 mi-rail"><span class="mi-idx">[08]</span><span class="mi-rail-name">บทความ</span></div>
        <div class="col-lg-9">
            <div class="mi-head"><h2 id="h-articles">คู่มือและเรื่องเล่าจากตลาดมือสอง</h2><a href="<?= $base ?>/blog">บทความทั้งหมด <i class="fas fa-arrow-right" aria-hidden="true"></i></a></div>
            <ul class="mi-table list-unstyled">
                <?php foreach ($articles as $a): $sum = trim((string) ($a['excerpt'] ?? '')) !== '' ? (string) $a['excerpt'] : \App\Services\Blog\BlogRenderer::summary((string) $a['body_head'], 120); ?>
                    <li><a class="mi-row mi-row-article" href="<?= $base ?>/blog/<?= rawurlencode((string) $a['slug']) ?>"><span class="mi-row-id"><?= $thDate((string) $a['published_at']) ?></span><strong><?= htmlspecialchars((string) $a['title']) ?></strong><span class="mi-row-sub"><?= htmlspecialchars($sum) ?></span><span class="mi-row-go"><i class="fas fa-arrow-right" aria-hidden="true"></i></span></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div></div>
</section>
<?php endif; ?>

<section class="mi-sec" id="faq" aria-labelledby="h-faq">
    <div class="container"><div class="row">
        <div class="col-lg-3 mi-rail"><span class="mi-idx"><?= $articles ? '[09]' : '[08]' ?></span><span class="mi-rail-name">คำถามที่พบบ่อย</span></div>
        <div class="col-lg-9">
            <h2 id="h-faq">ก่อนเริ่มใช้ ควรรู้อะไร</h2>
            <div class="mi-faq" id="faq-list">
                <?php foreach ($faq as $i => $q): ?>
                    <div class="mi-faq-item">
                        <h3><button class="mi-faq-q collapsed" type="button" data-toggle="collapse" data-target="#faq-<?= $i ?>" aria-expanded="false" aria-controls="faq-<?= $i ?>"><span class="mi-row-id"><?= sprintf('%02d', $i + 1) ?></span><span><?= htmlspecialchars($q[0]) ?></span><i class="fas fa-plus" aria-hidden="true"></i></button></h3>
                        <div id="faq-<?= $i ?>" class="collapse" data-parent="#faq-list"><p><?= htmlspecialchars($q[1]) ?></p></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="mi-note">ยังมีข้อสงสัย อ่านรายละเอียดทั้งหมดได้ที่ <a href="<?= $base ?>/methodology">วิธีคำนวณและข้อจำกัด</a></p>
        </div>
    </div></div>
</section>

<section class="mi-block pb-5">
    <div class="container">
        <div class="mi-cta reveal">
            <div class="mi-cta-art"><img src="<?= $base ?>/assets/brand/cta-hardware.png" width="600" height="280" alt="" loading="lazy"></div>
            <div class="mi-cta-copy"><h2>เจอราคาประกาศแล้วไม่แน่ใจ?</h2><p>เปรียบเทียบราคาที่คุณเจอกับช่วงราคาตลาดอย่างเป็นกลาง ไม่ตัดสินผู้ขาย และไม่แทนการตรวจสภาพจริง</p></div>
            <div class="mi-cta-act"><a class="btn btn-lime" href="<?= $base ?>/deal-checker"><i class="fas fa-balance-scale" aria-hidden="true"></i> เปิด Deal Checker</a><a class="btn btn-ghost" href="<?= $base ?>/read-listing"><i class="fas fa-paste" aria-hidden="true"></i> วางประกาศให้ระบบอ่าน</a></div>
        </div>
    </div>
</section>
