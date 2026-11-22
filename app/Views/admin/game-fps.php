<?php use App\Services\Catalog\GameFps; ?>
<?php ob_start(); ?><?= htmlspecialchars(t('v.game_fps')) ?><?php $dxT = ob_get_clean(); ob_start(); ?><?= htmlspecialchars(t('v.in_game_frame_rates_shown_on_cpu_and')) ?><?php $dxD = ob_get_clean(); $dxA = ''; ?>
<?= mi_page_header($base, ['Content'], $dxT, $dxD, $dxA) ?>

<?php if (!$has_table): ?>
<div class="alert alert-warning"><?= htmlspecialchars(t('misc.gf_table_pre')) ?> <code>game_fps_results</code> <?= htmlspecialchars(t('misc.gf_table_post')) ?></div>
<?php else: ?>
<form class="card mi-admin-card mb-3" method="post" action="<?= $base ?>/admin/game-fps/save">
    <input type="hidden" name="_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
    <div class="card-header"><h2><?= htmlspecialchars(t('v.add_or_update_a_result')) ?></h2></div>
    <div class="card-body">
        <div class="form-row">
            <div class="form-group col-md-6"><label for="gf-product"><?= htmlspecialchars(t('v.cpu_gpu_model')) ?></label><select class="form-control" id="gf-product" name="product_id" required><option value=""><?= htmlspecialchars(t('v.choose_a_model')) ?></option><?php foreach ($products as $p): ?><option value="<?= (int) $p['id'] ?>"><?= htmlspecialchars((string) $p['full_name']) ?></option><?php endforeach; ?></select></div>
            <div class="form-group col-md-3"><label for="gf-game"><?= htmlspecialchars(t('v.game')) ?></label><select class="form-control" id="gf-game" name="game" required><?php foreach (GameFps::GAMES as $k => $l): ?><option value="<?= $k ?>"><?= htmlspecialchars($l) ?></option><?php endforeach; ?></select></div>
            <div class="form-group col-md-3"><label for="gf-res"><?= htmlspecialchars(t('v.resolution')) ?></label><select class="form-control" id="gf-res" name="resolution" required><?php foreach (GameFps::RESOLUTIONS as $k => $l): ?><option value="<?= $k ?>"><?= htmlspecialchars($l) ?></option><?php endforeach; ?></select></div>
        </div>
        <div class="form-row">
            <div class="form-group col-md-3"><label for="gf-q"><?= htmlspecialchars(t('v.quality_preset')) ?></label><input class="form-control" id="gf-q" name="quality" maxlength="30" placeholder="High" required></div>
            <div class="form-group col-md-3"><label for="gf-avg"><?= htmlspecialchars(t('v.average_fps')) ?></label><input class="form-control" id="gf-avg" name="avg_fps" inputmode="decimal" required></div>
            <div class="form-group col-md-3"><label for="gf-low"><?= htmlspecialchars(t('v.1_low_fps_optional')) ?></label><input class="form-control" id="gf-low" name="low1_fps" inputmode="decimal"></div>
            <div class="form-group col-md-3"><label for="gf-date"><?= htmlspecialchars(t('v.tested_on_optional')) ?></label><input class="form-control" id="gf-date" type="date" name="tested_on"></div>
        </div>
        <div class="form-row">
            <div class="form-group col-md-6"><label for="gf-pair"><?= htmlspecialchars(t('v.paired_part_the_gpu_for_a_cpu_result')) ?></label><input class="form-control" id="gf-pair" name="paired_part" maxlength="120" required></div>
            <div class="form-group col-md-6"><label for="gf-sponsor"><?= htmlspecialchars(t('v.sponsor_optional_shown_publicly')) ?></label><input class="form-control" id="gf-sponsor" name="sponsor_name" maxlength="80"></div>
        </div>
        <div class="form-row">
            <div class="form-group col-md-4"><label for="gf-sn"><?= htmlspecialchars(t('v.source_name')) ?></label><input class="form-control" id="gf-sn" name="source_name" maxlength="80" required></div>
            <div class="form-group col-md-5"><label for="gf-su"><?= htmlspecialchars(t('v.source_url')) ?></label><input class="form-control" id="gf-su" type="url" name="source_url" maxlength="500" required></div>
            <div class="form-group col-md-3"><label for="gf-ln"><?= htmlspecialchars(t('v.licence_permission')) ?></label><input class="form-control" id="gf-ln" name="license_note" maxlength="80" placeholder="CC BY 4.0" required></div>
        </div>
        <button class="btn btn-primary" type="submit"><?= htmlspecialchars(t('v.save_result')) ?></button>
        <small class="form-text text-muted"><?= htmlspecialchars(t('misc.gf_bulk_pre')) ?> <code>php cli/game_fps_import.php --file=results.csv</code> <?= htmlspecialchars(t('misc.gf_bulk_mid')) ?> <code>--apply</code> <?= htmlspecialchars(t('misc.gf_bulk_post')) ?></small>
    </div>
</form>

<div class="card mi-table-card">
    <div class="card-header"><h2><?= htmlspecialchars(t('v.latest_results')) ?><?= count($rows) ?>)</h2></div>
    <div class="table-responsive">
        <table class="table table-hover mi-admin-table mb-0">
            <thead><tr><th><?= htmlspecialchars(t('v.model')) ?></th><th><?= htmlspecialchars(t('v.game')) ?></th><th><?= htmlspecialchars(t('v.resolution')) ?></th><th><?= htmlspecialchars(t('v.avg_1_low')) ?></th><th><?= htmlspecialchars(t('v.paired_with')) ?></th><th><?= htmlspecialchars(t('v.source')) ?></th><th><?= htmlspecialchars(t('v.action')) ?></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= htmlspecialchars((string) $r['full_name']) ?></td>
                    <td><?= htmlspecialchars(GameFps::GAMES[$r['game_key']] ?? (string) $r['game_key']) ?></td>
                    <td><?= htmlspecialchars((string) $r['resolution']) ?> · <?= htmlspecialchars((string) $r['quality']) ?></td>
                    <td><?= rtrim(rtrim(number_format((float) $r['avg_fps'], 1), '0'), '.') ?><?= $r['low1_fps'] !== null ? ' / ' . rtrim(rtrim(number_format((float) $r['low1_fps'], 1), '0'), '.') : '' ?></td>
                    <td><?= htmlspecialchars((string) $r['paired_part']) ?></td>
                    <td><a href="<?= htmlspecialchars((string) $r['source_url']) ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars((string) $r['source_name']) ?></a><?= $r['sponsor_name'] ? ' · ' . htmlspecialchars((string) $r['sponsor_name']) : '' ?></td>
                    <td><?php if ($can_manage): ?><form method="post" action="<?= $base ?>/admin/game-fps/delete"><input type="hidden" name="_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>"><input type="hidden" name="result_id" value="<?= (int) $r['id'] ?>"><button class="btn btn-sm btn-outline-danger" type="submit"><?= htmlspecialchars(t('v.delete')) ?></button></form><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="7" class="text-muted"><?= htmlspecialchars(t('v.no_results_yet')) ?></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
