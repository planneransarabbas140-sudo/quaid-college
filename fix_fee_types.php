<?php
// File: fix_fee_types.php
// Migration: Adds missing columns to fee_types table without touching existing data
require_once 'config/db.php';

$database = new Database();
$db = $database->getConnection();

$steps   = [];   // ['type'=>'success|error|info|warning', 'msg'=>'...']
$columns = [];   // columns found after fix
$rows    = [];   // existing fee_type rows

// ── Helper: check if a column exists ────────────────────────────────────────
function columnExists(PDO $db, string $table, string $column): bool {
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME   = ?
           AND COLUMN_NAME  = ?"
    );
    $stmt->execute([$table, $column]);
    return (bool) $stmt->fetchColumn();
}

// ── Helper: check if a table exists ─────────────────────────────────────────
function tableExists(PDO $db, string $table): bool {
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME   = ?"
    );
    $stmt->execute([$table]);
    return (bool) $stmt->fetchColumn();
}

// ════════════════════════════════════════════════════════════════════════════
// STEP 1 — Verify table exists
// ════════════════════════════════════════════════════════════════════════════
if (!tableExists($db, 'fee_types')) {
    $steps[] = ['type' => 'error',
                'msg'  => '❌ <strong>Table not found:</strong> <code>fee_types</code> does not exist. '
                        . 'Please run <a href="setup_fee_types.php" class="alert-link">setup_fee_types.php</a> first.'];
} else {
    $steps[] = ['type' => 'success',
                'msg'  => '✅ <strong>Table found:</strong> <code>fee_types</code> exists in the database.'];

    // ════════════════════════════════════════════════════════════════════════
    // STEP 2 — Add `description` column if missing
    // ════════════════════════════════════════════════════════════════════════
    if (!columnExists($db, 'fee_types', 'description')) {
        try {
            $db->exec("ALTER TABLE `fee_types` ADD COLUMN `description` TEXT DEFAULT NULL AFTER `amount`");
            $steps[] = ['type' => 'success',
                        'msg'  => '✅ <strong>Column added:</strong> <code>description TEXT</code> → added after <code>amount</code>.'];
        } catch (PDOException $e) {
            $steps[] = ['type' => 'error',
                        'msg'  => '❌ <strong>Failed to add <code>description</code>:</strong> ' . htmlspecialchars($e->getMessage())];
        }
    } else {
        $steps[] = ['type' => 'info',
                    'msg'  => 'ℹ️ <strong>Skipped:</strong> <code>description</code> column already exists — no change made.'];
    }

    // ════════════════════════════════════════════════════════════════════════
    // STEP 3 — Add `is_active` column if missing
    // ════════════════════════════════════════════════════════════════════════
    if (!columnExists($db, 'fee_types', 'is_active')) {
        try {
            $db->exec("ALTER TABLE `fee_types` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `description`");
            $steps[] = ['type' => 'success',
                        'msg'  => '✅ <strong>Column added:</strong> <code>is_active TINYINT(1) DEFAULT 1</code> → added after <code>description</code>.'];
        } catch (PDOException $e) {
            $steps[] = ['type' => 'error',
                        'msg'  => '❌ <strong>Failed to add <code>is_active</code>:</strong> ' . htmlspecialchars($e->getMessage())];
        }
    } else {
        $steps[] = ['type' => 'info',
                    'msg'  => 'ℹ️ <strong>Skipped:</strong> <code>is_active</code> column already exists — no change made.'];
    }

    // ════════════════════════════════════════════════════════════════════════
    // STEP 4 — Confirm all required columns now exist
    // ════════════════════════════════════════════════════════════════════════
    $required = ['id', 'fee_name', 'amount', 'description', 'is_active', 'created_at'];
    $missing  = [];
    foreach ($required as $col) {
        if (!columnExists($db, 'fee_types', $col)) {
            $missing[] = $col;
        }
    }

    if (empty($missing)) {
        $steps[] = ['type' => 'success',
                    'msg'  => '✅ <strong>Schema verified:</strong> All required columns ('
                            . implode(', ', array_map(fn($c) => "<code>$c</code>", $required))
                            . ') are present.'];
    } else {
        foreach ($missing as $col) {
            $steps[] = ['type' => 'warning',
                        'msg'  => "⚠️ <strong>Still missing:</strong> <code>$col</code> column was not found after migration."];
        }
    }

    // ════════════════════════════════════════════════════════════════════════
    // STEP 5 — Fetch live table structure & data
    // ════════════════════════════════════════════════════════════════════════
    try {
        $columns = $db->query("DESCRIBE `fee_types`")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $steps[] = ['type' => 'error', 'msg' => '❌ DESCRIBE failed: ' . htmlspecialchars($e->getMessage())];
    }

    try {
        $rows = $db->query("SELECT id, fee_name, amount, description, is_active, created_at FROM fee_types ORDER BY fee_name ASC")->fetchAll(PDO::FETCH_ASSOC);
        $steps[] = ['type' => 'info',
                    'msg'  => 'ℹ️ <strong>Data check:</strong> ' . count($rows) . ' fee type(s) found — existing data is untouched.'];
    } catch (PDOException $e) {
        $steps[] = ['type' => 'error', 'msg' => '❌ Data fetch failed: ' . htmlspecialchars($e->getMessage())];
    }
}

