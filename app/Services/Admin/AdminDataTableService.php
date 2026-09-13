<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Core\Database;
use App\Models\Dashboard;
use App\Services\Auth\AuthorizationService;
use App\Services\Provenance\ObservationProvenanceService;
use PDO;

final class AdminDataTableService
{
    private PDO $db;
    private ObservationProvenanceService $provenance;

    public function __construct()
    {
        global $config;
        $this->db = Database::connection($config['db']);
        $this->provenance = new ObservationProvenanceService();
    }

    public function products(array $input, string $base): array
    {
        $req = $this->request($input);
        $params = [];
        $where = ['1=1'];
        if (($input['category_id'] ?? '') !== '') {
            $where[] = 'p.category_id=:category_id';
            $params[':category_id'] = (int)$input['category_id'];
        }
        if (($input['brand_id'] ?? '') !== '') {
            $where[] = 'p.brand_id=:brand_id';
            $params[':brand_id'] = (int)$input['brand_id'];
        }
        if (($input['active'] ?? '') !== '') {
            $where[] = 'p.is_active=:active';
            $params[':active'] = (int)$input['active'];
        }
        $this->addSearch($where, $params, ['p.full_name', 'p.model_name', 'p.slug', 'b.name', 'c.name', 'p.generation', 'p.spec_summary'], $req['search']);
        $from = "FROM products p
            JOIN brands b ON b.id=p.brand_id
            JOIN product_categories c ON c.id=p.category_id
            LEFT JOIN (
                SELECT product_id,
                    SUM(verified_status='pending') pending_count,
                    SUM(verified_status='approved') approved_count
                FROM price_observations
                GROUP BY product_id
            ) obs ON obs.product_id=p.id";
        $columns = ['p.full_name', 'b.name', 'c.name', 'p.spec_summary', 'obs.pending_count', 'p.is_active', 'p.id'];
        $sql = "SELECT p.id,p.category_id,p.brand_id,p.model_name,p.slug,p.full_name,p.generation,p.spec_summary,p.image_path,p.is_active,b.name brand,c.name category,COALESCE(obs.pending_count,0) pending_count,COALESCE(obs.approved_count,0) approved_count $from WHERE " . implode(' AND ', $where) . ' ORDER BY ' . $this->orderSql($req, $columns, 'p.id DESC') . ' LIMIT :limit OFFSET :offset';
        $rows = $this->pagedRows($sql, $params, $req);
        $total = $this->count("SELECT COUNT(*) FROM products p", []);
        $filtered = $this->count("SELECT COUNT(*) $from WHERE " . implode(' AND ', $where), $params);
        return $this->response($req, $total, $filtered, array_map(fn(array $row): array => $this->productRow($row, $base), $rows));
    }

    public function aliases(array $input, string $base): array
    {
        $req = $this->request($input);
        $params = [];
        $where = ['1=1'];
        if (($input['product_id'] ?? '') !== '') {
            $where[] = 'pa.product_id=:product_id';
            $params[':product_id'] = (int)$input['product_id'];
        }
        $this->addSearch($where, $params, ['pa.alias_text', 'pa.normalized_alias', 'pa.source_note', 'p.full_name'], $req['search']);
        $from = "FROM product_aliases pa JOIN products p ON p.id=pa.product_id LEFT JOIN product_variants pv ON pv.id=pa.product_variant_id";
        $columns = ['pa.alias_text', 'p.full_name', 'pa.normalized_alias', 'pa.confidence', 'pa.source_note', 'pa.id'];
        $sql = "SELECT pa.id,pa.product_id,pa.product_variant_id,pa.alias_text,pa.normalized_alias,pa.source_note,pa.confidence,p.full_name,pv.variant_name $from WHERE " . implode(' AND ', $where) . ' ORDER BY ' . $this->orderSql($req, $columns, 'pa.id DESC') . ' LIMIT :limit OFFSET :offset';
        $rows = $this->pagedRows($sql, $params, $req);
        $total = $this->count("SELECT COUNT(*) FROM product_aliases pa", []);
        $filtered = $this->count("SELECT COUNT(*) $from WHERE " . implode(' AND ', $where), $params);
        return $this->response($req, $total, $filtered, array_map(fn(array $row): array => $this->aliasRow($row, $base), $rows));
    }

