<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Database;
use App\Services\Catalog\ImageImport;
use App\Services\Catalog\MasterData;

/** LS-89 BE-5: brands and categories, the reference specs of one model, and its pictures. */
final class AdminMasterDataController extends AdminBaseController
{
    public function lists(): void
    {
        if (!$this->requirePermission('products.view')) return;
        $data = $this->data();
        $this->render('catalog-lists', 'Brands and categories', ['brands' => $data->brands(), 'categories' => $data->categories(), 'canManage' => $this->canManage()]);
    }

    public function listAction(): void
    {
        if (!$this->requirePermission('products.manage')) return;
        if (!Csrf::verify($_POST['_token'] ?? null)) { $this->flash('danger', t('flash.csrf')); $this->redirect('/admin/catalog-lists'); }
        $data = $this->data();
        $id = $this->number('id') ?: null;
        $action = $this->text('action');
        $result = match ($action) {
            'brand_save' => $data->saveBrand($id, $this->text('name')),
            'brand_delete' => $this->confirmed() ? $data->deleteBrand((int)$id) : ['ok' => false, 'error' => 'CONFIRM_REQUIRED'],
            'category_save' => $data->saveCategory($id, $this->text('name'), $this->text('slug'), $this->number('sort_order')),
            'category_delete' => $this->confirmed() ? $data->deleteCategory((int)$id) : ['ok' => false, 'error' => 'CONFIRM_REQUIRED'],
            default => ['ok' => false, 'error' => 'UNKNOWN_ACTION'],
        };
        if ($result['ok']) {
            $this->audit('master_' . $action, str_starts_with($action, 'brand') ? 'brand' : 'product_category', (int)$result['id'], ['name' => $this->text('name')]);
        }
        $this->report($result);
        $this->redirect('/admin/catalog-lists');
    }

    public function specs(string $id): void
    {
        if (!$this->requirePermission('products.view')) return;
        $data = $this->data();
        $product = $this->productOr404($data, $id);
        if ($product === null) return;
        try { $specs = $data->specs((int)$product['id']); $ready = true; } catch (\PDOException) { $specs = []; $ready = false; }
        $this->render('product-specs', 'Product specs', ['product' => $product, 'specs' => $specs, 'ready' => $ready, 'canManage' => $this->canManage()]);
    }

    public function specAction(): void
    {
        if (!$this->requirePermission('products.manage')) return;
        if (!Csrf::verify($_POST['_token'] ?? null)) { $this->flash('danger', t('flash.csrf')); $this->redirect('/admin/products'); }
        $productId = $this->number('product_id');
        $back = $productId > 0 ? '/admin/products/' . $productId . '/specs' : '/admin/products';
        $data = $this->data();
        try {
            if ($this->text('action') === 'delete') {
                $result = $this->confirmed() ? $data->deleteSpec($productId, $this->text('spec_key')) : ['ok' => false, 'error' => 'CONFIRM_REQUIRED'];
                $action = 'master_spec_deleted';
            } else {
                $row = [];
                foreach (['spec_key', 'spec_value', 'unit', 'source_url', 'date_checked', 'review_status', 'review_note'] as $field) { $row[$field] = $this->text($field); }
                $result = $data->saveSpec($productId, $row);
                $action = 'master_spec_saved';
            }
        } catch (\PDOException) {
            $result = ['ok' => false, 'error' => 'TABLE_MISSING'];
            $action = '';
        }
        if ($result['ok']) {
            $this->audit($action, 'product', $productId, ['before' => $result['before'] ?? null, 'after' => $result['after'] ?? null]);
        }
        $this->report($result);
        $this->redirect($back);
    }

    public function pictures(string $id): void
    {
        if (!$this->requirePermission('products.view')) return;
        $data = $this->data();
        $product = $this->productOr404($data, $id);
        if ($product === null) return;
        $this->render('product-pictures', 'Product pictures', ['product' => $product, 'pictures' => $data->pictures((int)$product['id']), 'ready' => ImageImport::tableExists($this->db()), 'maxSlots' => ImageImport::MAX_SLOTS, 'canManage' => $this->canManage()]);
    }

