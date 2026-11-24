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
                    <li><a href="<?= $base ?>/compare">เทียบรุ่น</a></li>
                    <li><a href="<?= $base ?>/condition-guide">เช็กสภาพ</a></li>
                    <li><a href="<?= $base ?>/deal-checker">Deal Checker</a></li>
                    <li><a href="<?= $base ?>/build">ประกอบคอม</a></li>
                    <li><a href="<?= $base ?>/methodology">วิธีคำนวณ</a></li>
                </ul>
            </div>
            <div class="col-6 col-lg-3 mb-4">
                <h4 aria-level="2">หมวดสินค้า</h4>
                <ul class="footer-links">
                    <li><a href="<?= $base ?>/price?category=cpu">CPU</a></li>
                    <li><a href="<?= $base ?>/price?category=gpu">GPU</a></li>
                    <li><a href="<?= $base ?>/price?category=motherboard">Motherboard</a></li>
                    <li><a href="<?= $base ?>/price?category=ram">RAM</a></li>
                    <li><a href="<?= $base ?>/price?category=storage">Storage</a></li>
                    <li><a href="<?= $base ?>/price?category=psu">PSU</a></li>
                    <li><a href="<?= $base ?>/price?category=case">Case</a></li>
                    <li><a href="<?= $base ?>/price?category=cooling">Cooling</a></li>
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
        </div>
    </div>
</footer>
<?php // PWA P2: one Bootstrap alert bar at the bottom, filled and shown by site.js (install offer, new version, offline note); hidden otherwise ?>
<div id="pwa-bar" class="fixed-bottom p-3 d-none" role="status" aria-live="polite"><div class="alert alert-dark mb-0 d-flex flex-wrap align-items-center justify-content-between">
    <span data-pwa-text></span>
    <span class="ml-auto"><button type="button" class="btn btn-sm btn-primary d-none" data-pwa-act></button> <button type="button" class="btn btn-sm btn-link" data-pwa-close aria-label="ปิด">ปิด</button></span>
</div></div>
<button type="button" id="to-top" class="mi-top" aria-label="กลับขึ้นด้านบน" title="กลับขึ้นด้านบน"><i class="fas fa-arrow-up" aria-hidden="true"></i></button>
<script src="<?= $base ?>/assets/admin/vendor/jquery/jquery-3.7.1.min.js"></script>
<script src="<?= $base ?>/assets/admin/vendor/bootstrap/js/bootstrap.bundle.js"></script>
<script src="<?= $base ?>/assets/admin/vendor/select2/select2.min.js"></script>
<script src="<?= $base ?>/assets/admin/vendor/select2/th.js"></script>
<script src="<?= $base ?>/assets/mi-select.js?v=<?= (int) @filemtime(ROOT_PATH . "/public/assets/mi-select.js") ?>" defer></script>
<script src="<?= $base ?>/assets/spec-builder.js?v=<?= (int) @filemtime(ROOT_PATH . "/public/assets/spec-builder.js") ?>" defer></script>
<script src="<?= $base ?>/assets/site.js?v=<?= (int) @filemtime(ROOT_PATH . '/public/assets/site.js') ?>" defer></script>
</body>
</html>
