<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Models\CollectionPipeline;
use App\Models\Product;
use App\Services\Http\RateLimiter;
use App\Services\Listing\ListingReader;
use App\Services\PriceEngine;

/**
 * LS-24 A1/A2: "paste a listing, get it read". Stateless: nothing is stored, the text is masked of contact details before it is shown, a POST is rate limited and
 * needs a CSRF token. The reading itself is ListingReader (existing extractor, resolver and price engine).
 */
final class ListingReaderController extends Controller
{
    public function form(): void
    {
        $this->view('listing/reader', ['title' => 'วางประกาศให้ระบบอ่าน', 'text' => '', 'result' => null, 'error' => null]);
    }

    public function read(): void
    {
        $limit = (new RateLimiter(ROOT_PATH . '/storage/cache/ratelimit'))->attempt('listing-reader', (string)($_SERVER['REMOTE_ADDR'] ?? ''), 30, 600);
        if (!$limit['allowed']) { http_response_code(429); header('Retry-After: ' . $limit['retry_after']); $this->view('errors/429', ['title' => 'ส่งคำขอบ่อยเกินไป', 'retryAfter' => $limit['retry_after']]); return; }
        if (!Csrf::verify($_POST['_token'] ?? null)) { http_response_code(419); $this->view('errors/419', ['title' => 'แบบฟอร์มหมดอายุ']); return; }
        $raw = is_string($_POST['listing'] ?? null) ? $_POST['listing'] : '';
        if (trim($raw) === '') { $this->view('listing/reader', ['title' => 'วางประกาศให้ระบบอ่าน', 'text' => '', 'result' => null, 'error' => 'วางข้อความประกาศก่อน']); return; }
        $typed = is_string($_POST['price'] ?? null) && preg_match('/^\d{1,8}$/', str_replace(',', '', trim($_POST['price']))) ? (float)str_replace(',', '', trim($_POST['price'])) : null;
        $model = new Product();
        $result = ListingReader::read($raw, (new CollectionPipeline())->catalog(), static fn(int $id): ?array => $model->find($id), static fn(array $p, float $price): array => (new PriceEngine())->evaluate($p, $price), $typed);
        $this->view('listing/reader', ['title' => 'ผลการอ่านประกาศ', 'text' => $result['text'], 'result' => $result, 'error' => null]);
    }
}
