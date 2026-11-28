<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Database;
use App\Services\Admin\ModerationQueue;
use App\Services\Market\ListingBrowse;
use App\Services\Market\SellerProfiles;
use PDOException;

/** LS-90 D4: the one page that shows what waits for a person, with listing reports and seller verification decided on it. */
final class AdminModerationController extends AdminBaseController
{
    private const ERRORS = ['NOTE_REQUIRED' => 'mod.err.note', 'BAD_STATE' => 'mod.err.state', 'BAD_ACTION' => 'mod.err.state'];

    public function index(): void
    {
        // either moderation permission opens the page; each section is shown only to who may act on it
        if (!$this->can('listings.moderate') && !$this->requirePermission('reports.moderate')) { return; }
        $db = $this->db();
        $reports = [];
        $waiting = [];
        $verified = [];
        $ready = true;
        if ($this->can('listings.moderate')) {
            try {
                $reports = (new ListingBrowse($db))->openReports();
                $sellers = new SellerProfiles($db);
                $waiting = $sellers->waiting();
                $verified = $sellers->verified();
            } catch (PDOException) {
                $ready = false;
            }
        }
        $this->render('moderation', 'Moderation queue', ['queues' => (new ModerationQueue($db))->summary($this->user()), 'reports' => $reports, 'waiting' => $waiting,
            'verified' => $verified, 'ready' => $ready, 'canListings' => $this->can('listings.moderate')]);
    }

    public function listingReport(): void
    {
        if (!$this->allowed()) { return; }
        $r = (new ListingBrowse($this->db()))->decideReport((int)$this->user()['id'], $this->posted('id'), $this->text('decision'), $this->text('note'));
        $this->finish($r, '#listing-reports');
    }

    public function seller(): void
    {
        if (!$this->allowed()) { return; }
        $r = (new SellerProfiles($this->db()))->decide((int)$this->user()['id'], $this->posted('member_id'), $this->text('decision'), $this->text('note'));
        $this->finish($r, '#sellers');
    }

    private function allowed(): bool
    {
        if (!$this->requirePermission('listings.moderate')) { return false; }
        if (!Csrf::verify($_POST['_token'] ?? null)) {
            http_response_code(419);
            $this->view('errors/419', ['title' => 'แบบฟอร์มหมดอายุ']);
            return false;
        }
        return true;
    }

    /** @param array{ok:bool,error:?string} $result */
    private function finish(array $result, string $anchor): void
    {
        $this->flash($result['ok'] ? 'success' : 'danger', t($result['ok'] ? 'mod.done' : (self::ERRORS[$result['error']] ?? 'common.fail')));
        $this->redirect('/admin/moderation' . $anchor);
    }

    private function posted(string $key): int { $v = $_POST[$key] ?? null; return is_scalar($v) ? max(0, (int)filter_var($v, FILTER_VALIDATE_INT)) : 0; }
    private function text(string $key): string { return is_scalar($_POST[$key] ?? null) ? (string)$_POST[$key] : ''; }
    private function db(): \PDO { global $config; return Database::connection($config['db']); }
}
