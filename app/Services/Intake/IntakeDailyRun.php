<?php
declare(strict_types=1);

namespace App\Services\Intake;

use App\Services\Collection\OfflineEvidenceImportService;
use App\Services\Pricing\PriceSnapshotService;
use App\Services\Pricing\SnapshotRecompute;
use PDO;

/**
 * LS-23 E1: one run over a batch folder: check every file against the contract, then take each CLEAN dataset through its own importer and report what happened.
 * DRY RUN unless $apply. A dataset with any problem is skipped as a whole (strict), never half imported. Real rows still go to the review queue as PENDING and
 * a person approves them; nothing here approves an observation, edits a formula or applies a migration (C and D only report what a migration would contain).
 * The report carries counts and codes only, never a title, price or link.
 */
final class IntakeDailyRun
{
    /** @return array<string,mixed> */
    public static function run(PDO $db, string $dir, bool $apply, int $actorId, string $workDir): array
    {
        $c = IntakeValidator::contract();
        $ctx = IntakeContext::fromDb($db);
        $res = []; $report = ['mode' => $apply ? 'apply' : 'dry run', 'files' => [], 'steps' => [], 'skipped' => []];
        foreach ($c['datasets'] as $key => $d) {
            $path = $dir . '/' . $d['file'];
            if (is_file($path)) { $res[$key] = IntakeValidator::validateFile($key, $path, $ctx); }
        }
        foreach ($res as $key => $r) {
            $report['files'][$key] = ['rows' => $r['rows'], 'valid_rows' => $r['valid_rows'], 'problem_codes' => $r['codes'], 'header_errors' => $r['header_errors']];
            if ($r['header_errors'] !== [] || $r['row_errors'] !== []) { $report['skipped'][$key] = 'has problems: not imported (strict)'; }
        }
        $clean = static fn(string $k): bool => isset($res[$k]) && !isset($report['skipped'][$k]) && $res[$k]['valid_rows'] > 0;
        $report['coverage'] = IntakeCoverage::evaluate($res, $ctx);
        unset($ctx['resolve']);

        if ($clean('A')) {
            $m = IntakeExtras::merge($res['A']['valid'], isset($res['A_extra']) && !isset($report['skipped']['A_extra']) ? $res['A_extra']['valid'] : []);
            if ($m['records'] !== []) {
                if (!is_dir($workDir)) { mkdir($workDir, 0775, true); }
                $file = $workDir . '/second_source_' . bin2hex(random_bytes(3)) . '.csv';
                file_put_contents($file, \App\Services\Admin\IntakeTargets::csv(IntakeExtras::COLUMNS, array_map('array_values', $m['records'])));
                $imp = (new OfflineEvidenceImportService())->import($file, 'real', !$apply, 300);
                @unlink($file);
                $report['steps']['A_used_listings'] = ['left_out' => $m['left_out'], 'valid_rows' => $imp['valid_rows'] ?? null, 'invalid_rows' => $imp['invalid_rows'] ?? null, 'candidates_created' => $imp['candidates_created'] ?? 0,
                    'row_errors' => count((array)($imp['row_errors'] ?? []))];
            }
        }
        if ($clean('B')) { $report['steps']['B_new_prices'] = $apply && $actorId < 1 ? ['error' => 'ACTOR_REQUIRED'] : IntakeNewPrices::import($db, $actorId, $res['B']['valid'], $apply); }
        if ($clean('F')) {
            $resolver = new \App\Services\ProductResolver\ProductResolver((new \App\Models\CollectionPipeline())->catalog());
            $report['steps']['F_sold_prices'] = $apply && $actorId < 1 ? ['error' => 'ACTOR_REQUIRED'] : IntakeSoldPrices::import($db, $actorId, $res['F']['valid'], $resolver, $apply);
        }
        if ($clean('C')) { $g = IntakeSpecsMigration::generate($res['C']['valid']); $report['steps']['C_specs'] = ['would_insert_specs' => $g['specs'], 'models' => $g['models'], 'next' => 'php cli/intake_generate_specs_migration.php <file>']; }
        if ($clean('D_products')) {
            $g = IntakeProductsMigration::generate($res['D_products']['valid'], $clean('D_aliases') ? $res['D_aliases']['valid'] : []);
            $report['steps']['D_models'] = ['would_insert_models' => $g['models'], 'aliases' => $g['aliases'], 'aliases_dropped' => count($g['dropped']), 'bare_number_aliases' => count($g['risky']), 'next' => 'php cli/intake_generate_products_migration.php <folder>'];
        }
        if ($clean('E')) { $report['steps']['E_condition_checks'] = ['rows' => $res['E']['valid_rows'], 'note' => 'content is a draft for owner approval; no importer yet'];}
        $plan = (new SnapshotRecompute($db, new PriceSnapshotService()))->plan('asking');
        $report['snapshot_plan'] = ['counts' => SnapshotRecompute::counts($plan), 'note' => $apply ? 'imported rows are pending review: prices change only after a person approves them and the snapshot is recalculated' : 'dry run'];
        return $report;
    }
}