// Did any step fail?
$hasError = !empty(array_filter($steps, fn($s) => $s['type'] === 'error'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix Fee Types Schema | QAC Portal</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Space+Mono:wght@700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        :root {
            --teal:       #4ec2b5;
            --teal-dark:  #3aa898;
            --navy:       #0f2d48;
            --navy-light: #1a3f63;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: linear-gradient(140deg, var(--navy) 0%, var(--navy-light) 55%, #1a5276 100%);
            min-height: 100vh;
            padding: 2rem 1rem;
        }

        /* ── Main card ── */
        .fix-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 30px 70px rgba(0,0,0,.4);
            overflow: hidden;
            animation: fadeUp .45s ease both;
        }
        @keyframes fadeUp {
            from { opacity:0; transform:translateY(28px); }
            to   { opacity:1; transform:translateY(0); }
        }

        /* ── Header ── */
        .fix-header {
            background: linear-gradient(135deg, var(--navy), var(--navy-light));
            color: #fff;
            padding: 2rem;
            text-align: center;
        }
        .fix-header .icon-ring {
            width: 70px; height: 70px;
            background: rgba(78,194,181,.15);
            border: 2px solid var(--teal);
            border-radius: 50%;
            display: flex; align-items:center; justify-content:center;
            margin: 0 auto 1rem;
            font-size: 1.8rem; color: var(--teal);
        }
        .fix-header h1 { font-size: 1.4rem; font-weight: 700; margin: 0; }
        .fix-header p  { font-size: .82rem; opacity: .7; margin: .35rem 0 0; }

        /* ── Step log ── */
        .step-list { padding: 1.75rem 2rem 0; }
        .step-item {
            display: flex; gap: .75rem; align-items: flex-start;
            padding: .8rem 1rem;
            border-radius: 10px;
            margin-bottom: .55rem;
            font-size: .9rem; line-height: 1.5;
        }
        .step-item i   { margin-top: .1rem; flex-shrink: 0; }
        .step-item.s   { background: #f0fdf4; border-left: 4px solid #22c55e; }
        .step-item.e   { background: #fff1f2; border-left: 4px solid #f43f5e; }
        .step-item.i   { background: #eff6ff; border-left: 4px solid #3b82f6; }
        .step-item.w   { background: #fffbeb; border-left: 4px solid #f59e0b; }

        /* ── Tables section ── */
        .tables-section { padding: 1.5rem 2rem 2rem; }
        .section-label {
            font-size: .7rem; font-weight: 700; letter-spacing: 1.5px;
            text-transform: uppercase; color: var(--navy); opacity: .5;
            margin-bottom: .6rem;
        }
        .data-table th { background: var(--navy); color: #fff; font-size: .78rem; }
        .data-table td { font-size: .82rem; vertical-align: middle; }
        .badge-active  { background: #dcfce7; color: #15803d; border-radius: 6px; padding: 2px 8px; font-size:.75rem; }
        .badge-inactive{ background: #fee2e2; color: #b91c1c; border-radius: 6px; padding: 2px 8px; font-size:.75rem; }
        code { background: #f1f5f9; padding: 1px 5px; border-radius: 4px; color: var(--navy); }

        /* ── Redirect strip ── */
        .redirect-strip {
            background: linear-gradient(135deg, var(--teal), var(--teal-dark));
            color: #fff;
            padding: 1.25rem 2rem;
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: .75rem;
        }
        .redirect-strip .count {
            font-family: 'Space Mono', monospace;
            font-size: 1.8rem; font-weight: 700; line-height: 1;
        }
        .redirect-strip p { margin: .2rem 0 0; font-size: .82rem; opacity: .9; }
        .progress-wrap { background: rgba(255,255,255,.25); border-radius: 100px; height: 4px; width: 200px; overflow: hidden; }
        .progress-fill { height: 100%; background: #fff; border-radius: 100px; width: 100%; transition: width 3s linear; }
        .btn-go {
            background: var(--navy); color: #fff; border: none;
            border-radius: 10px; padding: .55rem 1.4rem;
            font-weight: 600; font-size: .9rem; text-decoration: none;
            transition: background .2s; white-space: nowrap;
        }
        .btn-go:hover { background: var(--navy-light); color: #fff; }
        .btn-retry { background: #f43f5e; }
        .btn-retry:hover { background: #e11d48; }
    </style>

    <?php if (!$hasError): ?>
    <meta http-equiv="refresh" content="4;url=modules/fee_management/structure.php">
    <?php endif; ?>
</head>
<body>
<div class="container" style="max-width:860px;">

    <div class="fix-card">

        <!-- Header -->
        <div class="fix-header">
            <div class="icon-ring"><i class="fas fa-wrench"></i></div>
            <h1>Fee Types — Schema Migration</h1>
            <p>Quaid-e-Azam Group of Colleges &mdash; ERP Database Fix</p>
        </div>

        <!-- Step log -->
        <div class="step-list">
            <?php
            $iconMap = [
                'success' => ['cls'=>'s', 'ico'=>'fa-check-circle text-success'],
                'error'   => ['cls'=>'e', 'ico'=>'fa-times-circle text-danger'],
                'info'    => ['cls'=>'i', 'ico'=>'fa-info-circle text-primary'],
                'warning' => ['cls'=>'w', 'ico'=>'fa-exclamation-triangle text-warning'],
            ];
            foreach ($steps as $step):
                $map = $iconMap[$step['type']] ?? $iconMap['info'];
            ?>
            <div class="step-item <?= $map['cls'] ?>">
                <i class="fas <?= $map['ico'] ?>"></i>
                <span><?= $step['msg'] ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Table structure -->
        <?php if (!empty($columns)): ?>
        <div class="tables-section">
            <div class="section-label"><i class="fas fa-table me-1"></i>Live Table Structure — fee_types</div>
            <div class="table-responsive mb-4">
                <table class="table table-sm table-bordered data-table mb-0">
                    <thead>
                        <tr>
                            <th>Column</th>
                            <th>Type</th>
                            <th>Null</th>
                            <th>Key</th>
                            <th>Default</th>
                            <th>Extra</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($columns as $col): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($col['Field']) ?></code></td>
                            <td><?= htmlspecialchars($col['Type']) ?></td>
                            <td><?= htmlspecialchars($col['Null']) ?></td>
                            <td><?= htmlspecialchars($col['Key']) ?></td>
                            <td><?= htmlspecialchars($col['Default'] ?? 'NULL') ?></td>
                            <td><?= htmlspecialchars($col['Extra']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Existing data -->
            <div class="section-label"><i class="fas fa-list me-1"></i>Existing Fee Types (<?= count($rows) ?> record<?= count($rows) !== 1 ? 's' : '' ?>)</div>
            <?php if (empty($rows)): ?>
                <p class="text-muted small text-center py-3">No fee types found in table — run <a href="setup_fee_types.php">setup_fee_types.php</a> to seed data.</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm table-hover data-table mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Fee Name</th>
                            <th>Amount (PKR)</th>
                            <th>Description</th>
                            <th>Active</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><span class="badge" style="background:var(--navy);"><?= (int)$row['id'] ?></span></td>
                            <td class="fw-semibold"><?= htmlspecialchars($row['fee_name']) ?></td>
                            <td class="text-success fw-semibold"><?= number_format((float)$row['amount'], 2) ?></td>
                            <td class="text-muted"><?= htmlspecialchars($row['description'] ?? '—') ?></td>
                            <td>
                                <?php if ($row['is_active']): ?>
                                    <span class="badge-active">Active</span>
                                <?php else: ?>
                                    <span class="badge-inactive">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted" style="font-size:.75rem;"><?= htmlspecialchars($row['created_at'] ?? '—') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Redirect / action strip -->
        <div class="redirect-strip">
            <?php if (!$hasError): ?>
            <div>
                <span class="count" id="countdown">4</span>
                <p><i class="fas fa-arrow-right me-1"></i>Redirecting to Fee Type Management&hellip;</p>
                <div class="progress-wrap mt-2">
                    <div class="progress-fill" id="progressBar"></div>
                </div>
            </div>
            <a href="modules/fee_management/structure.php" class="btn-go">
                <i class="fas fa-bolt me-1"></i>Go Now
            </a>
            <?php else: ?>
            <div>
                <span style="font-size:1.1rem; font-weight:700;">⚠️ Migration incomplete — check errors above.</span>
                <p class="mt-1">Fix the issues, then retry.</p>
            </div>
            <a href="fix_fee_types.php" class="btn-go btn-retry">
                <i class="fas fa-redo me-1"></i>Retry
            </a>
            <?php endif; ?>
        </div>

    </div><!-- /.fix-card -->
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php if (!$hasError): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Start shrinking bar after a tick
    setTimeout(() => { document.getElementById('progressBar').style.width = '0%'; }, 50);

    let n = 4;
    const el = document.getElementById('countdown');
    const t  = setInterval(() => {
        n--;
        el.textContent = n;
        if (n <= 0) {
            clearInterval(t);
            window.location.href = 'modules/fee_management/structure.php';
        }
    }, 1000);
});
</script>
<?php endif; ?>
</body>
</html>