    public function observations(array $input, string $base): array
    {
        $req = $this->request($input);
        [$where, $params] = $this->observationWhere($input, $req['search']);
        $from = $this->observationFrom();
        $columns = ['o.id', 'p.full_name', 'dataset_label', 'o.id', 'o.price_type', 'display_price', 's.name', 'o.verified_status', 'o.observed_at', 'o.id'];
        $sql = "SELECT o.id,o.raw_observation_id,o.price_type,o.asking_price,o.price_value,COALESCE(o.price_value,o.asking_price) display_price,o.verified_status,o.observed_at,p.full_name,r.raw_title,r.raw_price_text,r.source_url_encrypted,r.external_reference_hash,s.name source_name,s.source_key,e.excerpt,e.content_hash," . $this->datasetSql() . " dataset_label $from WHERE " . implode(' AND ', $where) . ' ORDER BY ' . $this->orderSql($req, $columns, 'o.id DESC') . ' LIMIT :limit OFFSET :offset';
        $rows = $this->pagedRows($sql, $params, $req);
        $total = $this->count("SELECT COUNT(*) $from WHERE " . implode(' AND ', $this->observationWhere($input, '')[0]), $this->observationWhere($input, '')[1]);
        $filtered = $this->count("SELECT COUNT(*) $from WHERE " . implode(' AND ', $where), $params);
        return $this->response($req, $total, $filtered, array_map(fn(array $row): array => $this->observationRow($row, $base), $rows));
    }

    public function reviewQueue(array $input, string $base): array
    {
        $req = $this->request($input);
        $filters = [
            'dataset' => strtoupper((string)($input['dataset'] ?? 'REAL')),
            'status' => strtolower((string)($input['status'] ?? 'pending')),
            'lane' => strtolower((string)($input['lane'] ?? '')),
            'provenance' => strtoupper((string)($input['provenance'] ?? '')),
            'run_id' => preg_replace('/[^A-Za-z0-9_.:-]/', '', (string)($input['run_id'] ?? '')) ?? '',
            'sort' => $this->reviewSort($req),
            'product_id' => ($input['product_id'] ?? '') !== '' ? (int)$input['product_id'] : null,
            'source_id' => ($input['source_id'] ?? '') !== '' ? (int)$input['source_id'] : null,
            'q' => $req['search'],
            'page' => intdiv($req['start'], $req['length']) + 1,
            'per_page' => $req['length'],
        ];
        $dashboard = new Dashboard();
        $queue = $dashboard->reviewQueue($filters);
        $baseFilters = $filters;
        $baseFilters['q'] = '';
        $baseQueue = $dashboard->reviewQueue($baseFilters);
        $data = [];
        foreach ($queue['items'] as $item) {
            $data[] = [$this->reviewCard($item, $base, $this->reviewReturnPath($filters))];
        }
        return $this->response($req, (int)$baseQueue['total'], (int)$queue['total'], $data);
    }

    public function priceIndices(array $input, string $base): array
    {
        $req = $this->request($input);
        $params = [];
        $where = ['1=1'];
        if (($input['category'] ?? '') !== '') {
            $where[] = 'c.slug=:category';
            $params[':category'] = (string)$input['category'];
        }
        if (($input['confidence'] ?? '') !== '') {
            $where[] = 'pi.confidence_label=:confidence';
            $params[':confidence'] = (string)$input['confidence'];
        }
        if (($input['provenance_status'] ?? '') !== '') {
            $where[] = 'COALESCE(pi.provenance_status,\'legacy_unavailable\')=:provenance_status';
            $params[':provenance_status'] = (string)$input['provenance_status'];
        }
        $this->addSearch($where, $params, ['p.full_name', 'b.name', 'c.name'], $req['search']);
        $from = "FROM products p JOIN brands b ON b.id=p.brand_id JOIN product_categories c ON c.id=p.category_id LEFT JOIN price_indices pi ON pi.id=(SELECT id FROM price_indices WHERE product_id=p.id ORDER BY last_calculated_at DESC LIMIT 1)";
        $columns = ['p.full_name', 'pi.price_type', 'pi.median', 'pi.valid_sample_size', 'pi.confidence_label', 'pi.fresh_sample_ratio', 'pi.provenance_status', 'pi.last_calculated_at'];
        $sql = "SELECT p.id product_id,p.slug,p.full_name,b.name brand,c.name category,pi.id snapshot_id,pi.price_type,pi.q1,pi.median,pi.q3,pi.valid_sample_size,pi.fresh_sample_ratio,pi.confidence_score,pi.confidence_label,pi.provenance_status,pi.last_calculated_at $from WHERE " . implode(' AND ', $where) . ' ORDER BY ' . $this->orderSql($req, $columns, 'p.id DESC') . ' LIMIT :limit OFFSET :offset';
        $rows = $this->pagedRows($sql, $params, $req);
        $total = $this->count("SELECT COUNT(*) FROM products p", []);
        $filtered = $this->count("SELECT COUNT(*) $from WHERE " . implode(' AND ', $where), $params);
        return $this->response($req, $total, $filtered, array_map(fn(array $row): array => $this->priceIndexRow($row, $base), $rows));
    }

