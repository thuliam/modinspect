<section class="container page narrow"><div class="empty"><h1>ไม่พบหน้าที่ค้นหา</h1><p>ลิงก์อาจไม่ถูกต้อง หรือข้อมูลนี้ยังไม่เปิดเผย ลองค้นรุ่นที่ต้องการจากตรงนี้ได้เลย</p>
<?php // LS-17 B5: a dead end always offers a way on: search, the price index, the Deal Checker ?>
<form class="mi-search my-3" action="<?= $base ?>/price" method="get">
    <label class="sr-only" for="nf-search">ค้นหารุ่นสินค้า</label>
    <div class="input-group">
        <input id="nf-search" class="form-control h-auto" name="q" placeholder="เช่น RTX 3070 หรือ Ryzen 5 5600" required>
        <div class="input-group-append"><button class="btn btn-lime" type="submit">ค้นหา</button></div>
    </div>
</form>
<div class="mi-actions"><a class="button" href="<?= $base ?>/">กลับหน้าแรก</a><a class="button ghost" href="<?= $base ?>/price">ดูราคาทุกรุ่น</a><a class="button ghost" href="<?= $base ?>/deal-checker">Deal Checker</a></div>
</div></section>
