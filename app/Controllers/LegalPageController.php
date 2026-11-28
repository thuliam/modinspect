<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Services\Member\LegalDocuments;
use App\Services\Member\MemberAudit;
use Throwable;

/**
 * LS-90 T5: the public privacy and terms pages. They show only what a person published at /admin/legal-documents, and only while
 * LEGAL_PAGES_ENABLED is on; otherwise, or when nothing is published, they answer 404. No legal text lives in the code.
 */
final class LegalPageController extends Controller
{
    public function privacy(): void
    {
        $this->document('privacy');
    }

    public function terms(): void
    {
        $this->document('buyer_terms');
    }

    private function document(string $type): void
    {
        global $config;
        $document = null;
        if (!empty($config['legal_pages']['enabled'])) {
            try {
                $db = Database::connection($config['db']);
                $document = (new LegalDocuments($db, new MemberAudit($db)))->current($type);
            } catch (Throwable $e) {
                error_log('LEGAL_PAGE_UNAVAILABLE ' . get_class($e));
            }
        }
        if ($document === null) {
            http_response_code(404);
            $this->view('errors/404', ['title' => 'ไม่พบหน้า']);
            return;
        }
        $this->view('pages/legal', ['title' => (string)$document['title'], 'document' => $document]);
    }
}
