<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;

global $config;
$db = Database::connection($config['db']);
$path = $argv[1] ?? null;

if ($path === null) {
    fwrite(STDERR, "Usage: php cli/migrate.php database/migrations/file.sql\n");
    exit(2);
}

$fullPath = ROOT_PATH . '/' . str_replace('\\', '/', $path);
if (!is_file($fullPath)) {
    fwrite(STDERR, "Migration not found: {$path}\n");
    exit(2);
}

$sql = (string)file_get_contents($fullPath);
$statements = [];
$buffer = '';
$quote = null;
$length = strlen($sql);

for ($i = 0; $i < $length; $i++) {
    $char = $sql[$i];
    $prev = $i > 0 ? $sql[$i - 1] : '';

    if (($char === "'" || $char === '"') && $prev !== '\\') {
        $quote = $quote === $char ? null : ($quote ?? $char);
    }

    if ($char === ';' && $quote === null) {
        $statement = trim($buffer);
        if ($statement !== '') {
            $statements[] = $statement;
        }
        $buffer = '';
        continue;
    }

    $buffer .= $char;
}

$tail = trim($buffer);
if ($tail !== '') {
    $statements[] = $tail;
}

foreach ($statements as $statement) {
    if ($statement === '' || strtoupper($statement) === 'USE MODINSPECT') {
        continue;
    }
    $db->exec($statement);
}

echo "Applied {$path}\n";
