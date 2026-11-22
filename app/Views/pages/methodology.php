<section class="container page narrow"><span class="eyebrow">ความโปร่งใส</span><h1>วิธีคำนวณและข้อจำกัด</h1><p class="lead">เราออกแบบให้ทุกตัวเลขมีที่มา และบอกตรงไปตรงมาเมื่อข้อมูลยังไม่เพียงพอ</p>
<div class="card prose"><h2>เราเก็บอะไร</h2><p>ราคาประกาศ รุ่นสินค้า สภาพ ประกัน เวลา แหล่งข้อมูล และหลักฐานเท่าที่จำเป็น โดยลดการเก็บข้อมูลส่วนบุคคล</p><h2>ราคาประกาศไม่ใช่ราคาขายจริง</h2><p>ดัชนี MVP สะท้อนราคาที่ผู้ขายตั้ง หากไม่มีหลักฐานการปิดดีล ระบบจะไม่เรียกว่า “ราคาขายจริง”</p><h2>ค่ากลางและช่วงตลาด</h2><p>ระบบใช้ Median เป็นค่ากลาง และ Q1–Q3 เป็นช่วงอ้างอิงหลัก เพราะทนต่อราคาสุดโต่งกว่าค่าเฉลี่ย</p><h2>การคัดกรอง</h2><p>รายการซ้ำ เงินมัดจำ เครื่องทั้งชุด สินค้าเสีย ประกาศรับซื้อ และสกุลเงินผิดจะถูกแยกออกจากดัชนี</p><h2>ความมั่นใจ</h2><p>คำนวณจากจำนวนตัวอย่าง ความหลากหลายของแหล่งข้อมูล ความสด และคุณภาพหลักฐาน ไม่ได้อาศัยสีเพียงอย่างเดียว</p></div></section>

<?php
$mi_credit_file = ROOT_PATH . '/public/assets/products/CREDITS.json';
$mi_credits = is_file($mi_credit_file) ? (json_decode((string) file_get_contents($mi_credit_file), true) ?: []) : [];
if ($mi_credits): ?>
<section class="container page narrow" id="photo-credits">
    <div class="card prose">
        <h2>เครดิตภาพสินค้า</h2>
        <p>ภาพถ่ายสินค้าบางรายการมาจาก Wikimedia Commons ภายใต้สัญญาอนุญาตแบบเปิดตามที่ระบุ ภาพเป็นตัวอย่างของรุ่นเดียวกันหรือการ์ดของผู้ผลิตพันธมิตร ไม่ใช่ภาพของชิ้นที่ประกาศขาย</p>
        <button class="btn btn-sm btn-ghost" type="button" data-toggle="collapse" data-target="#photo-credit-list" aria-expanded="false" aria-controls="photo-credit-list">ดูรายการเครดิต</button>
        <div class="collapse mt-3" id="photo-credit-list"><ul>
            <?php foreach ($mi_credits as $slug => $c): ?>
                <li><a href="<?= htmlspecialchars((string) $c['page']) ?>" rel="noopener" target="_blank"><?= htmlspecialchars(str_replace('File:', '', (string) $c['file'])) ?></a>
                    · <?= htmlspecialchars((string) $c['license']) ?><?php if (!empty($c['author'])): ?> · <?= htmlspecialchars(mb_substr((string) $c['author'], 0, 80)) ?><?php endif; ?><?php if (!empty($c['note'])): ?> (<?= htmlspecialchars((string) $c['note']) ?>)<?php endif; ?></li>
            <?php endforeach; ?>
        </ul></div>
    </div>
</section>
<?php endif; ?>
<?php
$mi_logo_file = ROOT_PATH . '/public/assets/brands/CREDITS.json';
$mi_logos = is_file($mi_logo_file) ? (json_decode((string) file_get_contents($mi_logo_file), true) ?: []) : [];
if ($mi_logos): ?>
<section class="container page narrow" id="logo-credits">
    <div class="card prose">
        <h2>เครดิตโลโก้ยี่ห้อ</h2>
        <p>โลโก้มาจาก Wikimedia Commons ตามสัญญาอนุญาตที่ระบุ เครื่องหมายการค้าเป็นของเจ้าของแต่ละราย ใช้เพื่อระบุยี่ห้อสินค้าเท่านั้น ไม่ได้หมายถึงการรับรอง</p>
        <button class="btn btn-sm btn-ghost" type="button" data-toggle="collapse" data-target="#logo-credit-list" aria-expanded="false" aria-controls="logo-credit-list">ดูรายการเครดิต</button>
        <div class="collapse mt-3" id="logo-credit-list"><ul>
            <?php foreach ($mi_logos as $slug => $c): ?>
                <li><a href="<?= htmlspecialchars((string) $c['page']) ?>" rel="noopener" target="_blank"><?= htmlspecialchars(str_replace('File:', '', (string) $c['file'])) ?></a> · <?= htmlspecialchars((string) $c['license']) ?></li>
            <?php endforeach; ?>
        </ul></div>
    </div>
</section>
<?php endif; ?>
<?php require ROOT_PATH . '/app/Views/components/next-step.php'; // LS-46: every page ends with a next step ?>
