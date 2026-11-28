<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Services\Admin\PriceStatus;
use App\Services\Pricing\PriceRefresh;

/** LS-89 BE-2: what the public site shows per model, a preview of a calculation, and the button that runs it. */
final class AdminPriceController extends AdminBaseController
{
    public function status(): void
    {
        if (!$this->requirePermission('snapshots.view')) return;
        $this->render('price-status', 'Price status', ['overview' => (new PriceStatus())->overview(), 'canRecalculate' => $this->can('prices.recalculate')]);
    }

    public function detail(string $id): void
    {
        if (!$this->requirePermission('snapshots.view')) return;
        $detail = ctype_digit($id) ? (new PriceStatus())->detail((int)$id) : null;
        if ($detail === null) {
            http_response_code(404);
            $this->view('errors/404', ['title' => 'Model not found']);
            return;
        }
        $this->render('price-detail', 'Price status', ['detail' => $detail, 'canRecalculate' => $this->can('prices.recalculate')]);
    }

    /** one model (product_id), or every model whose calculation differs from what is stored (all=1) */
    public function recalculate(): void
    {
        if (!$this->requirePermission('prices.recalculate')) return;
        if (!Csrf::verify($_POST['_token'] ?? null)) { $this->flash('danger', t('flash.csrf')); $this->redirect('/admin/price-status'); }
        $productId = (int)(is_scalar($_POST['product_id'] ?? null) ? $_POST['product_id'] : 0);
        $all = ($_POST['all'] ?? '') === '1';
        if ($all) {
            $ids = [];
            foreach ((new PriceStatus())->overview()['rows'] as $row) {
                if ($row['can_recalculate']) { $ids[] = (int)$row['product_id']; }
            }
        } else {
            $ids = $productId > 0 && (new PriceStatus())->detail($productId) !== null ? [$productId] : [];
        }
        if ($ids === []) {
            $this->flash('warning', t('price.nothing_to_do'));
            $this->redirect('/admin/price-status');
        }
        $results = (new PriceRefresh())->refresh($ids);
        $this->audit('price_recalculated', 'product', $all ? null : $productId, ['results' => $results]);
        $message = $this->priceResultMessage($results);
        $this->flash($message['type'], $message['text']);
        $this->redirect($all ? '/admin/price-status' : '/admin/price-status/' . $productId);
    }
}
