<section class="container page mi-deal-page">
    <div class="mi-page-heading narrow-copy">
        <span class="eyebrow">Deal Checker</span>
        <h1>ราคาที่เจออยู่ตรงไหนของตลาด?</h1>
        <p class="lead">เลือกสินค้า ใส่ราคาประกาศ และเพิ่มบริบทเท่าที่มี ระบบจะเปรียบเทียบกับช่วงราคาที่พบอย่างเป็นกลาง</p>
    </div>

    <div class="mi-deal-layout">
        <form method="post" class="card form mi-deal-form">
            <input type="hidden" name="_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">

            <?php foreach ($errors as $error): ?>
                <div class="alert"><?= htmlspecialchars($error) ?></div>
            <?php endforeach; ?>

            <label for="deal-product">สินค้า</label>
            <select id="deal-product" name="product_id" required>
                <option value="">เลือกรุ่นสินค้า</option>
                <?php foreach ($products as $p): ?>
                    <option value="<?= (int) $p['id'] ?>" <?= (string) ($_GET['product'] ?? '') === (string) $p['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($p['full_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="deal-price">ราคาที่พบ (บาท)</label>
            <input id="deal-price" type="number" name="user_price" min="1" max="10000000" step="1" value="<?= htmlspecialchars((string) ($_GET['user_price'] ?? '')) ?>" placeholder="เช่น 7900" required>

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
                <label class="check"><input type="checkbox" name="has_box"> มีกล่อง</label>
                <label class="check"><input type="checkbox" name="has_receipt"> มีใบเสร็จ</label>
                <label class="check"><input type="checkbox" name="has_benchmark"> มีผลทดสอบ</label>
            </fieldset>

            <label class="check consent-row">
                <input type="checkbox" name="consent">
                ยินยอมให้นำข้อมูลที่ไม่ระบุตัวตนไปปรับปรุงดัชนีราคา
            </label>

            <button type="submit">ประเมินตำแหน่งราคา</button>
        </form>

        <aside class="mi-deal-help">
            <div class="card">
                <h2>ผลลัพธ์จะบอกอะไร</h2>
                <ul>
                    <li>ต่ำกว่าช่วงราคาที่พบ</li>
                    <li>อยู่ในช่วงตลาด</li>
                    <li>สูงกว่าช่วงที่พบเล็กน้อย</li>
                    <li>Premium asking price</li>
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
