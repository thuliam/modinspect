<?php use App\Services\Catalog\GameFps; ?>
<?php ob_start(); ?>Game FPS<?php $dxT = ob_get_clean(); ob_start(); ?>In-game frame rates shown on CPU and GPU product pages. Reference information only, never a price input.<?php $dxD = ob_get_clean(); $dxA = ''; ?>
<?= mi_page_header($base, ['Content'], $dxT, $dxD, $dxA) ?>

<?php if (!$has_table): ?>
<div class="alert alert-warning">The table <code>game_fps_results</code> does not exist on this database. Apply migration 031 (owner order required) before adding results.</div>
<?php else: ?>
<form class="card mi-admin-card mb-3" method="post" action="<?= $base ?>/admin/game-fps/save">
    <input type="hidden" name="_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
    <div class="card-header"><h2>Add or update a result</h2></div>
    <div class="card-body">
        <div class="form-row">
            <div class="form-group col-md-6"><label for="gf-product">CPU / GPU model</label><select class="form-control" id="gf-product" name="product_id" required><option value="">Choose a model</option><?php foreach ($products as $p): ?><option value="<?= (int) $p['id'] ?>"><?= htmlspecialchars((string) $p['full_name']) ?></option><?php endforeach; ?></select></div>
            <div class="form-group col-md-3"><label for="gf-game">Game</label><select class="form-control" id="gf-game" name="game" required><?php foreach (GameFps::GAMES as $k => $l): ?><option value="<?= $k ?>"><?= htmlspecialchars($l) ?></option><?php endforeach; ?></select></div>
            <div class="form-group col-md-3"><label for="gf-res">Resolution</label><select class="form-control" id="gf-res" name="resolution" required><?php foreach (GameFps::RESOLUTIONS as $k => $l): ?><option value="<?= $k ?>"><?= htmlspecialchars($l) ?></option><?php endforeach; ?></select></div>
        </div>
        <div class="form-row">
            <div class="form-group col-md-3"><label for="gf-q">Quality preset</label><input class="form-control" id="gf-q" name="quality" maxlength="30" placeholder="High" required></div>
            <div class="form-group col-md-3"><label for="gf-avg">Average FPS</label><input class="form-control" id="gf-avg" name="avg_fps" inputmode="decimal" required></div>
            <div class="form-group col-md-3"><label for="gf-low">1% low FPS (optional)</label><input class="form-control" id="gf-low" name="low1_fps" inputmode="decimal"></div>
            <div class="form-group col-md-3"><label for="gf-date">Tested on (optional)</label><input class="form-control" id="gf-date" type="date" name="tested_on"></div>
        </div>
        <div class="form-row">
            <div class="form-group col-md-6"><label for="gf-pair">Paired part (the GPU for a CPU result, the CPU for a GPU result)</label><input class="form-control" id="gf-pair" name="paired_part" maxlength="120" required></div>
            <div class="form-group col-md-6"><label for="gf-sponsor">Sponsor (optional, shown publicly)</label><input class="form-control" id="gf-sponsor" name="sponsor_name" maxlength="80"></div>
        </div>
        <div class="form-row">
            <div class="form-group col-md-4"><label for="gf-sn">Source name</label><input class="form-control" id="gf-sn" name="source_name" maxlength="80" required></div>
            <div class="form-group col-md-5"><label for="gf-su">Source URL</label><input class="form-control" id="gf-su" type="url" name="source_url" maxlength="500" required></div>
            <div class="form-group col-md-3"><label for="gf-ln">Licence / permission</label><input class="form-control" id="gf-ln" name="license_note" maxlength="80" placeholder="CC BY 4.0" required></div>
        </div>
        <button class="btn btn-primary" type="submit">Save result</button>
        <small class="form-text text-muted">Bulk load: <code>php cli/game_fps_import.php --file=results.csv</code> (dry run; add <code>--apply</code> to store). The same rules apply.</small>
    </div>
</form>

<div class="card mi-table-card">
    <div class="card-header"><h2>Latest results (<?= count($rows) ?>)</h2></div>
    <div class="table-responsive">
        <table class="table table-hover mi-admin-table mb-0">
            <thead><tr><th>Model</th><th>Game</th><th>Resolution</th><th>Avg / 1% low</th><th>Paired with</th><th>Source</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= htmlspecialchars((string) $r['full_name']) ?></td>
                    <td><?= htmlspecialchars(GameFps::GAMES[$r['game_key']] ?? (string) $r['game_key']) ?></td>
                    <td><?= htmlspecialchars((string) $r['resolution']) ?> · <?= htmlspecialchars((string) $r['quality']) ?></td>
                    <td><?= rtrim(rtrim(number_format((float) $r['avg_fps'], 1), '0'), '.') ?><?= $r['low1_fps'] !== null ? ' / ' . rtrim(rtrim(number_format((float) $r['low1_fps'], 1), '0'), '.') : '' ?></td>
                    <td><?= htmlspecialchars((string) $r['paired_part']) ?></td>
                    <td><a href="<?= htmlspecialchars((string) $r['source_url']) ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars((string) $r['source_name']) ?></a><?= $r['sponsor_name'] ? ' · ' . htmlspecialchars((string) $r['sponsor_name']) : '' ?></td>
                    <td><?php if ($can_manage): ?><form method="post" action="<?= $base ?>/admin/game-fps/delete"><input type="hidden" name="_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>"><input type="hidden" name="result_id" value="<?= (int) $r['id'] ?>"><button class="btn btn-sm btn-outline-danger" type="submit">Delete</button></form><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="7" class="text-muted">No results yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
