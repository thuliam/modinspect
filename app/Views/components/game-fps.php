<?php
/** LS-59: in-game FPS panel for CPU and GPU pages. $gameFps: null = not a CPU/GPU page (draw nothing), [] = no results yet, rows = results from Product::gameFps() */
use App\Services\Catalog\GameFps;
if (!isset($gameFps) || $gameFps === null) { return; }
$isGpu = strtolower((string) ($product['category_slug'] ?? '')) === 'gpu';
$groups = GameFps::group($gameFps);
$fmt = static fn(float $v): string => rtrim(rtrim(number_format($v, 1), '0'), '.');
$top = 0.0; foreach ($groups as $list) { foreach ($list as $r) { $top = max($top, (float) $r['avg_fps']); } }
$scale = max(60.0, ceil($top / 60) * 60);
$fid = 'fps' . bin2hex(random_bytes(3));
?>
<section class="card mi-fps-card mb-4" id="sec-fps" data-nav="เล่นเกม" data-icon="fa-gamepad" aria-labelledby="mi-fps-title">
    <div class="mi-section-head compact"><div><span class="eyebrow">ผลทดสอบเกม</span><h2 id="mi-fps-title">เล่นเกมได้กี่ FPS</h2></div></div>
<?php if (!$groups): ?>
    <p class="mi-fps-empty"><i class="fas fa-info-circle" aria-hidden="true"></i> ยังไม่มีผลทดสอบของรุ่นนี้ เราไม่ประมาณค่า FPS เอง จะแสดงเมื่อมีผลทดสอบจริงที่ระบุแหล่งที่มา <?= $isGpu ? 'และซีพียูที่ใช้คู่กัน' : 'และการ์ดจอที่ใช้คู่กัน' ?></p>
    <ul class="mi-fps-games list-unstyled">
        <?php foreach (GameFps::GAMES as $gl): ?><li><i class="fas fa-gamepad" aria-hidden="true"></i> <?= htmlspecialchars($gl) ?></li><?php endforeach; ?>
    </ul>
<?php else: $first = true; ?>
    <?php if (count($groups) > 1): ?>
        <ul class="nav mi-sim-tabs mb-3" role="tablist">
            <?php foreach ($groups as $res => $list): ?><li class="nav-item"><a class="nav-link<?= $first ? ' active' : '' ?>" data-toggle="pill" href="#<?= $fid . '-' . $res ?>" role="tab" aria-selected="<?= $first ? 'true' : 'false' ?>"><?= htmlspecialchars(GameFps::RESOLUTIONS[$res]) ?></a></li><?php $first = false; endforeach; $first = true; ?>
        </ul>
    <?php endif; ?>
    <div class="tab-content">
    <?php foreach ($groups as $res => $list): $sources = []; $sponsors = []; ?>
        <div class="tab-pane fade<?= $first ? ' show active' : '' ?>" id="<?= $fid . '-' . $res ?>" role="tabpanel"><?php $first = false; ?>
            <ul class="list-unstyled mi-stats mi-fps-list">
                <?php foreach ($list as $i => $r): $avg = (float) $r['avg_fps']; $sources[$r['source_url']] = [$r['source_name'], $r['license_note']]; if (!empty($r['sponsor_name'])) { $sponsors[$r['sponsor_name']] = true; } ?>
                    <li class="mi-stat" style="--i:<?= (int) $i ?>">
                        <span class="mi-stat-l"><?= htmlspecialchars(GameFps::GAMES[$r['game_key']]) ?><small><?= htmlspecialchars((string) $r['quality']) ?> · คู่กับ <?= htmlspecialchars((string) $r['paired_part']) ?></small></span>
                        <div class="progress mi-stat-bar"><div class="progress-bar" role="progressbar" style="width: <?= $fmt(min(100.0, $avg / $scale * 100)) ?>%" aria-valuenow="<?= $fmt($avg) ?>" aria-valuemin="0" aria-valuemax="<?= (int) $scale ?>" aria-label="<?= htmlspecialchars(GameFps::GAMES[$r['game_key']]) ?>"></div></div>
                        <b class="mi-stat-v"><?= $fmt($avg) ?><small> FPS</small><?php if ($r['low1_fps'] !== null): ?><small class="mi-fps-low">1% low <?= $fmt((float) $r['low1_fps']) ?></small><?php endif; ?></b>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="mi-trend-note">แหล่งที่มา: <?php $n = 0; foreach ($sources as $url => [$sn, $ln]): ?><?= $n++ ? ', ' : '' ?><a href="<?= htmlspecialchars((string) $url) ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars((string) $sn) ?></a> (<?= htmlspecialchars((string) $ln) ?>)<?php endforeach; ?><?php if ($sponsors): ?> · ทดสอบโดยได้รับการสนับสนุนจาก <?= htmlspecialchars(implode(', ', array_keys($sponsors))) ?><?php endif; ?></p>
        </div>
    <?php endforeach; ?>
    </div>
    <p class="mi-trend-note">FPS ขึ้นกับเครื่องที่ใช้ทดสอบ ไดรเวอร์ และฉากที่วัด ใช้เทียบรุ่นเป็นแนวทาง ไม่ใช่ค่ารับประกัน และไม่เกี่ยวกับการคำนวณราคา</p>
<?php endif; ?>
</section>
