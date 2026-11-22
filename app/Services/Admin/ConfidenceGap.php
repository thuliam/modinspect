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
        $human = in_array('HUMAN_RECORDED_EVIDENCE_CAP', $caps, true) ? [\App\Core\Lang::t('cg.human_cap')] : [];
        if (in_array($label, ['medium', 'high'], true)) {
            return ['reaches_medium' => true, 'blockers' => [], 'notes' => $human];
        }
        $blockers = []; $notes = $human;
        if ($included < 3 || in_array('INSUFFICIENT_SAMPLE', $caps, true)) {
            $blockers[] = \App\Core\Lang::t('cg.need_3', ['n' => $included]);
        }
        if (in_array('OWNER_TEXT_ONLY_CAP', $caps, true)) {
            $blockers[] = \App\Core\Lang::t('cg.owner_only');
        }
        if ($rawScore < self::MEDIUM_AT && !in_array('INSUFFICIENT_SAMPLE', $caps, true)) {
            $blockers[] = \App\Core\Lang::t('cg.raw_low', ['s' => number_format($rawScore, 1)]);
        }
        if (in_array('TINY_SAMPLE_CAP', $caps, true)) { $notes[] = \App\Core\Lang::t('cg.tiny'); }
        if (in_array('SINGLE_SOURCE_CAP', $caps, true)) { $notes[] = \App\Core\Lang::t('cg.single'); }
        if (in_array('STALE_COHORT_CAP', $caps, true)) { $notes[] = \App\Core\Lang::t('cg.stale'); }
        if (in_array('WEAK_EVIDENCE_CAP', $caps, true)) { $notes[] = \App\Core\Lang::t('cg.weak'); }
        return ['reaches_medium' => $blockers === [], 'blockers' => $blockers, 'notes' => $notes];
    }
}
