<?php
// File: school-growth.php - School Growth Dashboard
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/school_growth_functions.php';

if (!isLoggedIn()) {
    redirect('index.php');
}
requireRole(['admin', 'owner']);

$database = new Database();
$conn = $database->getConnection();

$page_title = 'School Growth';

$growthScore = calculateGrowthScore($conn);
$growthInsight = getGrowthInsightSentence($conn);
$admissionsThisMonth = getAdmissionsThisMonth($conn);
$admissionsMomentum = getAdmissionsMomentumRate($conn);
$feeCollectionRate = getFeeCollectionRate($conn);
$attendanceBreadth = getAttendanceBreadth($conn);
$netMonthly = getNetMonthlyPosition($conn);
$activeStudents = getActiveStudentCount($conn);
$principalSummary = getPrincipalSummary($conn);
$forecastRevenue = getRevenueForecast($conn);
$forecastExpense = getExpenseForecast($conn);
$netForecast = round($forecastRevenue - $forecastExpense, 2);
$retentionRate = getRetentionRate($conn);
$feeMomentum = getFeeMomentumRate($conn);
$admissionsVsWithdrawals = getAdmissionsVsWithdrawals($conn);
$flowData = getFeeIncomeExpenseFlow($conn);
$academicTrend = getAcademicAttendanceTrend($conn);
$growthSnapshot = getGrowthSnapshot($conn);
$riskAlerts = getRiskAlerts($conn);
$growthSuggestions = getGrowthSuggestions($conn);
$wins = getWinsAndPositiveSignals($conn);
$actions = getRecommendedActions($conn);
$teacherPerformance = getTeacherPerformanceSnapshot($conn);
$operationalHealth = getOperationalHealth($conn);
$classInsights = getClasswiseGrowthInsights($conn);
$smartActivity = getSmartActivityBlocks($conn);
$admissionReadiness = getAdmissionReadiness($conn);
$feeRecoveryBoard = getFeeRecoveryBoard($conn);
$studentRiskWatchlist = getStudentRiskWatchlist($conn);

include __DIR__ . '/includes/header.php';
?>

