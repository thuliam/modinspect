<?php
declare(strict_types=1);

/**
 * LS-89 BE-3: recovery path when a user lost the authenticator and the recovery codes. Removes that user's second factor so they can sign in with
 * the password and enrol again. Needs shell access to the server.  php cli/mfa_reset.php --email=<address>
 * Exit: 0 removed, 1 nothing to remove or unknown user, 2 usage.
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\Auth\MfaService;

$email = '';
foreach (array_slice($argv, 1) as $a) { if (str_starts_with($a, '--email=')) { $email = mb_strtolower(trim(substr($a, 8)), 'UTF-8'); } }
if ($email === '') { fwrite(STDERR, "Usage: php cli/mfa_reset.php --email=<address>\n"); exit(2); }
global $config;
$db = Database::connection($config['db']);
$stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
$stmt->execute([$email]);
$id = (int)$stmt->fetchColumn();
if ($id < 1) { fwrite(STDERR, "No user with that email.\n"); exit(1); }
if (!(new MfaService($db))->remove($id, null, 'cli')) { echo "That user has no second factor.\n"; exit(1); }
echo "Second factor removed. The user signs in with the password and can enrol again.\n";
