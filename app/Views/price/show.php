<?php $hasMarketSnapshot=!empty($product['median']) && (int)($product['valid_sample_size']??0)>0 && (int)($product['accepted_observation_count']??0)>0; $manifest=json_decode((string)($product['calculation_manifest'] ?? ''),true) ?: []; $confidence=$manifest['confidence'] ?? []; $components=$confidence['components'] ?? []; ?>
<section class="container product-page">
<div class="breadcrumbs"><?= htmlspecialchars($product['category_name']) ?> › <?= htmlspecialchars($product['brand_name']) ?> › <?= htmlspecialchars($product['model_name']) ?></div>
<div class="product-heading">
    <div><h1><?= htmlspecialchars($product['full_name']) ?></h1><div class="spec-chips"><span><?= htmlspecialchars($product['generation']??'HARDWARE') ?></span><span><?= htmlspecialchars($product['spec_summary']??'') ?></span></div></div>
    <div class="heading-actions">
        <button class="ghost disabled-control" type="button" disabled><span class="material-symbols-outlined">bookmark</span> บันทึก — กำลังพัฒนา</button>
        <a class="button ghost" href="<?= $base ?>/compare/<?= $product['slug'] ?>"><span class="material-symbols-outlined">compare_arrows</span> เปรียบเทียบ</a>
        <button class="disabled-control" type="button" disabled><span class="material-symbols-outlined">share</span> แชร์ — กำลังพัฒนา</button>
    </div>
</div>

<?php if(!$hasMarketSnapshot): ?>
<div class="empty">
    <h2>ข้อมูลตลาดยังไม่เพียงพอ</h2>
    <p>สินค้านี้ยังไม่มีข้อมูลที่ผ่านการตรวจแล้วเพียงพอ จึงไม่แสดงตัวเลขตลาดเป็นความจริงปัจจุบัน</p>
    <?php if(!empty($product['median'])): ?><p class="muted">มีข้อมูลดิบ/ทดสอบในระบบ แต่ยังไม่ผ่านการยอมรับสำหรับ public price index</p><?php endif; ?>
</div>
<?php else: ?>
<div class="top-data-grid">
    <div class="card price-dashboard">
        <div class="price-top">
            <div><span class="meta">ราคาตลาดโดยประมาณ (MEDIAN)</span><div class="big-price">฿<?= number_format((float)$product['median']) ?> <small>มือสอง</small></div></div>
            <div><span class="confidence-chip">ความเชื่อมั่น: <?= htmlspecialchars(strtoupper((string)$product['confidence_label'])) ?></span><small>Snapshot <?= htmlspecialchars($product['last_calculated_at'] ?? '-') ?></small></div>
        </div>
        <div class="range-labels"><span>Q1 ฿<?= number_format((float)$product['q1']) ?></span><span>Q3 ฿<?= number_format((float)$product['q3']) ?></span></div>
        <div class="draft-distribution"><i></i></div>
        <div class="zone-labels"><span>ต่ำผิดปกติ</span><span>ช่วงตลาด</span><span>สูงเล็กน้อย</span><span>พรีเมียม</span></div>
        <div class="price-stats">
            <div><small>จำนวนตัวอย่าง</small><b><?= (int)$product['valid_sample_size'] ?> รายการ</b></div>
            <div><small>ช่วง Q3-Q1</small><b>฿<?= number_format((float)$product['q3']-(float)$product['q1']) ?></b></div>
            <div><small>ประเภทข้อมูล</small><b>Asking</b></div>
            <div><small>ความสด</small><b><?= number_format((float)($product['fresh_sample_ratio'] ?? 0),1) ?>%</b></div>
        </div>
        <?php if($confidence): ?><div class="notice">เหตุผล: <?= htmlspecialchars(implode(' · ',array_slice($confidence['reasons'] ?? [],0,4))) ?></div><?php endif; ?>
    </div>
    <div class="card trend-card">
        <div class="section-head"><h2>แนวโน้มราคา</h2><span>30 วันล่าสุด</span></div>
        <?php if($history): ?><div class="bar-chart"><?php foreach($history as $point): ?><i style="height:<?= max(8,min(100,(int)(((float)$point['median']/max(1,(float)$product['median']))*70))) ?>%"></i><?php endforeach; ?></div><?php else: ?><div class="empty">ยังไม่มี history เพียงพอ</div><?php endif; ?>
    </div>
</div>
<div class="middle-grid">
    <form class="dark-deal" action="<?= $base ?>/deal-checker" method="get">
        <h2>เจอราคามาเท่าไหร่?</h2>
        <p>ส่งต่อไป Deal Checker เพื่อประเมินกับชุดข้อมูลราคาที่ผ่านการตรวจแล้ว</p>
        <input type="hidden" name="product" value="<?= (int)$product['id'] ?>">
        <label>ราคาที่พบ (THB)<input name="user_price" placeholder="เช่น 7900"></label>
        <button>เปิด Deal Checker</button>
    </form>
    <div class="card used-new">
        <h2>ขอบเขตข้อมูล</h2>
        <p>ดัชนีนี้สะท้อนราคาประกาศมือสองที่ผ่าน human review แล้ว ไม่ใช่ราคาปิดดีลจริงหรือราคาของใหม่</p>
        <?php if($components): ?><div class="notice">Confidence v1: sample <?= number_format((float)($components['sample']['score'] ?? 0),0) ?> · freshness <?= number_format((float)($components['freshness']['score'] ?? 0),0) ?> · sources <?= number_format((float)($components['source_diversity']['score'] ?? 0),0) ?> · evidence <?= number_format((float)($components['evidence_quality']['score'] ?? 0),0) ?></div><?php endif; ?>
        <div class="notice">หากตัวอย่างน้อยหรือหลักฐานไม่สด ระบบจะลดความมั่นใจและไม่ auto-accept ข้อมูลใหม่</div>
    </div>
</div>
<div class="bottom-grid">
    <div class="card checklist"><h2>Checklist ก่อนโอนเงิน</h2><?php foreach(['ตรวจรุ่นให้ตรงกับประกาศ','ขอหลักฐาน Benchmark','เช็กประกันและ serial'] as $x): ?><div><i>✓</i><span><b><?= $x ?></b><small>ขอหลักฐานและตรวจสอบกับผู้ขายก่อนชำระเงิน</small></span></div><?php endforeach; ?></div>
    <div class="limitation"><i>!</i><div><h3>ข้อจำกัดของข้อมูล</h3><p>ข้อมูลราคาชุดนี้มาจากราคาตั้งขาย จำนวน <?= (int)$product['valid_sample_size'] ?> รายการ และยังต้องใช้ดุลยพินิจร่วมกับสภาพสินค้าและประกัน</p></div></div>
</div>
<?php endif; ?>
</section>
