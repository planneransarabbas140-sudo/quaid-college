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

$page_title = $page_title ?? "Admin Panel";
$currentRole = getUserRole();
$roleModules = [
    'admin' => [
        ['modules/admissions/index.php', 'fas fa-user-plus', 'Admissions'],
        ['modules/fee_management/index.php', 'fas fa-money-bill-wave', 'Fee Management'],
        ['modules/accounts/index.php', 'fas fa-calculator', 'Accounts'],
        ['modules/ledger/index.php', 'fas fa-book', 'Ledger'],
        ['modules/lms/index.php', 'fas fa-book-reader', 'LMS'],
        ['modules/attendance/index.php', 'fas fa-calendar-check', 'Attendance'],
        ['modules/examination/index.php', 'fas fa-file-signature', 'Examination'],
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
        ['modules/accounts/index.php', 'fas fa-calculator', 'Accounts'],
        ['modules/ledger/index.php', 'fas fa-book', 'Ledger'],
        ['modules/lms/index.php', 'fas fa-book-reader', 'LMS'],
        ['modules/attendance/index.php', 'fas fa-calendar-check', 'Attendance'],
        ['modules/examination/index.php', 'fas fa-file-signature', 'Examination'],
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
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@400;500;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    
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
            margin-left: -260px;
        }
        #sidebar.active + #content {
            margin-left: 0;
            width: 100%;
        }
        
        @media (max-width: 992px) {
            #sidebar { margin-left: -260px; }
            #sidebar.active { margin-left: 0; }
            #content { margin-left: 0; width: 100%; }
            #content.active { margin-left: 260px; }
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
                        <li><a href="<?= BASE_URL . $path ?>"><i class="<?= htmlspecialchars($icon) ?>"></i> <span><?= htmlspecialchars($label) ?></span></a></li>
                    <?php endforeach; ?>
                    
                    <li class="mt-4 pt-3 border-top border-secondary">
                        <a href="<?= BASE_URL ?>logout.php" class="text-danger"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- ─── MAIN CONTENT ────────────────────────────────────── -->
        <div id="content">
            <!-- Top Navbar -->
            <nav class="navbar navbar-expand-lg sticky-top">
                <div class="container-fluid">
                    <button type="button" id="sidebarCollapse" class="btn btn-light shadow-sm">
                        <i class="fas fa-bars"></i>
                    </button>
                    
                    <div class="mx-auto">
                        <h4 class="mb-0 fw-bold" style="font-family: var(--font-display); color: var(--teal);">QAC</h4>
                    </div>

                    <div class="d-flex align-items-center gap-3">
                        <div class="position-relative">
                            <i class="fas fa-bell fs-5 text-muted"></i>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.5rem;">3</span>
                        </div>
                        
                        <div class="dropdown">
                            <button class="btn border-0 d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                                <div class="fw-bold" style="font-size: 0.85rem; color: var(--navy);"><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></div>
                                <i class="fas fa-user-circle fs-4 text-primary"></i>
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

            <div class="p-4 p-md-5">