<style>
    .growth-score-card {
        min-height: 260px;
        background: linear-gradient(135deg, #0f2d48 0%, #1f4f72 100%);
        color: #fff;
        border-radius: 20px;
    }
    .growth-score-card .btn-outline-light {
        border-color: rgba(255,255,255,0.35);
        color: #fff;
    }
    .metric-card {
        border-radius: 18px;
        min-height: 170px;
    }
    .metric-icon {
        width: 48px;
        height: 48px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
    }
    .risk-bullet {
        display: flex;
        gap: 0.75rem;
        align-items: flex-start;
    }
    .risk-bullet i {
        margin-top: 0.3rem;
    }
    .sparkline-box {
        min-height: 128px;
        border-radius: 18px;
    }
    .summary-pill {
        background: #f4f7fb;
        border-radius: 16px;
        padding: 0.75rem 1rem;
        display: inline-block;
        margin-bottom: 0.75rem;
    }
    .tab-link-custom {
        border-radius: 999px;
        margin-right: 0.35rem;
        padding: 0.85rem 1.25rem;
        border: 1px solid #dee2e6;
        color: #0f2d48;
        background: #fff;
    }
    .tab-link-custom.active {
        background: #0f2d48;
        color: #fff;
        border-color: #0f2d48;
    }
    .insight-badge {
        min-width: 110px;
    }
</style>

<div class="container-fluid px-4 py-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="fw-bold mb-1">School Growth</h2>
            <p class="text-muted mb-0">Analyze school health, trends, risks, and growth opportunities</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= BASE_URL ?>modules/expenses/index.php" class="tab-link-custom">Expenses</a>
            <a href="<?= BASE_URL ?>modules/ledger/index.php" class="tab-link-custom">Debit/Credits</a>
            <a href="<?= BASE_URL ?>modules/accounts/index.php" class="tab-link-custom">Income</a>
            <a href="<?= BASE_URL ?>modules/accounts/index.php" class="tab-link-custom">Balance Sheet</a>
            <a href="<?= BASE_URL ?>modules/expenses/index.php" class="tab-link-custom">Profit / Loss</a>
            <a href="<?= BASE_URL ?>modules/expenses/index.php" class="tab-link-custom">Stationary</a>
            <a href="<?= BASE_URL ?>modules/pos/index.php" class="tab-link-custom">Inventory</a>
            <a href="<?= BASE_URL ?>modules/expenses/index.php" class="tab-link-custom">Reports</a>
            <a href="<?= BASE_URL ?>school-growth.php" class="tab-link-custom active">Growth</a>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-5">
            <div class="card growth-score-card shadow-sm p-4">
                <div class="d-flex justify-content-between align-items-start mb-4">
                    <div>
                        <small class="text-uppercase fw-semibold opacity-75">Growth Score</small>
                        <h1 class="display-4 fw-bold mb-0"><?= htmlspecialchars($growthScore) ?>/100</h1>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-white text-dark rounded-pill py-2 px-3">Momentum</span>
                    </div>
                </div>
                <p class="lead mb-4"><?= htmlspecialchars($growthInsight) ?></p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="#" id="sgPrintBtn" class="btn btn-light btn-sm px-4">Print / Save PDF</a>
                    <a href="<?= BASE_URL ?>modules/expenses/index.php" class="btn btn-outline-light btn-sm px-4">Open Financial Reports</a>
                    <a href="<?= BASE_URL ?>modules/student/index.php" class="btn btn-outline-light btn-sm px-4">Open Students</a>
                </div>
            </div>
        </div>
        <div class="col-xl-7">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="card metric-card shadow-sm p-3 border-0" style="background:#e9f9ed;">
                        <div class="d-flex align-items-center mb-3">
                            <span class="metric-icon bg-success text-white me-3"><i class="fas fa-user-graduate"></i></span>
                            <div>
                                <p class="text-muted small mb-1">Active Students</p>
                                <h4 class="fw-bold mb-0"><?= number_format($activeStudents) ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card metric-card shadow-sm p-3 border-0" style="background:#e8f4ff;">
                        <div class="d-flex align-items-center mb-3">
                            <span class="metric-icon bg-primary text-white me-3"><i class="fas fa-user-plus"></i></span>
                            <div>
                                <p class="text-muted small mb-1">Admissions This Month</p>
                                <h4 class="fw-bold mb-0"><?= number_format($admissionsThisMonth) ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card metric-card shadow-sm p-3 border-0" style="background:#fff4e6;">
                        <div class="d-flex align-items-center mb-3">
                            <span class="metric-icon bg-warning text-white me-3"><i class="fas fa-percent"></i></span>
                            <div>
                                <p class="text-muted small mb-1">Fee Collection Rate</p>
                                <h4 class="fw-bold mb-0"><?= sgf_percent($feeCollectionRate) ?>%</h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card metric-card shadow-sm p-3 border-0" style="background:#f3e8ff;">
                        <div class="d-flex align-items-center mb-3">
                            <span class="metric-icon bg-info text-white me-3"><i class="fas fa-chart-line"></i></span>
                            <div>
                                <p class="text-muted small mb-1">Attendance Breadth</p>
                                <h4 class="fw-bold mb-0"><?= sgf_percent($attendanceBreadth) ?>%</h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="card metric-card shadow-sm p-3 border-0" style="background:#ffe8e8;">
                        <div class="d-flex align-items-center mb-3">
                            <span class="metric-icon bg-danger text-white me-3"><i class="fas fa-wallet"></i></span>
                            <div>
                                <p class="text-muted small mb-1">Net Monthly Position</p>
                                <h4 class="fw-bold mb-0 <?= $netMonthly < 0 ? 'text-danger' : 'text-success' ?>">Rs. <?= sgf_price($netMonthly) ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-7">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h5 class="fw-bold mb-3">Principal Summary</h5>
                    <?php foreach ($principalSummary as $line): ?>
                        <p class="mb-2"><?= htmlspecialchars($line) ?></p>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="row g-3">
                <div class="col-12">
                    <div class="card shadow-sm border-0 p-3">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0">Forecast</h6>
                            <span class="badge bg-light text-dark">Next Month</span>
                        </div>
                        <div class="row g-3">
                            <div class="col-4 text-center">
                                <p class="text-muted small mb-1">Revenue</p>
                                <h5 class="fw-bold">Rs. <?= sgf_price($forecastRevenue) ?></h5>
                            </div>
                            <div class="col-4 text-center">
                                <p class="text-muted small mb-1">Expense</p>
                                <h5 class="fw-bold">Rs. <?= sgf_price($forecastExpense) ?></h5>
                            </div>
                            <div class="col-4 text-center">
                                <p class="text-muted small mb-1">Net</p>
                                <h5 class="fw-bold <?= $netForecast < 0 ? 'text-danger' : 'text-success' ?>">Rs. <?= sgf_price($netForecast) ?></h5>
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <?php $retentionBadge = $retentionRate >= 90 ? 'bg-success text-white' : ($retentionRate >= 80 ? 'bg-warning text-dark' : 'bg-danger text-white'); ?>
                            <span class="badge <?= $retentionBadge ?> insight-badge">Retention <?= sgf_percent($retentionRate) ?>%</span>
                            <span class="badge <?= $feeMomentum >= 0 ? 'bg-success' : 'bg-danger' ?> text-white insight-badge">Fee Momentum <?= $feeMomentum >= 0 ? '+' : '' ?><?= sgf_percent($feeMomentum) ?>%</span>
                            <span class="badge bg-secondary text-white insight-badge">Status Stable</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-6">
            <div class="card shadow-sm border-0 p-3">
                <div class="card-body">
                    <h5 class="fw-bold mb-3">Admissions vs Withdrawals</h5>
                    <canvas id="admissionsWithdrawalsChart" height="260"></canvas>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card shadow-sm border-0 p-3">
                <div class="card-body">
                    <h5 class="fw-bold mb-3">Fee, Income, Expense Flow</h5>
                    <canvas id="flowChart" height="260"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card sparkline-box shadow-sm p-3">
                <div class="d-flex justify-content-between mb-3">
                    <div>
                        <small class="text-muted">Student Attendance</small>
                        <h5 class="fw-bold mb-0"><?= sgf_percent($academicTrend['student_attendance']) ?>%</h5>
                    </div>
                    <i class="fas fa-user-check fs-3 text-success"></i>
                </div>
                <div class="progress" style="height: 10px; border-radius: 10px;">
                    <div class="progress-bar bg-success" role="progressbar" style="width: <?= sgf_percent($academicTrend['student_attendance']) ?>%;"></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card sparkline-box shadow-sm p-3">
                <div class="d-flex justify-content-between mb-3">
                    <div>
                        <small class="text-muted">Teacher Attendance</small>
                        <h5 class="fw-bold mb-0"><?= sgf_percent($academicTrend['teacher_attendance']) ?>%</h5>
                    </div>
                    <i class="fas fa-chalkboard-teacher fs-3 text-primary"></i>
                </div>
                <div class="progress" style="height: 10px; border-radius: 10px;">
                    <div class="progress-bar bg-primary" role="progressbar" style="width: <?= sgf_percent($academicTrend['teacher_attendance']) ?>%;"></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card sparkline-box shadow-sm p-3">
                <div class="d-flex justify-content-between mb-3">
                    <div>
                        <small class="text-muted">Exam Performance</small>
                        <h5 class="fw-bold mb-0"><?= sgf_percent($academicTrend['exam_performance']) ?>%</h5>
                    </div>
                    <i class="fas fa-file-alt fs-3 text-warning"></i>
                </div>
                <div class="progress" style="height: 10px; border-radius: 10px;">
                    <div class="progress-bar bg-warning" role="progressbar" style="width: <?= sgf_percent($academicTrend['exam_performance']) ?>%;"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-7">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h5 class="fw-bold mb-3">Risk Alerts</h5>
                    <?php foreach ($riskAlerts as $alert): ?>
                        <div class="alert alert-warning rounded-4 py-3" role="alert">
                            <div class="risk-bullet"><i class="fas fa-exclamation-triangle text-danger"></i><span><?= htmlspecialchars($alert) ?></span></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h5 class="fw-bold mb-3">Growth Snapshot</h5>
                    <table class="table table-borderless mb-0">
                        <tbody>
                            <tr><td>Teachers</td><td class="text-end"><?= number_format($growthSnapshot['teachers']) ?></td></tr>
                            <tr><td>Support Staff</td><td class="text-end"><?= number_format($growthSnapshot['support_staff']) ?></td></tr>
                            <tr><td>Total Expected Fee</td><td class="text-end">Rs. <?= sgf_price($growthSnapshot['expected_fee']) ?></td></tr>
                            <tr><td>Total Paid</td><td class="text-end">Rs. <?= sgf_price($growthSnapshot['paid']) ?></td></tr>
                            <tr><td>Parent Engagement</td><td class="text-end"><?= number_format($growthSnapshot['parent_engagement']) ?></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h5 class="fw-bold mb-3">Growth Suggestions</h5>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($growthSuggestions as $suggestion): ?>
                            <li class="mb-2"><i class="fas fa-lightbulb text-warning me-2"></i><?= htmlspecialchars($suggestion) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h5 class="fw-bold mb-3">Wins & Positive Signals</h5>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($wins as $win): ?>
                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i><?= htmlspecialchars($win) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h5 class="fw-bold mb-3">Recommended Actions</h5>
                    <?php foreach ($actions as $action): ?>
                        <div class="d-flex align-items-center justify-content-between mb-3 p-3 bg-light rounded-4">
                            <span><?= htmlspecialchars($action) ?></span>
                            <button class="btn btn-sm btn-outline-primary"><i class="fas fa-arrow-right"></i></button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-7">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h5 class="fw-bold mb-3">Teacher Performance Snapshot</h5>
                    <?php if ($teacherPerformance['top'] || $teacherPerformance['needs_review']): ?>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <div class="p-3 bg-success bg-opacity-10 rounded-4">
                                    <strong>Top Teacher</strong>
                                    <p class="mb-1"><?= htmlspecialchars($teacherPerformance['top']['name'] ?? 'No data') ?></p>
                                    <small><?= htmlspecialchars($teacherPerformance['top']['attendance'] ?? '0.0') ?>% attendance</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 bg-danger bg-opacity-10 rounded-4">
                                    <strong>Needs Review</strong>
                                    <p class="mb-1"><?= htmlspecialchars($teacherPerformance['needs_review']['name'] ?? 'No data') ?></p>
                                    <small><?= htmlspecialchars($teacherPerformance['needs_review']['attendance'] ?? '0.0') ?>% attendance</small>
                                </div>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light"><tr><th>Teacher</th><th>Attendance</th><th>Assigned</th></tr></thead>
                                <tbody>
                                    <?php if (empty($teacherPerformance['open_teachers'])): ?>
                                        <tr><td colspan="3" class="text-center text-muted">No open teacher records.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($teacherPerformance['open_teachers'] as $row): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($row['name']) ?></td>
                                                <td><?= htmlspecialchars($row['attendance']) ?>%</td>
                                                <td><?= htmlspecialchars($row['assigned']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0">Teacher attendance data is not available yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h5 class="fw-bold mb-3">Operational Health</h5>
                    <div class="row g-3">
                        <div class="col-6"><div class="p-3 bg-light rounded-4 text-center"><small class="text-muted">Classes Missing Sections</small><h5 class="mb-0"><?= number_format($operationalHealth['classes_missing_sections']) ?></h5></div></div>
                        <div class="col-6"><div class="p-3 bg-light rounded-4 text-center"><small class="text-muted">Classes Missing Subjects</small><h5 class="mb-0"><?= number_format($operationalHealth['classes_missing_subjects']) ?></h5></div></div>
                        <div class="col-6"><div class="p-3 bg-light rounded-4 text-center"><small class="text-muted">Incomplete Student Profiles</small><h5 class="mb-0"><?= number_format($operationalHealth['incomplete_student_profiles']) ?></h5></div></div>
                        <div class="col-6"><div class="p-3 bg-light rounded-4 text-center"><small class="text-muted">Unassigned Teachers</small><h5 class="mb-0"><?= number_format($operationalHealth['unassigned_teachers']) ?></h5></div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <h5 class="fw-bold mb-3">Class-wise Growth Insights</h5>
            <?php if (empty($classInsights)): ?>
                <div class="text-center text-muted py-4">No class-level data available yet.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light"><tr><th>Class</th><th>Students</th><th>Attendance</th><th>Result Avg</th><th>Pending Dues</th><th>Insight</th></tr></thead>
                        <tbody>
                            <?php foreach ($classInsights as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['class']) ?></td>
                                    <td><?= number_format($row['students']) ?></td>
                                    <td><?= htmlspecialchars($row['attendance']) ?>%</td>
                                    <td><?= htmlspecialchars($row['result']) ?>%</td>
                                    <td>Rs. <?= sgf_price($row['pending']) ?></td>
                                    <td><?= htmlspecialchars($row['insight']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h5 class="fw-bold mb-3">Marketing Growth Ideas</h5>
                    <p class="mb-0">Refresh admission messaging with parent testimonials and result highlights.</p>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h5 class="fw-bold mb-3">Smart Activity Blocks</h5>
                    <div class="row g-3">
                        <div class="col-4"><div class="p-3 bg-light rounded-4 text-center"><small class="text-muted d-block mb-2">Reports</small><h5 class="mb-0"><?= number_format($smartActivity['generated_reports']) ?></h5></div></div>
                        <div class="col-4"><div class="p-3 bg-light rounded-4 text-center"><small class="text-muted d-block mb-2">Feature Requests</small><h5 class="mb-0"><?= number_format($smartActivity['feature_requests']) ?></h5></div></div>
                        <div class="col-4"><div class="p-3 bg-light rounded-4 text-center"><small class="text-muted d-block mb-2">Broadcasts</small><h5 class="mb-0"><?= number_format($smartActivity['parent_broadcasts']) ?></h5></div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-7">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h5 class="fw-bold mb-3">Admission Readiness</h5>
                    <div class="row g-3">
                        <div class="col-6"><div class="p-3 bg-light rounded-4"><small class="text-muted">Capacity</small><h5 class="mb-0"><?= number_format($admissionReadiness['student_capacity']) ?></h5></div></div>
                        <div class="col-6"><div class="p-3 bg-light rounded-4"><small class="text-muted">Current Students</small><h5 class="mb-0"><?= number_format($admissionReadiness['current_students']) ?></h5></div></div>
                        <div class="col-6"><div class="p-3 bg-light rounded-4"><small class="text-muted">Available Seats</small><h5 class="mb-0"><?= number_format($admissionReadiness['available_seats']) ?></h5></div></div>
                        <div class="col-6"><div class="p-3 bg-light rounded-4"><small class="text-muted">Classes / Sections</small><h5 class="mb-0"><?= htmlspecialchars($admissionReadiness['classes_sections']) ?></h5></div></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h5 class="fw-bold mb-3">Fee Recovery Board</h5>
                    <?php if (empty($feeRecoveryBoard)): ?>
                        <div class="text-center text-muted py-4">No pending dues right now.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle">
                                <thead class="table-light"><tr><th>Student</th><th>Class</th><th>Pending</th></tr></thead>
                                <tbody>
                                    <?php foreach ($feeRecoveryBoard as $row): ?>
                                        <tr>
                                            <td><?= htmlspecialchars(trim($row['first_name'] . ' ' . $row['last_name'])) ?></td>
                                            <td><?= htmlspecialchars($row['class']) ?></td>
                                            <td>Rs. <?= sgf_price($row['pending']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <h5 class="fw-bold mb-3">Student Risk Watchlist</h5>
            <?php if (empty($studentRiskWatchlist)): ?>
                <div class="text-center text-muted py-4">No rescue student risk signals detected.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light"><tr><th>Student</th><th>Class</th><th>Risk Reason</th></tr></thead>
                        <tbody>
                            <?php foreach ($studentRiskWatchlist as $item): ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['name']) ?></td>
                                    <td><?= htmlspecialchars($item['class']) ?></td>
                                    <td><?= htmlspecialchars($item['reason']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <h5 class="fw-bold mb-3">30-Day Growth Roadmap</h5>
            <div class="timeline">
                <div class="mb-3"><strong>Week 1:</strong> Restart current admission momentum into referral-based outreach.</div>
                <div class="mb-3"><strong>Week 2:</strong> Run fee-recovery drive for top pending classes and families.</div>
                <div class="mb-3"><strong>Week 3:</strong> Review all absent students and call parents for reconnection.</div>
                <div class="mb-0"><strong>Week 4:</strong> Collect result highlights and showcase academic wins for branding.</div>
            </div>
        </div>
    </div>
</div>

<script id="sgAdmissionsData" type="application/json"><?= json_encode($admissionsVsWithdrawals) ?></script>
<script id="sgFlowData" type="application/json"><?= json_encode($flowData) ?></script>
<script src="<?= BASE_URL ?>assets/js/school-growth.js"></script>

<?php include __DIR__ . '/includes/footer.php'; ?>
