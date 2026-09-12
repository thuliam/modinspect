<?php
$items=$queue['items'] ?? [];
$filters=$filters ?? [];
$summary=$summary ?? ['by_dataset'=>[],'status_totals'=>[]];
$queryFor=function(array $overrides=[]) use ($filters): string {
    $query=array_merge($filters,$overrides);
    foreach($query as $key=>$value){
        if($value === null || $value === '' || ($key === 'page' && (int)$value === 1)) unset($query[$key]);
    }
    return http_build_query($query);
};
$selected=function(string $name,string $value) use ($filters): string {
    return (string)($filters[$name] ?? '') === $value ? 'selected' : '';
};
$short=function(mixed $value,int $limit=130): string {
    $text=trim((string)$value);
    return strlen($text)>$limit ? substr($text,0,$limit-3).'...' : $text;
};
$categoryIcon=function(string $category): string {
    return match(strtolower($category)){
        'cpu'=>'fa-microchip',
        'gpu'=>'fa-tv',
        'motherboard'=>'fa-server',
        'ram'=>'fa-memory',
        'storage'=>'fa-hdd',
        'psu'=>'fa-plug',
        'cooling'=>'fa-fan',
        default=>'fa-cube',
    };
};
$imageUrl=function(array $row) use ($base): ?string {
    $path=trim((string)($row['product_image_path'] ?? ''));
    if($path === '') return null;
    $relative=ltrim($path,'/');
    if(!is_file(ROOT_PATH.'/public/'.$relative)) return null;
    return $base.'/'.$relative;
};
$realPending=(int)($summary['by_dataset']['REAL']['pending'] ?? 0);
$mockPending=(int)($summary['by_dataset']['MOCK_TEST']['pending'] ?? 0);
$approved=(int)($summary['status_totals']['approved'] ?? 0);
$rejected=(int)($summary['status_totals']['rejected'] ?? 0);
$excluded=(int)($summary['status_totals']['excluded'] ?? 0);
$queuePath='/admin/review-queue'.($queryFor() ? '?'.$queryFor() : '');
?>
<div class="mi-page-header">
    <div>
        <span class="mi-breadcrumb">Admin / Market Data</span>
        <h1>Review Queue</h1>
        <p>Human review console defaults to REAL pending evidence. MOCK_TEST records remain accessible through filters.</p>
    </div>
    <a class="btn btn-outline-primary" href="<?= $base ?>/admin/review-analytics"><i class="fas fa-chart-bar mr-2"></i>Review Analytics</a>
</div>

<div class="row mi-metric-row">
    <div class="col-xl col-md-4 col-sm-6"><div class="card mi-metric-card mi-metric-warning"><span>REAL Pending</span><strong><?= $realPending ?></strong><small>Calibration review queue</small></div></div>
    <div class="col-xl col-md-4 col-sm-6"><div class="card mi-metric-card"><span>MOCK/Test Pending</span><strong><?= $mockPending ?></strong><small>Kept separate from REAL</small></div></div>
    <div class="col-xl col-md-4 col-sm-6"><div class="card mi-metric-card"><span>Approved</span><strong><?= $approved ?></strong><small>All datasets</small></div></div>
    <div class="col-xl col-md-4 col-sm-6"><div class="card mi-metric-card"><span>Rejected</span><strong><?= $rejected ?></strong><small>All datasets</small></div></div>
    <div class="col-xl col-md-4 col-sm-6"><div class="card mi-metric-card"><span>Excluded</span><strong><?= $excluded ?></strong><small>All datasets</small></div></div>
</div>

