<?php // LS-17 A2 (S-3): prefill from the query string only when the values are plain strings ("?product[]=x" raised a warning per product)
$preProduct = is_string($_GET['product'] ?? null) ? $_GET['product'] : ''; $prePrice = is_string($_GET['user_price'] ?? null) ? $_GET['user_price'] : ''; ?>
<section class="container page mi-deal-page">
    <div class="mi-page-heading narrow-copy">
        <span class="eyebrow">Deal Checker</span>
        <h1>ราคาที่เจออยู่ตรงไหนของตลาด?</h1>
        <p class="lead">เลือกสินค้า ใส่ราคาประกาศ และเพิ่มบริบทเท่าที่มี ระบบจะเปรียบเทียบกับช่วงราคาที่พบอย่างเป็นกลาง</p>
    </div>
    <ol class="mi-flow" aria-label="ขั้นตอน">
        <li class="active"><b>1</b><span>เลือกรุ่นและใส่ราคาที่เจอ</span></li>
        <li><b>2</b><span>เพิ่มบริบท สภาพ และอุปกรณ์ที่ได้</span></li>
        <li><b>3</b><span>ดูตำแหน่งราคาเทียบช่วงตลาด</span></li>
    </ol>

    <div class="mi-deal-layout">
        <form method="post" class="card form mi-deal-form">
            <input type="hidden" name="_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">

            <?php foreach ($errors as $error): ?>
                <div class="alert"><?= htmlspecialchars($error) ?></div>
            <?php endforeach; ?>

            <div class="mi-field">

                <label for="deal-product">สินค้า</label>

                <select id="deal-product" name="product_id" required>
                <option value="">เลือกรุ่นสินค้า</option>
                <?php $dealGroups = []; foreach ($products as $p) { $dealGroups[(string) ($p['category_name'] ?? 'อื่น ๆ')][] = $p; } ?>
                <?php foreach ($dealGroups as $groupName => $groupProducts): ?>
                <optgroup label="<?= htmlspecialchars($groupName) ?>">
                <?php foreach ($groupProducts as $p): ?>
                    <option value="<?= (int) $p['id'] ?>" <?= $preProduct === (string) $p['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($p['full_name']) ?>
                    </option>
                <?php endforeach; ?>
                </optgroup>
                <?php endforeach; ?>
            </select>

            </div>

                        <div class="mi-field">
                <label for="deal-price">ราคาที่พบ (บาท)</label>
                <div class="mi-ask-bar"><span class="mi-ask-ico" aria-hidden="true">฿</span><input id="deal-price" class="mi-ask-input" type="number" inputmode="numeric" name="user_price" min="1" max="10000000" step="1" value="<?= htmlspecialchars($prePrice) ?>" placeholder="เช่น 7900" required></div>
                <small class="mi-hint">ใส่ราคาที่ผู้ขายประกาศ ไม่ใช่ราคาที่ตกลงกันแล้ว</small>
            </div>

            <div class="two">
                <div>
                    <label for="condition-level">สภาพสินค้า</label>
                    <select id="condition-level" name="condition_level">
                        <option value="unknown">ไม่ระบุ</option>
                        <option value="like_new">เหมือนใหม่</option>
                        <option value="good">ดี</option>
                        <option value="fair">พอใช้</option>
                        <option value="poor">มีตำหนิ</option>
                    </select>
                </div>
                <div>
                    <label for="warranty-months">ประกันคงเหลือ (เดือน)</label>
                    <input id="warranty-months" type="number" name="warranty_months" min="0" max="120" placeholder="0">
                </div>
            </div>

            <fieldset class="mi-check-fieldset">
                <legend>หลักฐานและอุปกรณ์ที่มี</legend>
                <div class="mi-checks">
                <div class="custom-control custom-checkbox"><input type="checkbox" class="custom-control-input" id="deal-box" name="has_box"><label class="custom-control-label" for="deal-box">มีกล่อง</label></div>
                <div class="custom-control custom-checkbox"><input type="checkbox" class="custom-control-input" id="deal-receipt" name="has_receipt"><label class="custom-control-label" for="deal-receipt">มีใบเสร็จ</label></div>
                <div class="custom-control custom-checkbox"><input type="checkbox" class="custom-control-input" id="deal-bench" name="has_benchmark"><label class="custom-control-label" for="deal-bench">มีผลทดสอบ</label></div>
                </div>
            </fieldset>

            <div class="custom-control custom-checkbox consent-row"><input type="checkbox" class="custom-control-input" id="deal-consent" name="consent"><label class="custom-control-label" for="deal-consent">ยินยอมให้ใช้ข้อมูลที่ไม่ระบุตัวตนนี้เพื่อศึกษาการใช้งาน ไม่มีผลต่อราคากลางที่แสดงบนเว็บ</label></div>

            <button type="submit" class="btn btn-lime btn-block mi-submit">ประเมินตำแหน่งราคา</button>
        </form>

        <aside class="mi-deal-help">
            <div class="card mi-help-card">
                <h2>ผลลัพธ์จะบอกอะไร</h2>
                <ul>
                    <li>ต่ำกว่าช่วงราคาที่พบ (เล็กน้อย พอสมควร หรือมาก)</li>
                    <li>อยู่ในช่วงตลาด</li>
                    <li>สูงกว่าช่วงที่พบ (เล็กน้อย พอสมควร หรือมาก)</li>
                    <li>ที่มาของผล: ส่วนต่างจากราคากลางเป็นบาทและเปอร์เซ็นต์ พร้อมปัจจัยที่คุณกรอก เช่น ใบเสร็จ ประกัน สภาพ</li>
                    <li>ข้อมูลไม่เพียงพอ</li>
                </ul>
            </div>
            <div class="card">
                <h2>ควรดูอะไรเพิ่ม</h2>
                <p>สภาพจริง ประกัน อุปกรณ์ กล่อง ใบเสร็จ ผลทดสอบ และบริการหลังขายอาจทำให้ราคาสูงกว่าช่วงทั่วไปได้</p>
            </div>
        </aside>
    </div>
</section>

<?php require ROOT_PATH . '/app/Views/components/next-step.php'; // LS-56: every tool page ends with a next step ?>
