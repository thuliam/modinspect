</main>
<footer class="site-footer">
    <div class="container footer-grid">
        <div class="footer-brand">
            <a class="brand" href="<?= $base ?>/" aria-label="ModInspect home">
                <svg class="brand-mark" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M3 6h12M9 12h12M3 18h12"/><circle cx="19" cy="6" r="1.6" fill="currentColor" stroke="none"/><circle cx="5" cy="12" r="1.6" fill="currentColor" stroke="none"/><circle cx="19" cy="18" r="1.6" fill="currentColor" stroke="none"/></svg>
                <span>ModInspect</span>
            </a>
            <p>เช็กราคา เช็กสภาพ ก่อนซื้อขายคอมมือสอง</p>
            <p>ข้อมูลเป็นราคาประกาศมือสองที่ผ่านการคัดกรอง ไม่ใช่ราคาปิดดีลหรือคำรับรองมูลค่าสุดท้าย</p>
            <a class="button" href="<?= $base ?>/deal-checker">เปิด Deal Checker</a>
        </div>
        <ul class="footer-links" aria-label="Footer navigation">
            <li><a href="<?= $base ?>/price">เช็กราคา</a></li>
            <li><a href="<?= $base ?>/deal-checker">Deal Checker</a></li>
            <li><a href="<?= $base ?>/methodology">วิธีคำนวณ</a></li>
        </ul>
    </div>
    <p class="container footer-copy">© <?= date('Y') ?> ModInspect</p>
</footer>
<script src="<?= $base ?>/assets/spec-builder.js" defer></script>
<script src="<?= $base ?>/assets/site.js?v=<?= (int) @filemtime(ROOT_PATH . '/public/assets/site.js') ?>" defer></script>
</body>
</html>
