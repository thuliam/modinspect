<?php
declare(strict_types=1);

namespace App\Services\Admin;

/**
 * Explains, for one stored snapshot, what stands between its confidence and "medium" (score 60). Pure derivation from the
 * snapshot's own stored policy caps and raw score (confidence-v2): it never recalculates, never changes a price or a label.
 */
final class ConfidenceGap
{
    public const MEDIUM_AT = 60.0;

    /**
     * @param list<string> $caps policy caps stored in the snapshot manifest
     * @return array{reaches_medium:bool,blockers:list<string>,notes:list<string>}
     */
    public static function explain(string $label, array $caps, float $rawScore, int $included): array
    {
        if (in_array($label, ['medium', 'high'], true)) {
            return ['reaches_medium' => true, 'blockers' => [], 'notes' => []];
        }
        $blockers = []; $notes = [];
        if ($included < 3 || in_array('INSUFFICIENT_SAMPLE', $caps, true)) {
            $blockers[] = 'Needs at least 3 included observations (has ' . $included . ').';
        }
        if (in_array('OWNER_TEXT_ONLY_CAP', $caps, true)) {
            $blockers[] = 'Every included observation is owner text without a listing URL, which caps the score below 60. At least one included observation from a different kind of source is needed.';
        }
        if ($rawScore < self::MEDIUM_AT && !in_array('INSUFFICIENT_SAMPLE', $caps, true)) {
            $blockers[] = 'Raw score ' . number_format($rawScore, 1) . ' is below 60 before caps: more, fresher or more varied observations raise it.';
        }
        if (in_array('TINY_SAMPLE_CAP', $caps, true)) { $notes[] = 'Fewer than 5 included observations also caps the score at 64.'; }
        if (in_array('SINGLE_SOURCE_CAP', $caps, true)) { $notes[] = 'A single source caps the score at 74.'; }
        if (in_array('STALE_COHORT_CAP', $caps, true)) { $notes[] = 'The median observation is older than 90 days, which caps the score at 64.'; }
        if (in_array('WEAK_EVIDENCE_CAP', $caps, true)) { $notes[] = 'Mostly weak evidence caps the score at 69.'; }
        return ['reaches_medium' => $blockers === [], 'blockers' => $blockers, 'notes' => $notes];
    }
}
