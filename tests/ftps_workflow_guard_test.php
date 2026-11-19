<?php
declare(strict_types=1);

/**
 * Guards the manual FTPS deploy workflow (.github/workflows/ftps-deploy.yml) in text, because nothing else runs it until the owner does:
 * the package job only runs from main; deploy needs the confirm word; the FTP account is pinned; certificates are verified; nothing is ever
 * deleted on the host and .env/storage are never named as upload targets; and (LS-14 review) the smoke job runs the script from the workflow's own
 * commit, not from the release ref, so rolling back to an older tag still finds cli/smoke_check.php.
 */
function ok_fw(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }
$wf = str_replace("\r\n", "\n", (string)file_get_contents(dirname(__DIR__) . '/.github/workflows/ftps-deploy.yml'));
ok_fw($wf !== '', 'The workflow exists');
ok_fw(str_contains($wf, "if: github.ref == 'refs/heads/main'"), 'The package job runs from main only');
ok_fw(str_contains($wf, "inputs.mode == 'deploy' && inputs.confirm != 'DEPLOY'") && str_contains($wf, 'default: plan'), 'Deploy needs the word DEPLOY and the default mode is plan');
ok_fw(str_contains($wf, 'modinspect@thuliamdev.com') && str_contains($wf, 'FTP_TLS(context=ssl.create_default_context())'), 'The FTP account is pinned and the certificate is verified');
ok_fw(!preg_match('/\.(delete|rmd|rmtree)\(|rm -rf|\bDELE\b|\bRMD\b/', $wf), 'The workflow never deletes anything on the host');
ok_fw(str_contains($wf, 'products-mfr') && str_contains($wf, ".env"), 'It refuses a package that carries .env or the unlicensed product photos');
// the smoke job
$smoke = substr($wf, (int)strpos($wf, "\n  smoke:"));
ok_fw(str_contains($smoke, 'php cli/smoke_check.php https://modinspect.thuliamdev.com'), 'The smoke job runs the shared smoke script');
ok_fw(preg_match('/ref:\s*\$\{\{\s*inputs\.ref/', "ref: \${{ inputs.ref }}") === 1 && !preg_match('/ref:\s*\$\{\{\s*inputs\.ref/', $smoke),'The smoke job does not check out the release ref (an older tag has no smoke script)');
ok_fw(str_contains($smoke, "if: inputs.mode == 'deploy'"), 'Smoke runs only after a real deploy');
echo "ftps_workflow_guard_test: PASS\n";
