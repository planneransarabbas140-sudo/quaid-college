<?php
/**
 * File: fix_attendance.php
 * Purpose: Standardize student class and section names to fix attendance module mismatches.
 */

require_once 'config/db.php';

$database = new Database();
$db = $database->getConnection();

$message = '';
$error = '';
$results = [];

// Step 3 & 4 - Execution logic
if (isset($_POST['run_fix'])) {
    try {
        $db->beginTransaction();

        // Class name updates
        $class_updates = [
            '11th (Pre-Medical)' => ['11th Pre-Medical', '11 Pre-Medical', 'FSc Pre-Medical 1st Year'],
            '12th (Pre-Medical)' => ['12th Pre-Medical', '12 Pre-Medical', 'FSc Pre-Medical 2nd Year'],
            '11th (Pre-Engineering)' => ['11th Pre-Engineering', '11 Pre-Engineering'],
            '12th (Pre-Engineering)' => ['12th Pre-Engineering', '12 Pre-Engineering'],
            '11th (ICS)' => ['11th ICS', '11 ICS', 'ICS 1st Year'],
            '12th (ICS)' => ['12th ICS', '12 ICS', 'ICS 2nd Year'],
            '11th (I.Com)' => ['11th ICom', '11 ICom', '11th I.Com'],
            '12th (I.Com)' => ['12th ICom', '12 ICom', '12th I.Com'],
            '11th (FA)' => ['11th FA', '11 FA'],
            '12th (FA)' => ['12th FA', '12 FA'],
            '11th (Taleem-ul-Islam)' => ['11th Taleem-ul-Islam', '11 Taleem'],
            '12th (Taleem-ul-Islam)' => ['12th Taleem-ul-Islam', '12 Taleem'],
        ];

        $total_classes_updated = 0;
        foreach ($class_updates as $target => $sources) {
            $placeholders = implode(',', array_fill(0, count($sources), '?'));
            $stmt = $db->prepare("UPDATE students SET class = ? WHERE class IN ($placeholders)");
            $params = array_merge([$target], $sources);
            $stmt->execute($params);
            $total_classes_updated += $stmt->rowCount();
        }

        // Section name updates
        $section_updates = [
            'A' => ['Section A', 'section a', 'sec a'],
            'B' => ['Section B', 'section b', 'sec b'],
            'C' => ['Section C', 'section c', 'sec c'],
            'D' => ['Section D', 'section d', 'sec d'],
        ];

        $total_sections_updated = 0;
        foreach ($section_updates as $target => $sources) {
            $placeholders = implode(',', array_fill(0, count($sources), '?'));
            $stmt = $db->prepare("UPDATE students SET section = ? WHERE section IN ($placeholders)");
            $params = array_merge([$target], $sources);
            $stmt->execute($params);
            $total_sections_updated += $stmt->rowCount();
        }

        $db->commit();
        $message = "Successfully updated student records. Classes: $total_classes_updated, Sections: $total_sections_updated.";
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Error during update: " . $e->getMessage();
    }
}

// Step 1 - Fetch current unique values
$stmt = $db->query("SELECT DISTINCT class, section FROM students ORDER BY class, section");
$current_values = $stmt->fetchAll();