<form class="review-filter-panel" method="get" action="<?= $base ?>/admin/review-queue">
    <label>Dataset
        <select name="dataset">
            <option value="REAL" <?= $selected('dataset','REAL') ?>>REAL</option>
            <option value="MOCK_TEST" <?= $selected('dataset','MOCK_TEST') ?>>MOCK_TEST</option>
            <option value="UNKNOWN" <?= $selected('dataset','UNKNOWN') ?>>UNKNOWN</option>
            <option value="ALL" <?= $selected('dataset','ALL') ?>>All datasets</option>
        </select>
    </label>
    <label>Status
        <select name="status">
            <?php foreach(['pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected','excluded'=>'Excluded','all'=>'All statuses'] as $value=>$label): ?>
                <option value="<?= $value ?>" <?= $selected('status',$value) ?>><?= $label ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Validation
        <select name="lane">
            <option value="">All lanes</option>
            <?php foreach(['amber'=>'Amber','green'=>'Green','red'=>'Red'] as $value=>$label): ?>
                <option value="<?= $value ?>" <?= $selected('lane',$value) ?>><?= $label ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Product
        <select name="product_id">
            <option value="">All products</option>
            <?php foreach($products as $product): ?>
                <option value="<?= (int)$product['id'] ?>" <?= (int)($filters['product_id'] ?? 0)===(int)$product['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string)$product['full_name']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Source
        <select name="source_id">
            <option value="">All sources</option>
            <?php foreach($sources as $source): ?>
                <option value="<?= (int)$source['id'] ?>" <?= (int)($filters['source_id'] ?? 0)===(int)$source['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string)$source['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Search
        <input name="q" value="<?= htmlspecialchars((string)($filters['q'] ?? '')) ?>" placeholder="title, product, or #ID">
    </label>
    <label>Sort
        <select name="sort">
            <option value="attention" <?= $selected('sort','attention') ?>>Amber first</option>
            <option value="newest" <?= $selected('sort','newest') ?>>Newest</option>
            <option value="oldest" <?= $selected('sort','oldest') ?>>Oldest</option>
            <option value="price_asc" <?= $selected('sort','price_asc') ?>>Price low to high</option>
            <option value="price_desc" <?= $selected('sort','price_desc') ?>>Price high to low</option>
        </select>
    </label>
    <div class="review-filter-actions">
        <button class="btn btn-primary" type="submit">Apply</button>
        <a class="btn btn-outline-secondary" href="<?= $base ?>/admin/review-queue">Reset</a>
    </div>
</form>

<div class="review-range">
    <span><?= (int)$queue['total'] ?> records</span>
    <span>Showing <?= (int)$queue['from'] ?>-<?= (int)$queue['to'] ?>, page <?= (int)$queue['page'] ?> of <?= (int)$queue['pages'] ?></span>
</div>

<div class="review-queue-list">
    <?php foreach($items as $o): ?>
        <?php
        $lane=strtolower((string)($o['lane'] ?? 'unknown'));
        $warningCount=count($o['warning_items'] ?? []);
        $returnPath=$queuePath;
        $detailUrl=$base.'/admin/review/'.(int)$o['id'].'?return_to='.rawurlencode($returnPath);
        $priceType=(string)($o['price_type'] ?? 'asking') === 'sold' ? 'Sold / Final Price' : 'Listing / Asking Price';
        $thumbUrl=$imageUrl($o);
        $icon=$categoryIcon((string)($o['category_slug'] ?? ''));
        ?>
        <article class="review-queue-item">
            <div class="review-thumb" aria-hidden="true">
                <?php if($thumbUrl): ?>
                    <img src="<?= htmlspecialchars($thumbUrl) ?>" alt="">
                <?php else: ?>
                    <i class="fas <?= htmlspecialchars($icon) ?>"></i>
                <?php endif; ?>
            </div>
            <div class="review-id-block">
                <span class="review-id">#<?= (int)$o['id'] ?></span>
                <span class="dataset-badge dataset-<?= strtolower((string)$o['dataset_label']) ?>"><?= htmlspecialchars((string)$o['dataset_label']) ?></span>
                <small><?= htmlspecialchars((string)$o['verified_status']) ?></small>
            </div>
            <div class="review-main-block">
                <h2><?= htmlspecialchars((string)$o['full_name']) ?></h2>
                <p><?= htmlspecialchars($short($o['display_title'] ?? $o['raw_title'] ?? '-')) ?></p>
                <small>Source: <?= htmlspecialchars((string)($o['source_name'] ?? 'unknown source')) ?></small>
            </div>
            <div class="review-price-block">
                <b>฿<?= number_format((float)$o['display_price']) ?></b>
                <small><?= htmlspecialchars($priceType) ?></small>
            </div>
            <div class="review-lane-block">
                <span class="lane-badge lane-<?= htmlspecialchars($lane) ?>"><?= htmlspecialchars(strtoupper($lane)) ?></span>
                <small><?= $warningCount ?> warning<?= $warningCount===1 ? '' : 's' ?></small>
                <small><?= htmlspecialchars((string)($o['observed_at'] ?? '-')) ?></small>
            </div>
            <a class="btn btn-primary review-open" href="<?= htmlspecialchars($detailUrl) ?>"><i class="fas fa-search mr-2"></i>Review</a>
        </article>
    <?php endforeach; ?>
    <?php if(!$items): ?>
        <div class="empty">No records match the current filters.</div>
    <?php endif; ?>
</div>

<?php if((int)$queue['pages'] > 1): ?>
<nav class="review-pagination" aria-label="Review queue pages">
    <?php if((int)$queue['page'] > 1): ?><a class="btn btn-outline-secondary" href="<?= $base ?>/admin/review-queue?<?= htmlspecialchars($queryFor(['page'=>(int)$queue['page']-1])) ?>">Previous</a><?php endif; ?>
    <span>Page <?= (int)$queue['page'] ?> / <?= (int)$queue['pages'] ?></span>
    <?php if((int)$queue['page'] < (int)$queue['pages']): ?><a class="btn btn-outline-secondary" href="<?= $base ?>/admin/review-queue?<?= htmlspecialchars($queryFor(['page'=>(int)$queue['page']+1])) ?>">Next</a><?php endif; ?>
</nav>
<?php endif; ?>
