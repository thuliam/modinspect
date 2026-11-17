<?php
declare(strict_types=1);

namespace App\Services\Admin;

/**
 * LS-4 K-1: groups the open unresolved listings into alias PROPOSALS a person can confirm in bulk-speed, one line per (product, alias).
 * Read-only: it writes nothing. A proposal is "safe" only when the alias passes the same checks the queue applies on save
 * (acceptable text, suffix equals the product's, resolves the listing, changes no other listing). No listing text is kept in the output.
 */
final class UnresolvedProposalReport
{
    public function __construct(private UnresolvedListingQueue $queue, private ?\App\Services\ProductResolver\ProductResolver $resolver = null) {}

    /**
     * The alias and the product name carry the SAME family and the SAME full model token (14700K is not 14700KF, 5800X3D is not 5800X,
     * i7 9700F is not Ryzen 9700X). Anything looser is left to a person to look at.
     */
    public static function sameModel(string $alias, string $productName): bool
    {
        $parse = static function (string $v): ?array {
            $n = preg_replace('/[^a-z0-9]+/', '', mb_strtolower($v, 'UTF-8')) ?? '';
            if (!preg_match('/(ryzen[3579]?|i[3579](?=\d)|rtx|gtx|rx)(\d{3,5}[a-z0-9]*)/', $n, $m)) { return null; }
            return [$m[1], $m[2]];
        };
        $a = $parse($alias); $p = $parse($productName);
        return $a !== null && $a === $p;
    }

    /**
     * @param list<array<string,mixed>> $rows rows shaped like UnresolvedListingQueue::page()
     * @return array{groups:list<array<string,mixed>>,no_suggestion:int,total:int,stale:int}
     */
    public function build(array $rows): array
    {
        $groups = []; $none = 0; $stale = 0;
        foreach ($rows as $r) {
            // decided before the catalog grew: the CURRENT resolver already matches it, so it needs a re-import, not an alias
            if ($this->resolver !== null && $this->resolver->resolve((string)$r['title'])->productId !== null) { $stale++; continue; }
            $top = $r['suggestions'][0] ?? null;
            $alias = (string)($r['proposed_alias'] ?? '');
            if ($top === null || $alias === '' || UnresolvedListingQueue::aliasProblems($alias) !== []) { $none++; continue; }
            $key = $top['product_id'] . '|' . UnresolvedListingQueue::normalize($alias);
            if (!isset($groups[$key])) {
                $impact = $this->queue->previewImpact((string)$r['title'], (int)$top['product_id'], $alias);
                $suffixOk = UnresolvedListingQueue::suffixSet($alias) === UnresolvedListingQueue::suffixSet((string)$top['name']) && self::sameModel($alias, (string)$top['name']);
                $groups[$key] = ['product_id' => (int)$top['product_id'], 'product' => (string)$top['name'], 'alias' => $alias, 'listings' => 0,
                    'safe' => $suffixOk && $impact['resolves_chosen'] && $impact['changed_others'] === 0, 'would_newly_resolve' => $impact['newly_resolved'],
                    'sample_listing_id' => (int)$r['raw_id']];
            }
            $groups[$key]['listings']++;
        }
        $list = array_values($groups);
        usort($list, static fn(array $a, array $b): int => [$b['safe'], $b['listings']] <=> [$a['safe'], $a['listings']]);
        return ['groups' => $list, 'no_suggestion' => $none, 'total' => count($rows), 'stale' => $stale];
    }
}