    public function sources(array $input, string $base, array $user): array
    {
        $req = $this->request($input);
        $params = [];
        $where = ['1=1'];
        if (($input['enabled'] ?? '') !== '') {
            if ((string)$input['enabled'] === '1') $where[] = '(s.is_active=1 AND s.is_paused=0)';
            if ((string)$input['enabled'] === '0') $where[] = '(s.is_active=0 OR s.is_paused=1)';
        }
        if (($input['provider'] ?? '') !== '') {
            $where[] = 's.source_key LIKE :provider';
            $params[':provider'] = '%' . (string)$input['provider'] . '%';
        }
        if (($input['health'] ?? '') !== '') {
            $where[] = "COALESCE(h.status,'unknown')=:health";
            $params[':health'] = (string)$input['health'];
        }
        $this->addSearch($where, $params, ['s.name', 's.source_key', 's.domain', 's.allowed_collection_method'], $req['search']);
        $from = "FROM data_sources s LEFT JOIN (
                SELECT source_id,
                    SUM(resolved_at IS NULL) unresolved_incidents
                FROM source_incidents GROUP BY source_id
            ) si ON si.source_id=s.id
            LEFT JOIN (
                SELECT source_id,
                    CASE WHEN SUM(status='failed') > 0 THEN 'degraded' WHEN SUM(status='completed') > 0 THEN 'healthy' ELSE 'unknown' END status,
                    MAX(completed_at) last_success,
                    MAX(CASE WHEN status='failed' THEN completed_at END) last_failure
                FROM collector_jobs GROUP BY source_id
            ) h ON h.source_id=s.id";
        $columns = ['s.name', 's.source_key', 's.is_active', 's.allowed_collection_method', 'h.status', 'h.last_success', 'si.unresolved_incidents', 's.id'];
        $sql = "SELECT s.id,s.name,s.source_key,s.domain,s.is_active,s.is_paused,s.allowed_collection_method,s.access_method,s.evidence_quality,s.pause_reason,s.disabled_reason,COALESCE(h.status,'unknown') health_status,h.last_success,h.last_failure,COALESCE(si.unresolved_incidents,0) unresolved_incidents $from WHERE " . implode(' AND ', $where) . ' ORDER BY ' . $this->orderSql($req, $columns, 's.id DESC') . ' LIMIT :limit OFFSET :offset';
        $rows = $this->pagedRows($sql, $params, $req);
        $total = $this->count("SELECT COUNT(*) FROM data_sources s", []);
        $filtered = $this->count("SELECT COUNT(*) $from WHERE " . implode(' AND ', $where), $params);
        $canControl = (new AuthorizationService())->can($user, 'sources.control');
        return $this->response($req, $total, $filtered, array_map(fn(array $row): array => $this->sourceRow($row, $base, $canControl), $rows));
    }

    public function jobs(array $input, string $base, array $user): array
    {
        $req = $this->request($input);
        $params = [];
        $where = ['1=1'];
        if (($input['status'] ?? '') !== '') {
            $where[] = 'j.status=:status';
            $params[':status'] = (string)$input['status'];
        }
        if (($input['dataset'] ?? '') !== '') {
            $where[] = "(CASE WHEN LOWER(COALESCE(s.source_key,'')) LIKE 'offline_real_%' THEN 'REAL' WHEN LOWER(COALESCE(s.source_key,'')) LIKE '%mock%' OR LOWER(COALESCE(s.source_key,'')) LIKE '%test%' THEN 'MOCK_TEST' ELSE 'UNKNOWN' END)=:dataset";
            $params[':dataset'] = (string)$input['dataset'];
        }
        if (($input['run_id'] ?? '') !== '') {
            $where[] = 'j.run_id LIKE :run_id';
            $params[':run_id'] = '%' . preg_replace('/[^A-Za-z0-9_.:-]/', '', (string)$input['run_id']) . '%';
        }
        $this->addSearch($where, $params, ['j.job_type', 'j.query_text', 'j.run_id', 's.name', 'p.full_name'], $req['search']);
        $from = "FROM collector_jobs j JOIN data_sources s ON s.id=j.source_id LEFT JOIN products p ON p.id=j.product_id";
        $columns = ['j.id', 'p.full_name', 's.name', 'j.raw_count', 'j.status', 'j.attempt_count', 'j.created_at', 'j.completed_at', 'j.id'];
        $sql = "SELECT j.id,j.job_type,j.query_text,j.status,j.attempt_count,j.run_id,j.raw_count,j.valid_count,j.created_at,j.completed_at,j.error_message,s.name source_name,s.source_key,p.full_name $from WHERE " . implode(' AND ', $where) . ' ORDER BY ' . $this->orderSql($req, $columns, 'j.id DESC') . ' LIMIT :limit OFFSET :offset';
        $rows = $this->pagedRows($sql, $params, $req);
        $total = $this->count("SELECT COUNT(*) FROM collector_jobs j", []);
        $filtered = $this->count("SELECT COUNT(*) $from WHERE " . implode(' AND ', $where), $params);
        $canRequeue = (new AuthorizationService())->can($user, 'collection.jobs.requeue');
        return $this->response($req, $total, $filtered, array_map(fn(array $row): array => $this->jobRow($row, $base, $canRequeue), $rows));
    }

    public function articles(array $input, string $base): array
    {
        $req = $this->request($input);
        $params = [];
        $where = ['1=1'];
        if (($input['status'] ?? '') !== '') {
            $where[] = 'a.status=:status';
            $params[':status'] = (string)$input['status'];
        }
        $this->addSearch($where, $params, ['a.title', 'a.slug', 'a.excerpt'], $req['search']);
        $from = "FROM articles a LEFT JOIN users u ON u.id=a.author_id";
        $columns = ['a.title', 'a.slug', 'a.status', 'u.name', 'a.published_at', 'a.updated_at', 'a.id'];
        $sql = "SELECT a.id,a.title,a.slug,a.excerpt,a.body,a.status,a.published_at,a.updated_at,u.name author_name $from WHERE " . implode(' AND ', $where) . ' ORDER BY ' . $this->orderSql($req, $columns, 'a.id DESC') . ' LIMIT :limit OFFSET :offset';
        $rows = $this->pagedRows($sql, $params, $req);
        $total = $this->count("SELECT COUNT(*) FROM articles a", []);
        $filtered = $this->count("SELECT COUNT(*) $from WHERE " . implode(' AND ', $where), $params);
        return $this->response($req, $total, $filtered, array_map(fn(array $row): array => $this->articleRow($row, $base), $rows));
    }

    public function audits(array $input): array
    {
        $req = $this->request($input);
        $params = [];
        $where = ['1=1'];
        if (($input['actor'] ?? '') !== '') {
            $where[] = 'a.user_id=:actor';
            $params[':actor'] = (int)$input['actor'];
        }
        if (($input['action'] ?? '') !== '') {
            $where[] = 'a.action LIKE :action';
            $params[':action'] = '%' . (string)$input['action'] . '%';
        }
        if (($input['entity'] ?? '') !== '') {
            $where[] = 'a.entity_type=:entity';
            $params[':entity'] = (string)$input['entity'];
        }
        if (($input['from'] ?? '') !== '') {
            $where[] = 'a.created_at>=:from_date';
            $params[':from_date'] = (string)$input['from'] . ' 00:00:00';
        }
        if (($input['to'] ?? '') !== '') {
            $where[] = 'a.created_at<=:to_date';
            $params[':to_date'] = (string)$input['to'] . ' 23:59:59';
        }
        $this->addSearch($where, $params, ['a.action', 'a.entity_type', 'a.before_data', 'a.after_data', 'u.email', 'u.name'], $req['search']);
        $from = "FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id";
        $columns = ['a.created_at', 'u.email', 'a.action', 'a.entity_type', 'a.after_data', 'a.id'];
        $sql = "SELECT a.id,a.user_id,a.action,a.entity_type,a.entity_id,a.before_data,a.after_data,a.created_at,u.name actor_name,u.email actor_email $from WHERE " . implode(' AND ', $where) . ' ORDER BY ' . $this->orderSql($req, $columns, 'a.id DESC') . ' LIMIT :limit OFFSET :offset';
        $rows = $this->pagedRows($sql, $params, $req);
        $total = $this->count("SELECT COUNT(*) FROM audit_logs a", []);
        $filtered = $this->count("SELECT COUNT(*) $from WHERE " . implode(' AND ', $where), $params);
        return $this->response($req, $total, $filtered, array_map(fn(array $row): array => $this->auditRow($row), $rows));
    }

    private function observationWhere(array $input, string $search): array
    {
        $params = [];
        $where = ['1=1'];
        if (($input['dataset'] ?? '') !== '') {
            $where[] = $this->datasetSql() . '=:dataset';
            $params[':dataset'] = (string)$input['dataset'];
        }
        if (($input['status'] ?? '') !== '') {
            $where[] = 'o.verified_status=:status';
            $params[':status'] = (string)$input['status'];
        }
        if (($input['price_type'] ?? '') !== '') {
            $where[] = 'o.price_type=:price_type';
            $params[':price_type'] = (string)$input['price_type'];
        }
        if (($input['product_id'] ?? '') !== '') {
            $where[] = 'o.product_id=:product_id';
            $params[':product_id'] = (int)$input['product_id'];
        }
        if (($input['source_id'] ?? '') !== '') {
            $where[] = 's.id=:source_id';
            $params[':source_id'] = (int)$input['source_id'];
        }
        if (($input['provenance'] ?? '') !== '') {
            $where[] = $this->provenanceSql() . '=:provenance';
            $params[':provenance'] = (string)$input['provenance'];
        }
        if (($input['run_id'] ?? '') !== '') {
            $where[] = 'e.excerpt LIKE :run_id';
            $params[':run_id'] = '%PROBE_RUN_ID=' . preg_replace('/[^A-Za-z0-9_.:-]/', '', (string)$input['run_id']) . '%';
        }
        $this->addSearch($where, $params, ['CAST(o.id AS CHAR)', 'p.full_name', 'r.raw_title', 's.name'], $search);
        return [$where, $params];
    }

    private function addSearch(array &$where, array &$params, array $columns, string $search, string $prefix = 'search'): void
    {
        $search = trim($search);
        if ($search === '') {
            return;
        }
        $clauses = [];
        $index = 0;
        foreach ($columns as $column) {
            $key = ':' . $prefix . '_' . count($params) . '_' . $index;
            $clauses[] = $column . ' LIKE ' . $key;
            $params[$key] = '%' . $search . '%';
            $index++;
        }
        $where[] = '(' . implode(' OR ', $clauses) . ')';
    }

    private function observationFrom(): string
    {
        return "FROM price_observations o
            JOIN products p ON p.id=o.product_id
            LEFT JOIN raw_price_observations r ON r.id=o.raw_observation_id
            LEFT JOIN data_sources s ON s.id=r.source_id
            LEFT JOIN market_evidence e ON e.raw_observation_id=r.id";
    }

    private function productRow(array $row, string $base): array
    {
        $market = ((int)$row['approved_count'] > 0) ? 'Approved observations' : (((int)$row['pending_count'] > 0) ? 'Pending review' : 'No accepted market data');
        $status = (int)$row['is_active'] === 1 ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Inactive</span>';
        $toggle = (int)$row['is_active'] === 1 ? 'Deactivate' : 'Activate';
        return [
            $this->rowTitle($row['full_name'], $row['slug']),
            $this->h($row['brand']),
            $this->h($row['category']),
            $this->h($this->short($row['spec_summary'] ?: $row['generation'] ?: $row['model_name'] ?: '-', 90)),
            '<span class="mi-row-title">' . $this->h($market) . '</span><span class="mi-row-subtitle">Pending ' . (int)$row['pending_count'] . ' / Approved ' . (int)$row['approved_count'] . '</span>',
            $status,
            '<button class="btn btn-sm btn-outline-primary js-product-edit" type="button" data-product=\'' . $this->h($this->json($row)) . '\'>Edit</button> <form method="post" action="' . $base . '/admin/products/toggle" class="d-inline js-confirm-form" data-confirm="' . $toggle . ' this product?"><input type="hidden" name="_token" value="' . $this->csrf() . '"><input type="hidden" name="product_id" value="' . (int)$row['id'] . '"><button class="btn btn-sm btn-outline-secondary">' . $toggle . '</button></form>',
        ];
    }

    private function aliasRow(array $row, string $base): array
    {
        return [
            $this->rowTitle($row['alias_text'], 'Alias #' . (int)$row['id']),
            $this->h($row['full_name']) . ($row['variant_name'] ? '<small>' . $this->h($row['variant_name']) . '</small>' : ''),
            '<code>' . $this->h($row['normalized_alias']) . '</code>',
            number_format((float)$row['confidence'], 0) . '%',
            $this->h($row['source_note'] ?: 'Manual'),
            '<span class="badge badge-success">Active</span>',
            '<button class="btn btn-sm btn-outline-primary js-alias-edit" type="button" data-alias=\'' . $this->h($this->json($row)) . '\'>Edit</button> <form method="post" action="' . $base . '/admin/product-aliases/delete" class="d-inline js-confirm-form" data-confirm="Delete this alias?"><input type="hidden" name="_token" value="' . $this->csrf() . '"><input type="hidden" name="alias_id" value="' . (int)$row['id'] . '"><button class="btn btn-sm btn-outline-danger">Delete</button></form>',
        ];
    }

    private function observationRow(array $row, string $base): array
    {
        $quality = $this->provenance->classify($row);
        return [
            '#' . (int)$row['id'] . '<small>Raw #' . (int)($row['raw_observation_id'] ?? 0) . '</small>',
            $this->rowTitle($row['full_name'], $this->short($row['raw_title'] ?? '-', 90)),
            '<span class="mi-dataset-' . strtolower((string)$row['dataset_label']) . '">' . $this->h($row['dataset_label']) . '</span>',
            '<span class="badge badge-light">' . $this->h($quality['quality']) . '</span>',
            $this->h($row['price_type'] ?? 'asking'),
            '<strong>&#3647;' . number_format((float)$row['display_price']) . '</strong>',
            $this->h($row['source_name'] ?? '-') . '<small>' . $this->h($row['source_key'] ?? '') . '</small>',
            '<span class="mi-status-badge mi-status-' . $this->h($row['verified_status']) . '">' . $this->h($row['verified_status']) . '</span>',
            $this->h($row['observed_at'] ?? '-'),
            '<a class="btn btn-sm btn-primary" href="' . $base . '/admin/review/' . (int)$row['id'] . '">Detail</a>',
        ];
    }

    private function reviewCard(array $row, string $base, string $returnPath): string
    {
        $lane = strtolower((string)($row['lane'] ?? 'unknown'));
        $quality = (string)($row['provenance_quality']['quality'] ?? 'GENERIC_SOURCE');
        $priceType = (string)($row['price_type'] ?? 'asking') === 'sold' ? 'Sold / Final Price' : 'Listing / Asking Price';
        $detail = $base . '/admin/review/' . (int)$row['id'] . '?return_to=' . rawurlencode($returnPath);
        return '<article class="review-queue-item mi-dt-review-card">'
            . '<div class="review-id-block"><span class="review-id">#' . (int)$row['id'] . '</span><span class="dataset-badge dataset-' . strtolower((string)$row['dataset_label']) . '">' . $this->h($row['dataset_label']) . '</span><small>' . $this->h($row['verified_status']) . '</small><small>' . $this->h($row['review_purpose'] ?? '') . '</small></div>'
            . '<div class="review-main-block"><h2>' . $this->h($row['full_name']) . '</h2><p>' . $this->h($this->short($row['display_title'] ?? $row['raw_title'] ?? '-', 130)) . '</p><small>Source: ' . $this->h($row['source_name'] ?? 'unknown source') . '</small></div>'
            . '<div class="review-price-block"><b>&#3647;' . number_format((float)$row['display_price']) . '</b><small>' . $this->h($priceType) . '</small></div>'
            . '<div class="review-lane-block"><span class="lane-badge lane-' . $this->h($lane) . '">' . $this->h(strtoupper($lane)) . '</span><small>' . $this->h(str_replace('_', ' ', $quality)) . '</small><small>' . $this->h($row['observed_at'] ?? '-') . '</small></div>'
            . '<a class="btn btn-primary review-open" href="' . $this->h($detail) . '"><i class="fas fa-search mr-2"></i>Review</a>'
            . '</article>';
    }

    private function priceIndexRow(array $row, string $base): array
    {
        $hasSnapshot = !empty($row['snapshot_id']) && (int)($row['valid_sample_size'] ?? 0) > 0;
        return [
            $this->rowTitle($row['full_name'], ($row['category'] ?? '-') . ' / ' . ($row['brand'] ?? '-')),
            $this->h($row['price_type'] ?? '-'),
            $hasSnapshot ? '&#3647;' . number_format((float)$row['q1']) . ' to &#3647;' . number_format((float)$row['q3']) : 'Insufficient reviewed data',
            $hasSnapshot ? '<strong>&#3647;' . number_format((float)$row['median']) . '</strong>' : '-',
            (int)($row['valid_sample_size'] ?? 0),
            '<span class="badge badge-light">' . $this->h(strtoupper((string)($row['confidence_label'] ?? 'insufficient'))) . '</span><small>Score ' . number_format((float)($row['confidence_score'] ?? 0), 2) . '</small>',
            number_format((float)($row['fresh_sample_ratio'] ?? 0) * 100, 1) . '%',
            '<span class="badge badge-light">' . $this->h($row['provenance_status'] ?? 'legacy_unavailable') . '</span>',
            $this->h($row['last_calculated_at'] ?? '-'),
            '<a class="btn btn-sm btn-outline-primary" href="' . $base . '/price/' . $this->h($row['slug'] ?? '') . '" target="_blank" rel="noopener">Public</a>',
        ];
    }

    private function sourceRow(array $row, string $base, bool $canControl): array
    {
        $enabled = (int)$row['is_active'] === 1 && (int)$row['is_paused'] === 0;
        $controls = '<button class="btn btn-sm btn-outline-secondary disabled-control" type="button" disabled>Controls unavailable</button>';
        if ($canControl) {
            $primary = (int)$row['is_active'] === 0 ? '<button class="btn btn-sm btn-success" name="action" value="enable">Enable</button>' : ((int)$row['is_paused'] === 1 ? '<button class="btn btn-sm btn-primary" name="action" value="resume">Resume</button>' : '<button class="btn btn-sm btn-outline-secondary" name="action" value="pause">Pause</button>');
            $controls = '<form method="post" action="' . $base . '/admin/sources/action" class="source-actions"><input type="hidden" name="_token" value="' . $this->csrf() . '"><input type="hidden" name="source_id" value="' . (int)$row['id'] . '"><input name="reason" maxlength="255" placeholder="Reason / incident note" value="Admin UI action">' . $primary . ' <button class="btn btn-sm btn-danger" name="action" value="disable">Disable</button> <button class="btn btn-sm btn-outline-secondary" name="action" value="incident">Incident</button></form>';
        }
        return [
            $this->rowTitle($row['name'], $row['domain'] ?: 'User supplied data'),
            $this->h($row['source_key']),
            '<span class="badge ' . ($enabled ? 'badge-success' : 'badge-secondary') . '">' . ($enabled ? 'Enabled' : 'Paused/Disabled') . '</span>',
            $this->h($row['allowed_collection_method'] ?? $row['access_method'] ?? '-'),
            '<span class="badge badge-light">' . $this->h($row['health_status']) . '</span>',
            '<small>Success ' . $this->h($row['last_success'] ?? 'Never') . '</small><small>Failure ' . $this->h($row['last_failure'] ?? 'Never') . '</small>',
            (int)$row['unresolved_incidents'],
            $controls,
        ];
    }

    private function jobRow(array $row, string $base, bool $canRequeue): array
    {
        $action = '<button class="btn btn-sm btn-outline-secondary disabled-control" type="button" disabled>Unavailable</button>';
        if (($row['status'] ?? '') === 'failed' && $canRequeue) {
            $action = '<form method="post" action="' . $base . '/admin/collector-jobs/requeue"><input type="hidden" name="_token" value="' . $this->csrf() . '"><input type="hidden" name="job_id" value="' . (int)$row['id'] . '"><button class="btn btn-sm btn-primary">Requeue</button></form>';
        }
        return [
            '#' . (int)$row['id'] . '<small>' . $this->h($row['job_type']) . '</small><small>' . $this->h($row['run_id'] ?? '') . '</small>',
            $this->h($row['full_name'] ?? $row['query_text'] ?? '-'),
            $this->h($row['source_name']) . '<small>' . $this->h($row['source_key'] ?? '-') . '</small>',
            (int)$row['raw_count'] . '<small>Valid ' . (int)$row['valid_count'] . '</small>',
            '<span class="badge badge-light">' . $this->h($row['status']) . '</span>',
            (int)$row['attempt_count'],
            $this->h($row['created_at'] ?? '-'),
            $this->h($row['completed_at'] ?? '-'),
            $action,
        ];
    }

    private function articleRow(array $row, string $base): array
    {
        return [
            $this->rowTitle($row['title'], $row['excerpt'] ?: $row['slug']),
            '<code>' . $this->h($row['slug']) . '</code>',
            '<span class="badge badge-light">' . $this->h($row['status']) . '</span>',
            $this->h($row['author_name'] ?? '-'),
            $this->h($row['published_at'] ?? '-'),
            $this->h($row['updated_at'] ?? '-'),
            '<button class="btn btn-sm btn-outline-primary js-article-edit" type="button" data-article=\'' . $this->h($this->json($row)) . '\'>Edit</button> <form method="post" action="' . $base . '/admin/articles/archive" class="d-inline js-confirm-form" data-confirm="Archive this article?"><input type="hidden" name="_token" value="' . $this->csrf() . '"><input type="hidden" name="article_id" value="' . (int)$row['id'] . '"><button class="btn btn-sm btn-outline-secondary">Archive</button></form>',
        ];
    }

    private function auditRow(array $row): array
    {
        $summary = $this->short($row['after_data'] ?? $row['before_data'] ?? 'No payload', 130);
        return [
            $this->h($row['created_at'] ?? '-'),
            $this->h($row['actor_name'] ?? ('User #' . ($row['user_id'] ?? '-'))) . '<small>' . $this->h($row['actor_email'] ?? '') . '</small>',
            '<span class="badge badge-light">' . $this->h($row['action']) . '</span>',
            $this->h($row['entity_type'] ?? '-') . ' #' . $this->h($row['entity_id'] ?? '-'),
            $this->h($summary),
            '<details class="audit-details"><summary>Open</summary><pre>' . $this->h($this->json(['before' => $row['before_data'] ?? null, 'after' => $row['after_data'] ?? null])) . '</pre></details>',
        ];
    }

    private function request(array $input): array
    {
        $length = max(1, min(100, (int)($input['length'] ?? 25)));
        return [
            'draw' => max(1, (int)($input['draw'] ?? 1)),
            'start' => max(0, (int)($input['start'] ?? 0)),
            'length' => $length,
            'search' => trim((string)($input['search']['value'] ?? '')),
            'order_column' => isset($input['order'][0]['column']) ? (int)$input['order'][0]['column'] : null,
            'order_dir' => strtolower((string)($input['order'][0]['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC',
        ];
    }

    private function response(array $req, int $total, int $filtered, array $data): array
    {
        return [
            'draw' => $req['draw'],
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $data,
        ];
    }

    private function pagedRows(string $sql, array $params, array $req): array
    {
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $req['length'], PDO::PARAM_INT);
        $stmt->bindValue(':offset', $req['start'], PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private function count(string $sql, array $params): int
    {
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    private function orderSql(array $req, array $columns, string $default): string
    {
        $index = $req['order_column'];
        if ($index === null || !isset($columns[$index])) {
            return $default;
        }
        return $columns[$index] . ' ' . $req['order_dir'];
    }

    private function reviewSort(array $req): string
    {
        return match ($req['order_column']) {
            0 => $req['order_dir'] === 'ASC' ? 'oldest' : 'newest',
            default => 'attention',
        };
    }

    private function reviewReturnPath(array $filters): string
    {
        $query = array_filter($filters, static fn(mixed $value): bool => $value !== null && $value !== '');
        unset($query['page'], $query['per_page'], $query['q']);
        return '/admin/review-queue' . ($query ? '?' . http_build_query($query) : '');
    }

    private function datasetSql(): string
    {
        return "CASE WHEN LOWER(COALESCE(s.source_key,'')) LIKE 'offline_real_%' OR LOWER(COALESCE(s.name,'')) LIKE '%offline real import%' THEN 'REAL' WHEN LOWER(COALESCE(s.source_key,'')) LIKE '%mock%' OR LOWER(COALESCE(s.source_key,'')) LIKE '%fixture%' OR LOWER(COALESCE(s.source_key,'')) LIKE '%test%' OR LOWER(COALESCE(s.name,'')) LIKE '%mock%' OR LOWER(COALESCE(s.name,'')) LIKE '%fixture%' OR LOWER(COALESCE(s.name,'')) LIKE '%test%' THEN 'MOCK_TEST' ELSE 'UNKNOWN' END";
    }

    private function provenanceSql(): string
    {
        return "CASE WHEN e.excerpt LIKE '%SOURCE=priceza%' AND e.excerpt LIKE '%SOURCE_SEARCH_URL=%' AND e.excerpt LIKE '%SOURCE_LISTING_URL=%' AND e.excerpt LIKE '%SOURCE_ITEM_ID=%' AND e.excerpt LIKE '%MERCHANT=%' AND e.excerpt LIKE '%LISTING_TITLE=%' AND e.excerpt LIKE '%ASKING_PRICE=%' AND e.excerpt LIKE '%OBSERVED_AT=%' AND e.excerpt LIKE '%EVIDENCE_SNAPSHOT_JSON=%' AND e.excerpt LIKE '%EVIDENCE_HASH=%' THEN 'LISTING_LEVEL' WHEN r.source_url_encrypted LIKE 'https://www.priceza.com/s/%' OR e.excerpt LIKE '%SOURCE_SEARCH_URL=%' OR LOWER(COALESCE(e.excerpt,'')) LIKE '%search result%' THEN 'SEARCH_RESULT_LEVEL' ELSE 'GENERIC_SOURCE' END";
    }

    private function rowTitle(mixed $title, mixed $subtitle): string
    {
        return '<span class="mi-row-title">' . $this->h($title) . '</span><span class="mi-row-subtitle">' . $this->h($subtitle) . '</span>';
    }

    private function short(mixed $value, int $limit): string
    {
        $text = trim((string)$value);
        return mb_strlen($text, 'UTF-8') > $limit ? mb_substr($text, 0, $limit - 3, 'UTF-8') . '...' : $text;
    }

    private function h(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }

    private function json(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}';
    }

    private function csrf(): string
    {
        return $this->h(\App\Core\Csrf::token());
    }
}
