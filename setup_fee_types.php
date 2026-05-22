<?php
// File: setup_fee_types.php
// One-time setup: Creates fee_types table and seeds default fee data
require_once 'config/db.php';

$database = new Database();
$db = $database->getConnection();

$messages = [];
$errors   = [];

// ── Step 1: Create table ────────────────────────────────────────────────────
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS `fee_types` (
            `id`          INT(11)        NOT NULL AUTO_INCREMENT,
            `fee_name`    VARCHAR(100)   NOT NULL,
            `amount`      DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
            `description` VARCHAR(255)   DEFAULT NULL,
            `is_active`   TINYINT(1)     NOT NULL DEFAULT 1,
            `created_at`  TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at`  TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `fee_name` (`fee_name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    $messages[] = '✅ <strong>Table check complete:</strong> <code>fee_types</code> table is ready.';
} catch (PDOException $e) {
    $errors[] = '❌ <strong>Table creation failed:</strong> ' . htmlspecialchars($e->getMessage());
}

// ── Step 2: Seed data only if table is empty ────────────────────────────────
if (empty($errors)) {
    try {
        $count = (int) $db->query("SELECT COUNT(*) FROM fee_types")->fetchColumn();

        if ($count === 0) {
            $feeData = [
                ['Admission Fee',       5000.00, 'One-time admission processing fee'],
                ['Tuition Fee',         3000.00, 'Monthly tuition / class fee'],
                ['Exam Fee',            1500.00, 'Per-semester examination fee'],
                ['Library Fee',          500.00, 'Annual library access fee'],
                ['Lab Fee',             1000.00, 'Laboratory usage fee'],
                ['Sports Fee',           500.00, 'Sports & physical education fee'],
                ['Late Fee',             200.00, 'Late payment penalty fee'],
                ['Transport Fee',       2000.00, 'Monthly transport / bus fee'],
                ['Hostel Fee',          5000.00, 'Monthly hostel accommodation fee'],
                ['Magazine Fee',         300.00, 'Annual college magazine fee'],
                ['Degree Fee',          2000.00, 'Degree / certificate issuance fee'],
                ['NAVTTC Course Fee',      0.00, 'Government funded — no charge'],
            ];

            $stmt = $db->prepare(
                "INSERT INTO fee_types (fee_name, amount, description) VALUES (?, ?, ?)"
            );

            $inserted = 0;
            foreach ($feeData as [$name, $amount, $desc]) {
                $stmt->execute([$name, $amount, $desc]);
                $inserted++;
            }

            $messages[] = "✅ <strong>Data seeded:</strong> {$inserted} fee types inserted successfully.";
        } else {
            $messages[] = "ℹ️ <strong>Skipped seeding:</strong> Table already has {$count} fee type(s) — existing data preserved.";
        }
    } catch (PDOException $e) {
        $errors[] = '❌ <strong>Data seeding failed:</strong> ' . htmlspecialchars($e->getMessage());
    }
}

