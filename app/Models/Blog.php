<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * LS-24 M8: published articles for readers. A draft, an archived article, or one whose publish time is still in the future is never returned by any method here,
 * so no public page can show it. Read-only.
 */
final class Blog
{
    private const LIVE = "status = 'published' AND published_at IS NOT NULL AND published_at <= NOW()";
    private PDO $db;

    public function __construct()
    {
        global $config;
        $this->db = Database::connection($config['db']);
    }

    /** @return array{rows:list<array<string,mixed>>,total:int,pages:int,page:int} */
    public function page(int $page, int $perPage = 9, string $q = ''): array
    {
        $page = max(1, $page); $perPage = max(1, min(30, $perPage));
        $where = self::LIVE; $params = [];
        $q = mb_substr(trim($q), 0, 80, 'UTF-8');
        if ($q !== '') { $where .= ' AND (title LIKE :q OR excerpt LIKE :q2 OR body LIKE :q3)'; $params = ['q' => "%$q%", 'q2' => "%$q%", 'q3' => "%$q%"]; }
        $c = $this->db->prepare("SELECT COUNT(*) FROM articles WHERE $where"); $c->execute($params); $total = (int)$c->fetchColumn();
        $pages = max(1, (int)ceil($total / $perPage)); $page = min($page, $pages);
        $s = $this->db->prepare("SELECT id, title, slug, excerpt, LEFT(body, 700) body_head, CHAR_LENGTH(body) body_len, published_at, updated_at FROM articles WHERE $where ORDER BY published_at DESC, id DESC LIMIT $perPage OFFSET " . (($page - 1) * $perPage));
        $s->execute($params);
        return ['rows' => $s->fetchAll(PDO::FETCH_ASSOC), 'total' => $total, 'pages' => $pages, 'page' => $page];
    }

    public function findBySlug(string $slug): ?array
    {
        if (!preg_match('/^[a-z0-9ก-๙_-]{1,190}$/u', $slug)) { return null; }
        $s = $this->db->prepare('SELECT id, title, slug, excerpt, body, published_at, updated_at FROM articles WHERE slug = :s AND ' . self::LIVE . ' LIMIT 1');
        $s->execute(['s' => $slug]);
        return $s->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** @return list<array{slug:string,title:string,published_at:string,updated_at:?string}> newest first, for the sitemap and the feed */
    public function recent(int $limit = 50): array
    {
        $s = $this->db->query("SELECT slug, title, excerpt, LEFT(body, 600) body_head, published_at, updated_at FROM articles WHERE " . self::LIVE . " ORDER BY published_at DESC, id DESC LIMIT " . max(1, min(200, $limit)));
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }
}
