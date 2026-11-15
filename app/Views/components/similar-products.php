<?php
/** @var array $similar rows from Product::similar(); @var float $ref reference price; @var string $base */
if (!$similar) { return; }
?>
<ul class="mi-similar">
    <?php foreach ($similar as $s): $diff = (float)$ref > 0 ? ((float)$s['median'] - (float)$ref) / (float)$ref * 100 : 0; ?>
        <li>
            <a href="<?= $base ?>/price/<?= rawurlencode($s['slug']) ?>">
                <span class="mi-similar-name"><?= htmlspecialchars($s['full_name']) ?></span>
                <span class="mi-similar-why"><?= $s['same_brand'] ? 'ยี่ห้อเดียวกัน' : 'ราคาใกล้เคียง' ?> · ตัวอย่าง <?= (int)$s['valid_sample_size'] ?></span>
            </a>
            <span class="mi-similar-price"><b>฿<?= number_format((float)$s['median']) ?></b><small class="<?= $diff >= 0 ? 'up' : 'down' ?>"><?= $diff >= 0 ? '+' : '' ?><?= number_format($diff, 0) ?>%</small></span>
        </li>
    <?php endforeach; ?>
</ul>
