<?php $labels=['below_range'=>'ต่ำกว่าช่วงตลาด','in_range'=>'อยู่ในช่วงตลาด','above_range'=>'สูงกว่าช่วงตลาด','insufficient'=>'ข้อมูลไม่เพียงพอ']; ?>
<section class="container page narrow"><span class="eyebrow">DEAL RESULT</span><h1>ผลประเมิน <?= htmlspecialchars($deal['full_name']) ?></h1>
<div class="card result"><span class="badge"><?= htmlspecialchars($labels[$deal['result_label']]??$deal['result_label']) ?></span><div class="big-price">฿<?= number_format((float)$deal['user_price']) ?></div><p class="lead"><?= htmlspecialchars($deal['recommendation']) ?></p>
<div class="compare-row"><span>ช่วงอ้างอิง</span><b>฿<?= number_format((float)$deal['suggested_price_min']) ?>–<?= number_format((float)$deal['suggested_price_max']) ?></b></div><div class="compare-row"><span>ค่ากลาง</span><b>฿<?= number_format((float)$deal['median']) ?></b></div>
<div class="notice"><?= htmlspecialchars($deal['warning_note']??'') ?></div><a class="button" href="<?= $base ?>/price/<?= rawurlencode($deal['slug']) ?>">ดูข้อมูลรุ่นนี้</a></div></section>