// ── Auto-redirect after 3 s if everything is OK ─────────────────────────────
$allGood = empty($errors);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Types Setup | QAC Portal</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Space+Mono:wght@700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        :root {
            --teal: #4ec2b5;
            --teal-dark: #3da89b;
            --navy: #0f2d48;
            --navy-light: #1a3f63;
        }

        body {
            background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 60%, #1a5276 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'DM Sans', sans-serif;
        }

        .setup-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.35);
            width: 100%;
            max-width: 620px;
            overflow: hidden;
            animation: fadeInUp .5s ease both;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .setup-header {
            background: linear-gradient(135deg, var(--navy), var(--navy-light));
            color: #fff;
            padding: 2rem 2rem 1.5rem;
            text-align: center;
        }

        .setup-header .icon-wrap {
            width: 72px; height: 72px;
            background: rgba(78,194,181,.2);
            border: 2px solid var(--teal);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1rem;
            font-size: 2rem; color: var(--teal);
        }

        .setup-header h1 { font-size: 1.45rem; font-weight: 700; margin: 0; }
        .setup-header p  { font-size: 0.85rem; opacity: .75; margin: .4rem 0 0; }

        .setup-body { padding: 2rem; }

        .step-item {
            display: flex;
            gap: .75rem;
            padding: .85rem 1rem;
            border-radius: 12px;
            margin-bottom: .6rem;
            font-size: .92rem;
            line-height: 1.5;
        }
        .step-item.success { background: #f0fdf4; border-left: 4px solid #22c55e; }
        .step-item.error   { background: #fff1f2; border-left: 4px solid #f43f5e; }
        .step-item.info    { background: #eff6ff; border-left: 4px solid #3b82f6; }

        .step-item i { margin-top: .15rem; flex-shrink: 0; }

        .redirect-box {
            background: linear-gradient(135deg, var(--teal), var(--teal-dark));
            color: #fff;
            border-radius: 12px;
            padding: 1.25rem 1.5rem;
            text-align: center;
            margin-top: 1.5rem;
        }
        .redirect-box .countdown {
            font-family: 'Space Mono', monospace;
            font-size: 2rem;
            font-weight: 700;
            display: block;
            line-height: 1;
        }
        .redirect-box p { margin: .4rem 0 0; font-size: .85rem; opacity: .9; }

        .progress-bar-wrap {
            background: rgba(255,255,255,.25);
            border-radius: 100px;
            height: 5px;
            margin-top: .75rem;
            overflow: hidden;
        }
        .progress-bar-fill {
            height: 100%;
            background: #fff;
            border-radius: 100px;
            width: 100%;
            transition: width 3s linear;
        }

        .btn-manual {
            background: var(--navy);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: .65rem 1.5rem;
            font-weight: 600;
            width: 100%;
            margin-top: 1rem;
            transition: background .2s;
            text-decoration: none;
            display: block;
            text-align: center;
        }
        .btn-manual:hover { background: var(--navy-light); color: #fff; }

        .error-footer { margin-top: 1.5rem; }
    </style>

    <?php if ($allGood): ?>
    <meta http-equiv="refresh" content="3;url=modules/fee_management/add_collection.php">
    <?php endif; ?>
</head>
<body>
<div class="setup-card">
    <!-- Header -->
    <div class="setup-header">
        <div class="icon-wrap">
            <i class="fas fa-database"></i>
        </div>
        <h1>Fee Types Setup</h1>
        <p>Quaid-e-Azam Group of Colleges &mdash; ERP System</p>
    </div>

    <!-- Body -->
    <div class="setup-body">

        <?php foreach ($messages as $msg): ?>
            <?php
                $cls = (strpos($msg, '✅') !== false) ? 'success' : 'info';
                $ico = ($cls === 'success') ? 'fa-check-circle text-success' : 'fa-info-circle text-primary';
            ?>
            <div class="step-item <?= $cls ?>">
                <i class="fas <?= $ico ?> mt-1"></i>
                <span><?= $msg ?></span>
            </div>
        <?php endforeach; ?>

        <?php foreach ($errors as $err): ?>
            <div class="step-item error">
                <i class="fas fa-times-circle text-danger mt-1"></i>
                <span><?= $err ?></span>
            </div>
        <?php endforeach; ?>

        <?php if ($allGood): ?>
        <!-- Auto-redirect countdown -->
        <div class="redirect-box">
            <span class="countdown" id="countdown">3</span>
            <p><i class="fas fa-arrow-right me-1"></i> Redirecting to Add Fee Collection&hellip;</p>
            <div class="progress-bar-wrap">
                <div class="progress-bar-fill" id="progressBar"></div>
            </div>
        </div>
        <a href="modules/fee_management/add_collection.php" class="btn-manual">
            <i class="fas fa-bolt me-2"></i>Go Now &mdash; Add Fee Collection
        </a>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Shrink progress bar
            setTimeout(() => {
                document.getElementById('progressBar').style.width = '0%';
            }, 50);

            // Live countdown
            let count = 3;
            const el = document.getElementById('countdown');
            const timer = setInterval(() => {
                count--;
                el.textContent = count;
                if (count <= 0) {
                    clearInterval(timer);
                    window.location.href = 'modules/fee_management/add_collection.php';
                }
            }, 1000);
        });
        </script>

        <?php else: ?>
        <!-- Errors — show manual link only -->
        <div class="error-footer text-center">
            <p class="text-muted mb-3" style="font-size:.9rem;">
                Please fix the errors above, then try again.
            </p>
            <a href="setup_fee_types.php" class="btn-manual" style="background:var(--teal);">
                <i class="fas fa-redo me-2"></i>Retry Setup
            </a>
        </div>
        <?php endif; ?>

    </div><!-- /.setup-body -->
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
