</main>
<footer class="site-footer">
    <div class="container">
        <div class="row footer-grid">
            <div class="col-lg-4 mb-4">
                <a class="brand" href="<?= $base ?>/" aria-label="modinpect home"><?= mi_logo($base) ?></a>
                <p class="mt-3">เช็กราคา เช็กสภาพ ก่อนซื้อขายคอมมือสอง</p>
                <p class="small">ข้อมูลเป็นราคาประกาศมือสองที่ผ่านการคัดกรอง ไม่ใช่ราคาปิดดีลหรือคำรับรองมูลค่าสุดท้าย</p>
            </div>
            <div class="col-6 col-lg-2 mb-4">
                <h4 aria-level="2">เมนูหลัก</h4>
                <ul class="footer-links">
                    <li><a href="<?= $base ?>/">หน้าแรก</a></li>
                    <li><a href="<?= $base ?>/price">เช็กราคา</a></li>
                    <li><a href="<?= $base ?>/deal-checker">Deal Checker</a></li>
                    <li><a href="<?= $base ?>/methodology">วิธีคำนวณ</a></li>
                </ul>
            </div>
            <div class="col-6 col-lg-3 mb-4">
                <h4 aria-level="2">หมวดสินค้า</h4>
                <ul class="footer-links">
                    <li><a href="<?= $base ?>/price?category=cpu">CPU</a></li>
                    <li><a href="<?= $base ?>/price?category=gpu">GPU</a></li>
                    <li><a href="<?= $base ?>/price?category=ram">RAM</a></li>
                    <li><a href="<?= $base ?>/price?category=storage">Storage</a></li>
                </ul>
            </div>
            <div class="col-lg-3 mb-4">
                <h4 aria-level="2">ความโปร่งใส</h4>
                <ul class="footer-links">
                    <li><a href="<?= $base ?>/methodology">ราคามาจากไหน</a></li>
                    <li><a href="<?= $base ?>/methodology">ข้อจำกัดของข้อมูล</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-copy">
            <span>© <?= date('Y') ?> modinpect</span>
            <button class="motion-toggle" id="motion-toggle" type="button" aria-pressed="false">เอฟเฟกต์การเคลื่อนไหว</button>
        </div>
    </div>
</footer>
<script src="<?= $base ?>/assets/admin/vendor/jquery/jquery-3.3.1.min.js"></script>
<script src="<?= $base ?>/assets/admin/vendor/bootstrap/js/bootstrap.bundle.js"></script>
<script src="<?= $base ?>/assets/spec-builder.js?v=<?= (int) @filemtime(ROOT_PATH . "/public/assets/spec-builder.js") ?>" defer></script>
<script src="<?= $base ?>/assets/site.js?v=<?= (int) @filemtime(ROOT_PATH . '/public/assets/site.js') ?>" defer></script>
</body>
</html>
