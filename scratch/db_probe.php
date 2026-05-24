<?php
// scratch/db_probe.php
// Small helper for local discovery (prints to stdout).

require_once __DIR__ . '/../config/db.php';

$db = (new Database())->getConnection();

$mode = $argv[1] ?? '';

if ($mode === 'show_like') {
    $like = $argv[2] ?? '';
    $stmt = $db->prepare("SHOW TABLES LIKE ?");
    $stmt->execute([$like]);
    $rows = $stmt->fetchAll(PDO::FETCH_NUM);
    foreach ($rows as $r) {
        echo $r[0] . PHP_EOL;
    }
    exit;
}

if ($mode === 'describe') {
    $table = $argv[2] ?? '';
    if ($table === '') {
        fwrite(STDERR, "Missing table name\n");
        exit(2);
    }
    $rows = $db->query("DESCRIBE `$table`")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        echo ($r['Field'] ?? '') . "\t" . ($r['Type'] ?? '') . "\t" . ($r['Null'] ?? '') . "\t" . ($r['Key'] ?? '') . PHP_EOL;
    }
    exit;
}

if ($mode === 'show_tables') {
    $rows = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($rows as $t) {
        echo $t . PHP_EOL;
    }
    exit;
}

fwrite(STDERR, "Usage:\n");
fwrite(STDERR, "  php scratch/db_probe.php show_tables\n");
fwrite(STDERR, "  php scratch/db_probe.php show_like <pattern>\n");
fwrite(STDERR, "  php scratch/db_probe.php describe <table>\n");
exit(2);

