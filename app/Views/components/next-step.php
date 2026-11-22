<?php /** LS-46: closing band for pages that would otherwise end without a next step; the same block as the end of the home page. Needs $base. */ ?>
<section class="mi-block pb-5">
    <div class="container">
        <div class="mi-cta reveal">
            <div class="mi-cta-art"><img src="<?= $base ?>/assets/brand/cta-hardware.png" width="600" height="280" alt="" loading="lazy"></div>
            <div class="mi-cta-copy"><h2>เจอราคาประกาศแล้วไม่แน่ใจ?</h2><p>เปรียบเทียบราคาที่คุณเจอกับช่วงราคาตลาดอย่างเป็นกลาง ไม่ตัดสินผู้ขาย และไม่แทนการตรวจสภาพจริง</p></div>
            <div class="mi-cta-act"><a class="btn btn-lime" href="<?= $base ?>/deal-checker"><i class="fas fa-balance-scale" aria-hidden="true"></i> เปิด Deal Checker</a><a class="btn btn-ghost" href="<?= $base ?>/price"><i class="fas fa-search" aria-hidden="true"></i> เลือกดูรุ่น</a></div>
        </div>
    </div>
</section>