    public function pictureAction(): void
    {
        if (!$this->requirePermission('products.manage')) return;
        if (!Csrf::verify($_POST['_token'] ?? null)) { $this->flash('danger', t('flash.csrf')); $this->redirect('/admin/products'); }
        $productId = $this->number('product_id');
        $data = $this->data();
        $product = $data->product($productId);
        if ($product === null) { $this->flash('danger', t('md.err.NOT_FOUND')); $this->redirect('/admin/products'); }
        $action = $this->text('action');
        $slot = $this->number('slot');
        $result = match ($action) {
            'upload' => $this->upload($product, $slot),
            'move' => $data->movePicture($productId, $slot, $this->number('to')),
            'remove' => $this->confirmed() ? $data->removePicture($productId, $slot) : ['ok' => false, 'error' => 'CONFIRM_REQUIRED'],
            'clear_primary' => $this->confirmed() ? $data->clearPrimary($productId) : ['ok' => false, 'error' => 'CONFIRM_REQUIRED'],
            default => ['ok' => false, 'error' => 'UNKNOWN_ACTION'],
        };
        if ($result['ok']) {
            $this->audit('master_picture_' . $action, 'product', $productId, ['slot' => $slot] + array_diff_key($result, ['ok' => 1, 'error' => 1]));
        }
        $this->report($result);
        $this->redirect('/admin/products/' . $productId . '/pictures');
    }

    /** one picture into one slot, through the same checks as the bulk picture import (type, size, dimensions, source and licence) */
    private function upload(array $product, int $slot): array
    {
        $db = $this->db();
        if (!ImageImport::tableExists($db)) return ['ok' => false, 'error' => 'TABLE_MISSING'];
        if ($slot < 1 || $slot > ImageImport::MAX_SLOTS) return ['ok' => false, 'error' => 'SLOT_INVALID'];
        $file = $_FILES['picture'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return ['ok' => false, 'error' => 'NO_FILE'];
        $dir = ROOT_PATH . '/storage/import_uploads';
        if (!is_dir($dir) && !mkdir($dir, 0775, true)) return ['ok' => false, 'error' => 'UPLOAD_FAILED'];
        $tmp = $dir . '/picture-' . bin2hex(random_bytes(6)) . '.bin';
        $source = (string)$file['tmp_name'];
        if (!(move_uploaded_file($source, $tmp) || (defined('MODINSPECT_TESTING') && is_file($source) && copy($source, $tmp)))) return ['ok' => false, 'error' => 'UPLOAD_FAILED'];
        try {
            $name = $product['slug'] . '_' . $slot . '.jpg';
            $meta = ['source_name' => $this->text('source_name'), 'source_url' => $this->text('source_url'), 'license_note' => $this->text('license_note'), 'alt' => $this->text('alt')];
            $plan = ImageImport::plan($db, [['name' => $name, 'path' => $tmp]], $meta);
            // the slot the person chose wins over anything the file name could suggest
            if (($plan[0]['data'] ?? null) === null) return ['ok' => false, 'error' => 'PICTURE_REFUSED', 'detail' => (string)$plan[0]['error']];
            if ((int)$plan[0]['product_id'] !== (int)$product['id'] || (int)$plan[0]['data']['slot'] !== $slot) return ['ok' => false, 'error' => 'SLOT_INVALID'];
            ImageImport::apply($db, $plan, ROOT_PATH . '/public/assets/products');
            return ['ok' => true, 'error' => null, 'source' => $meta['source_name'], 'licence' => $meta['license_note']];
        } catch (\Throwable $e) {
            error_log('PICTURE_UPLOAD_FAILED product=' . (int)$product['id'] . ' ' . get_class($e) . ': ' . $e->getMessage());
            return ['ok' => false, 'error' => 'UPLOAD_FAILED'];
        } finally {
            @unlink($tmp);
        }
    }

    private function productOr404(MasterData $data, string $id): ?array
    {
        $product = ctype_digit($id) ? $data->product((int)$id) : null;
        if ($product === null) {
            http_response_code(404);
            $this->view('errors/404', ['title' => 'Product not found']);
        }
        return $product;
    }

    private function report(array $result): void
    {
        if ($result['ok']) { $this->flash('success', t('md.saved')); return; }
        $detail = (string)($result['detail'] ?? '');
        $this->flash('danger', t('md.err.' . $result['error']) . ($detail === '' ? '' : ': ' . $detail));
    }

    private function db(): \PDO
    {
        global $config;
        return Database::connection($config['db']);
    }

    private function data(): MasterData
    {
        return new MasterData($this->db());
    }

    private function text(string $key): string
    {
        return is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';
    }

    private function number(string $key): int
    {
        return max(0, (int)filter_var($this->text($key) === '' ? ($_POST[$key] ?? 0) : $this->text($key), FILTER_VALIDATE_INT));
    }

    /** destructive actions need the confirmation box of their form to be ticked */
    private function confirmed(): bool
    {
        return ($_POST['confirm'] ?? '') === '1';
    }
}
