<?php
// File: includes/header.php - Comprehensive ERP Header
require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isLoggedIn()) {
    header("Location: " . BASE_URL . "modules/auth/login.php");
    exit();
}

enforcePasswordChange();

$page_title = $page_title ?? "Admin Panel";
$currentRole = getUserRole();
$roleModules = [
    'admin' => [
        ['modules/admissions/index.php', 'fas fa-user-plus', 'Admissions'],
        ['modules/fee_management/index.php', 'fas fa-money-bill-wave', 'Fee Management'],
        ['modules/fee_management/total_transactions.php', 'fas fa-arrow-right-arrow-left', 'Total Transactions'],
        ['school-growth.php', 'fas fa-chart-line', 'School Growth'],
        ['vouchers.php', 'fas fa-file-invoice-dollar', 'Vouchers'],
        ['modules/old_data_archive/old-data.php', 'fas fa-box-archive', 'Old Data Archive'],
        ['settings-class-billing-rules.php', 'fas fa-sliders', 'Class Billing Rules'],
        ['modules/accounts/index.php', 'fas fa-calculator', 'Accounts'],
        ['modules/ledger/index.php', 'fas fa-book', 'Ledger'],
        ['modules/lms/index.php', 'fas fa-book-reader', 'LMS'],
        ['modules/attendance/index.php', 'fas fa-calendar-check', 'Attendance'],
        ['modules/examination/index.php', 'fas fa-file-signature', 'Examination'],
        ['result-cards.php', 'fas fa-certificate', 'Result Cards'],
        ['modules/hr/index.php', 'fas fa-user-tie', 'HR Management'],
        ['modules/library/index.php', 'fas fa-book', 'Library'],
        ['modules/transport/index.php', 'fas fa-bus', 'Transport'],
        ['modules/communication/index.php', 'fas fa-comments', 'Communication'],
        ['modules/complaints/index.php', 'fas fa-exclamation-circle', 'Complaints'],
        ['modules/diary_homework/index.php', 'fas fa-book-open', 'Diary & Homework'],
        ['modules/downloads/index.php', 'fas fa-download', 'Downloads'],
        ['modules/pos/index.php', 'fas fa-store', 'POS'],
        ['modules/ptm/index.php', 'fas fa-users', 'PTM'],
        ['modules/student_profile/index.php', 'fas fa-id-card', 'Student Profile'],
        ['modules/syllabus/index.php', 'fas fa-scroll', 'Syllabus'],
        ['modules/tasks/index.php', 'fas fa-tasks', 'Tasks'],
        ['modules/timetable/index.php', 'fas fa-clock', 'Timetable'],
        ['modules/expenses/index.php', 'fas fa-wallet', 'Expense Management'],
        ['modules/front_desk/index.php', 'fas fa-desktop', 'Front Desk'],
        ['modules/backup/index.php', 'fas fa-database', 'Backup / Import'],
        ['modules/erase_data/index.php', 'fas fa-trash-alt', 'Erase All Data'],
    ],
    'owner' => [
        ['modules/admissions/index.php', 'fas fa-user-plus', 'Admissions'],
        ['modules/fee_management/index.php', 'fas fa-money-bill-wave', 'Fee Management'],
        ['modules/fee_management/total_transactions.php', 'fas fa-arrow-right-arrow-left', 'Total Transactions'],
        ['school-growth.php', 'fas fa-chart-line', 'School Growth'],
        ['vouchers.php', 'fas fa-file-invoice-dollar', 'Vouchers'],
        ['modules/old_data_archive/old-data.php', 'fas fa-box-archive', 'Old Data Archive'],
        ['settings-class-billing-rules.php', 'fas fa-sliders', 'Class Billing Rules'],
        ['modules/accounts/index.php', 'fas fa-calculator', 'Accounts'],
        ['modules/ledger/index.php', 'fas fa-book', 'Ledger'],
        ['modules/lms/index.php', 'fas fa-book-reader', 'LMS'],
        ['modules/attendance/index.php', 'fas fa-calendar-check', 'Attendance'],
        ['modules/examination/index.php', 'fas fa-file-signature', 'Examination'],
        ['result-cards.php', 'fas fa-certificate', 'Result Cards'],
        ['modules/hr/index.php', 'fas fa-user-tie', 'HR Management'],
        ['modules/library/index.php', 'fas fa-book', 'Library'],
        ['modules/transport/index.php', 'fas fa-bus', 'Transport'],
        ['modules/communication/index.php', 'fas fa-comments', 'Communication'],
        ['modules/complaints/index.php', 'fas fa-exclamation-circle', 'Complaints'],
        ['modules/diary_homework/index.php', 'fas fa-book-open', 'Diary & Homework'],
        ['modules/downloads/index.php', 'fas fa-download', 'Downloads'],
        ['modules/pos/index.php', 'fas fa-store', 'POS'],
        ['modules/ptm/index.php', 'fas fa-users', 'PTM'],
        ['modules/student_profile/index.php', 'fas fa-id-card', 'Student Profile'],
        ['modules/syllabus/index.php', 'fas fa-scroll', 'Syllabus'],
        ['modules/tasks/index.php', 'fas fa-tasks', 'Tasks'],
        ['modules/timetable/index.php', 'fas fa-clock', 'Timetable'],
        ['modules/expenses/index.php', 'fas fa-chart-line', 'Financial Overview'],
        ['modules/front_desk/index.php', 'fas fa-desktop', 'Front Desk'],
        ['modules/backup/index.php', 'fas fa-database', 'Backup / Import'],
    ],
    'teacher' => [
        ['modules/attendance/index.php', 'fas fa-calendar-check', 'Mark Attendance'],
        ['modules/examination/index.php', 'fas fa-file-signature', 'Examination'],
        ['result-cards.php', 'fas fa-certificate', 'Result Cards'],
        ['modules/diary_homework/index.php', 'fas fa-book-open', 'Diary & Homework'],
        ['modules/lms/index.php', 'fas fa-book-reader', 'LMS Uploads'],
        ['modules/student_profile/index.php', 'fas fa-id-card', 'My Students'],
        ['modules/timetable/index.php', 'fas fa-clock', 'My Classes'],
        ['modules/expenses/index.php', 'fas fa-receipt', 'Expense Requests'],
    ],
    'student' => [
        ['modules/student_profile/index.php', 'fas fa-id-card', 'My Profile'],
        ['modules/attendance/index.php', 'fas fa-calendar-check', 'My Attendance'],
        ['modules/diary_homework/index.php', 'fas fa-book-open', 'My Homework'],
        ['modules/lms/index.php', 'fas fa-book-reader', 'My LMS Material'],
        ['modules/fee_management/index.php', 'fas fa-money-bill-wave', 'My Fee Status'],
        ['modules/complaints/index.php', 'fas fa-exclamation-circle', 'Complaints'],
    ],
];
$visibleModules = $roleModules[$currentRole] ?? [];
$currentScriptPath = str_replace('\\', '/', (string)($_SERVER['PHP_SELF'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> | QAC Portal</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=2">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/design-system.css?v=2">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/sidebar.css?v=2">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/topbar.css?v=2">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/layout.css?v=2">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/components.css?v=2">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/responsive.css?v=2">
    
    <style>
        /* Layout Fixes for No Overlap */
        #content {
            margin-left: 260px;
            width: calc(100% - 260px);
            transition: all 0.3s;
            min-height: 100vh;
            background: #f8fafc;
        }
        #sidebar.active {
            margin-left: 0;
        }
        #sidebar.active + #content {
            margin-left: var(--sidebar-collapsed);
            width: calc(100% - var(--sidebar-collapsed));
        }
        
        @media (max-width: 992px) {
            #sidebar { margin-left: 0; }
            #sidebar.active { margin-left: 0; }
            #content { margin-left: 0; width: 100%; }
            #content.active { margin-left: 0; }
        }

        .nav-item-label {
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .sidebar-scroll {
            height: calc(100vh - 70px);
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: rgba(255,255,255,0.1) transparent;
        }

        .modal {
            z-index: 2050 !important;
        }

        .modal-backdrop {
            z-index: 2040 !important;
        }

        .modal-dialog {
            margin-left: auto !important;
            margin-right: auto !important;
        }
    </style>
</head>
<body>

    <div class="wrapper">
        <div class="sidebar-backdrop"></div>
        <!-- ─── SIDEBAR ────────────────────────────────────────── -->
        <nav id="sidebar">
            <div class="sidebar-header d-flex align-items-center">
                <img src="<?= BASE_URL ?>assets/images/qgc-logo.png" alt="Logo" style="max-height: 50px; width: auto;" class="me-2">
                <div class="logo-info">
                    <div class="logo-text" style="font-size: 0.9rem; line-height: 1.2;">Quaid-e-Azam<br>Colleges</div>
                    <div style="font-size: 0.65rem; color: var(--teal); text-transform: uppercase; letter-spacing: 1px;">
                        <?= htmlspecialchars($_SESSION['user_campus'] ?? 'Rajanpur') ?>
                    </div>
                </div>
            </div>

            <div class="sidebar-scroll">
                <ul class="list-unstyled components p-2">
                    <li class="<?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>">
                        <a href="<?= BASE_URL ?>dashboard.php"><i class="fas fa-home"></i> <span>Dashboard</span></a>
                    </li>
                    <?php foreach ($visibleModules as [$path, $icon, $label]): ?>
                        <?php
                        $modulePath = '/' . ltrim(str_replace('\\', '/', $path), '/');
                        $moduleActive = substr($currentScriptPath, -strlen($modulePath)) === $modulePath;
                        ?>
                        <li class="<?= $moduleActive ? 'active' : '' ?>"><a href="<?= BASE_URL . $path ?>"><i class="<?= htmlspecialchars($icon) ?>"></i> <span><?= htmlspecialchars($label) ?></span></a></li>
                    <?php endforeach; ?>
                    <?php
                    // Add WhatsApp Center under Communication when Communication module is visible
                    $hasComm = false;
                    foreach ($visibleModules as $vm) {
                        if (strpos($vm[0], 'modules/communication') !== false) { $hasComm = true; break; }
                    }
                    if ($hasComm) {
                        $waActive = strpos($currentScriptPath, '/modules/communication/whatsapp-center.php') !== false ? 'active' : '';
                        ?>
                        <li class="<?= $waActive ?>"><a href="<?= BASE_URL ?>modules/communication/whatsapp-center.php"><i class="fas fa-comment-dots"></i> <span>WhatsApp Center</span></a></li>
                    <?php } ?>

                    <?php if ($currentRole === 'student'): ?>
                        <?php
                        $clrStatus = null;
                        try {
                            $clrDb = (new Database())->getConnection();
                            if (tableExists($clrDb, 'degree_clearance')) {
                                $clrStmt = $clrDb->prepare("
                                    SELECT status
                                    FROM degree_clearance
                                    WHERE student_id = (SELECT id FROM students WHERE user_id = :uid LIMIT 1)
                                    ORDER BY applied_at DESC
                                    LIMIT 1
                                ");
                                $clrStmt->execute([':uid' => getUserId()]);
                                $clrStatus = $clrStmt->fetchColumn();
                            }
                        } catch (Exception $e) {
                            $clrStatus = null;
                        }
                        $clrActive = strpos($currentScriptPath, '/modules/student/clearance.php') !== false ? 'active' : '';
                        ?>
                        <li class="<?= $clrActive ?>">
                            <a href="<?= BASE_URL ?>modules/student/clearance.php">
                                <i class="fas fa-graduation-cap" style="color:#4ec2b5;"></i>
                                <span>Degree Clearance</span>
                                <?php if ($clrStatus === 'approved'): ?>
                                    <span class="badge bg-success ms-auto rounded-pill" style="font-size:.6rem;">✓</span>
                                <?php elseif ($clrStatus === 'under_review'): ?>
                                    <span class="badge bg-warning ms-auto rounded-pill text-dark" style="font-size:.6rem;">⏳</span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if (in_array($currentRole, ['admin', 'owner'], true)): ?>
                        <?php
                        $pendingClearance = 0;
                        try {
                            $clrDb = (new Database())->getConnection();
                            if (tableExists($clrDb, 'degree_clearance')) {
                                $pendingClearance = (int)$clrDb->query("SELECT COUNT(*) FROM degree_clearance WHERE status = 'under_review'")->fetchColumn();
                            }
                        } catch (Exception $e) {
                            $pendingClearance = 0;
                        }
                        $adminClrActive = strpos($currentScriptPath, '/modules/admin/clearance_requests.php') !== false ? 'active' : '';
                        ?>
                        <li class="<?= $adminClrActive ?>">
                            <a href="<?= BASE_URL ?>modules/admin/clearance_requests.php">
                                <i class="fas fa-graduation-cap" style="color:#4ec2b5;"></i>
                                <span>Degree Clearance</span>
                                <?php if ($pendingClearance > 0): ?>
                                    <span class="badge rounded-pill ms-auto" style="background:#f0b429;color:#0f2d48;font-size:.6rem;"><?= (int)$pendingClearance ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <li class="mt-4 pt-3 border-top border-secondary">
                        <a href="<?= BASE_URL ?>logout.php" class="text-danger"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- ─── MAIN CONTENT ────────────────────────────────────── -->
        <div id="content">
            <!-- Top Navbar -->
            <nav class="navbar navbar-expand-lg sticky-top topbar">
                <div class="container-fluid">
                    <button type="button" id="sidebarCollapse" class="btn btn-light shadow-sm">
                        <i data-lucide="panel-left"></i>
                    </button>
                    
                    <div class="topbar-breadcrumb d-none d-md-flex align-items-center gap-2 ms-3">
                        <a href="<?= BASE_URL ?>dashboard.php" class="text-small fw-bold">Dashboard</a>
                        <span class="text-muted">/</span>
                        <span class="fw-bold text-dark"><?= htmlspecialchars($page_title) ?></span>
                    </div>

                    <div class="d-flex align-items-center gap-3 ms-auto topbar-actions">
                        <button type="button" class="topbar-search" data-open-search>
                            <i data-lucide="search"></i>
                            <span>Search...</span>
                            <kbd>Ctrl K</kbd>
                        </button>
                        <button type="button" class="topbar-icon-btn position-relative" data-tooltip="Notifications">
                            <i data-lucide="bell"></i>
                            <span class="notif-dot"></span>
                        </button>
                        <button type="button" class="topbar-icon-btn" data-tooltip="User Guide">
                            <i data-lucide="circle-help"></i>
                        </button>
                        
                        <div class="dropdown">
                            <button class="btn border-0 topbar-admin" data-bs-toggle="dropdown">
                                <span class="admin-avatar"><?= htmlspecialchars(strtoupper(substr($_SESSION['username'] ?? 'AD', 0, 2))) ?></span>
                                <span class="admin-info text-start">
                                    <span class="admin-name"><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></span>
                                    <span class="admin-date"><?= date('D, d M Y') ?></span>
                                </span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0">
                                <li><a class="dropdown-item" href="#"><i class="fas fa-user me-2"></i> Profile</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>logout.php"><i class="fas fa-power-off me-2"></i> Logout</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </nav>

            <div class="search-modal-overlay" id="searchOverlay">
                <div class="search-modal glass">
                    <div class="search-modal-input-wrap">
                        <i data-lucide="search"></i>
                        <input type="text" id="globalSearchInput" placeholder="Search students, fees, classes...">
                        <kbd>ESC</kbd>
                    </div>
                    <div class="search-modal-results" id="searchResults">
                        <a href="<?= BASE_URL ?>modules/student_profile/index.php"><i data-lucide="users"></i><span>Students</span></a>
                        <a href="<?= BASE_URL ?>modules/fee_management/index.php"><i data-lucide="credit-card"></i><span>Fee Management</span></a>
                        <a href="<?= BASE_URL ?>modules/attendance/index.php"><i data-lucide="calendar-check"></i><span>Attendance</span></a>
                        <a href="<?= BASE_URL ?>result-cards.php"><i data-lucide="award"></i><span>Result Cards</span></a>
                    </div>
                    <div class="search-modal-footer">
                        <span><kbd>ESC</kbd> close</span>
                        <span><kbd>Enter</kbd> open</span>
                    </div>
                </div>
            </div>

            <div class="p-4 p-md-5">