// Step 2 - Expected values from attendance module
$expected_classes = [
    '11th (Pre-Medical)', '12th (Pre-Medical)',
    '11th (Pre-Engineering)', '12th (Pre-Engineering)',
    '11th (ICS)', '12th (ICS)',
    '11th (I.Com)', '12th (I.Com)',
    '11th (FA)', '12th (FA)',
    '11th (Taleem-ul-Islam)', '12th (Taleem-ul-Islam)'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix Attendance Data | QUAID COLLEGE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --teal: #4ec2b5;
            --navy: #0f2d48;
            --light-teal: #e0f2f1;
        }
        body {
            background-color: #f4f7f6;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--navy);
        }
        .navbar {
            background-color: var(--navy);
            border-bottom: 4px solid var(--teal);
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        .card-header {
            background-color: #fff;
            border-bottom: 1px solid var(--light-teal);
            border-radius: 15px 15px 0 0 !important;
            padding: 20px;
            font-weight: 700;
            color: var(--navy);
            display: flex;
            align-items: center;
        }
        .card-header i {
            color: var(--teal);
            margin-right: 15px;
            font-size: 1.5rem;
        }
        .btn-primary {
            background-color: var(--navy);
            border-color: var(--navy);
        }
        .btn-primary:hover {
            background-color: #1a4060;
            border-color: #1a4060;
        }
        .btn-teal {
            background-color: var(--teal);
            color: white;
            border: none;
            padding: 12px 30px;
            font-weight: 600;
            border-radius: 10px;
            transition: all 0.3s;
        }
        .btn-teal:hover {
            background-color: #3da89c;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(78, 194, 181, 0.3);
        }
        .table thead {
            background-color: var(--light-teal);
        }
        .status-badge {
            padding: 8px 15px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .bg-teal-light { background-color: var(--light-teal); color: var(--navy); }
        .alert-success {
            background-color: var(--light-teal);
            border-color: var(--teal);
            color: var(--navy);
        }
    </style>
</head>
<body>

<nav class="navbar navbar-dark mb-5">
    <div class="container">
        <a class="navbar-brand fw-bold" href="#">
            <i class="fas fa-graduation-cap me-2"></i> QUAID COLLEGE ERP
        </a>
    </div>
</nav>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="fw-bold">Attendance System Repair</h2>
                <a href="modules/attendance/index.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Attendance
                </a>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success d-flex align-items-center" role="alert">
                    <i class="fas fa-check-circle me-3 fs-4"></i>
                    <div><?php echo $message; ?></div>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger d-flex align-items-center" role="alert">
                    <i class="fas fa-exclamation-triangle me-3 fs-4"></i>
                    <div><?php echo $error; ?></div>
                </div>
            <?php endif; ?>

            <!-- Step 1 & 2 Comparison -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-database"></i> Database vs Module Requirements
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 border-end">
                            <h6 class="fw-bold mb-3 text-muted uppercase">Current Data (Step 1)</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover">
                                    <thead>
                                        <tr>
                                            <th>Class</th>
                                            <th>Section</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($current_values)): ?>
                                            <tr><td colspan="2" class="text-center">No students found</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($current_values as $row): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($row['class']); ?></td>
                                                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($row['section']); ?></span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="fw-bold mb-3 text-muted uppercase">Expected Values (Step 2)</h6>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($expected_classes as $cls): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center py-1 border-0">
                                        <small><?php echo $cls; ?></small>
                                        <span class="badge bg-teal-light rounded-pill">Expected</span>
                                    </li>
                                <?php endforeach; ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center py-1 border-0">
                                    <small>Sections: A, B, C, D</small>
                                    <span class="badge bg-teal-light rounded-pill">Expected</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Fix Action -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-magic"></i> Run Automatic Fix (Step 3 & 4)
                </div>
                <div class="card-body text-center py-5">
                    <p class="lead mb-4">This will standardize all student class and section names to match the attendance module requirements.</p>
                    <form method="POST">
                        <button type="submit" name="run_fix" class="btn btn-teal btn-lg">
                            <i class="fas fa-wrench me-2"></i> Run Fix Now
                        </button>
                    </form>
                    <div class="mt-4 text-muted small">
                        <i class="fas fa-info-circle me-1"></i> Note: This process is safe and will not delete any student data.
                    </div>
                </div>
            </div>

            <!-- Step 5 Info -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-code"></i> Code Update (Step 5)
                </div>
                <div class="card-body">
                    <p>The query in <code>modules/attendance/index.php</code> has been updated to handle flexible formats:</p>
                    <pre class="bg-light p-3 rounded" style="font-size: 0.85rem;">
SELECT * FROM students 
WHERE (class = ? OR class = REPLACE(?, ' ', '') OR class LIKE ?)
AND (section = ? OR section = CONCAT('Section ', ?))
AND status = 'Active'
ORDER BY first_name, last_name</pre>
                </div>
            </div>

        </div>
    </div>
</div>

<footer class="text-center py-4 mt-5 text-muted">
    <div class="container">
        &copy; <?php echo date('Y'); ?> Quaid-e-Azam Group of Colleges | Attendance Fix Utility
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
