<?php
// File: includes/school_growth_functions.php
require_once __DIR__ . '/../config/db.php';

function sgf_fetchScalar(PDO $conn, string $sql, array $params = []) {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $value = $stmt->fetchColumn();
    return $value === false ? 0 : $value;
}

function sgf_fetchRows(PDO $conn, string $sql, array $params = []) {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function sgf_getDateColumn(PDO $conn, string $table, array $fallbacks) {
    foreach ($fallbacks as $column) {
        if (columnExists($conn, $table, $column)) {
            return $column;
        }
    }
    return null;
}

function sgf_pickColumns(PDO $conn, string $table, array $columns) {
    foreach ($columns as $column) {
        if (columnExists($conn, $table, $column)) {
            return $column;
        }
    }
    return null;
}

function sgf_price($value) {
    return number_format((float)$value, 0, '.', ',');
}

function sgf_percent($value) {
    return number_format(max(0, min(100, (float)$value)), 1);
}

function sgf_activeStudentStatusCondition() {
    return "LOWER(COALESCE(status, 'active')) = 'active'";
}

function sgf_getAdmissionsSource(PDO $conn) {
    if (tableExists($conn, 'admission_applications')) {
        return 'admission_applications';
    }
    if (tableExists($conn, 'students')) {
        return 'students';
    }
    return null;
}

function getActiveStudentCount(PDO $conn) {
    if (!tableExists($conn, 'students')) {
        return 0;
    }
    return (int)sgf_fetchScalar($conn, "SELECT COUNT(*) FROM students WHERE " . sgf_activeStudentStatusCondition());
}

function getAdmissionsThisMonth(PDO $conn) {
    $source = sgf_getAdmissionsSource($conn);
    if (!$source) {
        return 0;
    }
    $dateColumn = sgf_getDateColumn($conn, $source, ['created_at', 'application_date', 'submitted_at', 'date']);
    if (!$dateColumn) {
        return 0;
    }
    return (int)sgf_fetchScalar($conn, "SELECT COUNT(*) FROM $source WHERE YEAR($dateColumn) = YEAR(CURDATE()) AND MONTH($dateColumn) = MONTH(CURDATE())");
}

function getAdmissionsLastMonth(PDO $conn) {
    $source = sgf_getAdmissionsSource($conn);
    if (!$source) {
        return 0;
    }
    $dateColumn = sgf_getDateColumn($conn, $source, ['created_at', 'application_date', 'submitted_at', 'date']);
    if (!$dateColumn) {
        return 0;
    }
    return (int)sgf_fetchScalar($conn, "SELECT COUNT(*) FROM $source WHERE YEAR($dateColumn) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND MONTH($dateColumn) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))");
}

function getAdmissionsMomentumRate(PDO $conn) {
    $current = getAdmissionsThisMonth($conn);
    $previous = getAdmissionsLastMonth($conn);
    if ($previous === 0) {
        return $current > 0 ? 100.0 : 0.0;
    }
    return min(100.0, ($current / max(1, $previous)) * 100);
}

function getFeeCollectionRate(PDO $conn) {
    if (!tableExists($conn, 'fee_collections')) {
        return 0.0;
    }
    $dateColumn = sgf_getDateColumn($conn, 'fee_collections', ['payment_date', 'created_at']);
    $paidColumn = sgf_pickColumns($conn, 'fee_collections', ['paid_amount', 'amount_paid', 'amount']);
    $expectedColumn = sgf_pickColumns($conn, 'fee_collections', ['amount']);
    if (!$dateColumn || !$paidColumn || !$expectedColumn) {
        return 0.0;
    }
    $collected = (float)sgf_fetchScalar($conn, "SELECT COALESCE(SUM($paidColumn), 0) FROM fee_collections WHERE YEAR($dateColumn) = YEAR(CURDATE()) AND MONTH($dateColumn) = MONTH(CURDATE()) AND COALESCE(status, 'Paid') = 'Paid'");
    $expected = (float)sgf_fetchScalar($conn, "SELECT COALESCE(SUM($expectedColumn), 0) FROM fee_collections WHERE YEAR($dateColumn) = YEAR(CURDATE()) AND MONTH($dateColumn) = MONTH(CURDATE())");
    if ($expected <= 0) {
        return $collected > 0 ? 100.0 : 0.0;
    }
    return min(100.0, ($collected / $expected) * 100);
}

function getAttendanceBreadth(PDO $conn) {
    if (!tableExists($conn, 'student_attendance')) {
        return 0.0;
    }
    $dateColumn = sgf_getDateColumn($conn, 'student_attendance', ['attendance_date', 'created_at']);
    if (!$dateColumn) {
        return 0.0;
    }
    $presentCount = (float)sgf_fetchScalar($conn, "SELECT COUNT(*) FROM student_attendance WHERE YEAR($dateColumn) = YEAR(CURDATE()) AND MONTH($dateColumn) = MONTH(CURDATE()) AND LOWER(TRIM(status)) IN ('present','p','1','attended')");
    $totalCount = (float)sgf_fetchScalar($conn, "SELECT COUNT(*) FROM student_attendance WHERE YEAR($dateColumn) = YEAR(CURDATE()) AND MONTH($dateColumn) = MONTH(CURDATE())");
    if ($totalCount <= 0) {
        return 0.0;
    }
    return min(100.0, ($presentCount / $totalCount) * 100);
}

function getNetMonthlyPosition(PDO $conn) {
    $incomeTotal = 0.0;
    $expenseTotal = 0.0;
    if (tableExists($conn, 'income')) {
        $incomeDate = sgf_getDateColumn($conn, 'income', ['created_at']);
        $incomeTotal = (float)sgf_fetchScalar($conn, "SELECT COALESCE(SUM(amount), 0) FROM income WHERE YEAR($incomeDate) = YEAR(CURDATE()) AND MONTH($incomeDate) = MONTH(CURDATE())");
    }
    if (tableExists($conn, 'expenses')) {
        $expenseDate = sgf_getDateColumn($conn, 'expenses', ['created_at']);
        $expenseTotal = (float)sgf_fetchScalar($conn, "SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE YEAR($expenseDate) = YEAR(CURDATE()) AND MONTH($expenseDate) = MONTH(CURDATE())");
    }
    return round($incomeTotal - $expenseTotal, 2);
}

function getPendingDues(PDO $conn) {
    if (!tableExists($conn, 'fee_collections')) {
        return 0.0;
    }
    $paidColumn = sgf_pickColumns($conn, 'fee_collections', ['paid_amount', 'amount_paid']);
    $amountColumn = sgf_pickColumns($conn, 'fee_collections', ['amount']);
    if (!$amountColumn || !$paidColumn) {
        return 0.0;
    }
    $pending = (float)sgf_fetchScalar($conn, "SELECT COALESCE(SUM(GREATEST($amountColumn - COALESCE($paidColumn, 0), 0)), 0) FROM fee_collections");
    return round($pending, 2);
}

function calculateGrowthScore($conn) {
    $score = 0;
    $score += min(20, getFeeCollectionRate($conn) / 100 * 20);
    $score += min(20, getAdmissionsMomentumRate($conn) / 100 * 20);
    $score += min(20, getAttendanceBreadth($conn) / 100 * 20);
    $examScore = 0;
    if (tableExists($conn, 'exam_marks')) {
        $totalObtained = (float)sgf_fetchScalar($conn, "SELECT COALESCE(SUM(obtained_marks), 0) FROM exam_marks");
        $totalMarks = (float)sgf_fetchScalar($conn, "SELECT COALESCE(SUM(total_marks), 0) FROM exam_marks");
        $examScore = $totalMarks > 0 ? min(20, ($totalObtained / $totalMarks) * 20) : 0;
    }
    $score += $examScore;
    $staffAttendanceScore = 0;
    if (tableExists($conn, 'staff_attendance')) {
        $staffDate = sgf_getDateColumn($conn, 'staff_attendance', ['attendance_date', 'created_at']);
        $staffAttendanceRate = 0.0;
        if ($staffDate) {
            $staffAttendanceRate = (float)sgf_fetchScalar(
                $conn,
                "SELECT COALESCE((SUM(CASE WHEN LOWER(TRIM(status)) IN ('present','p','1','attended') THEN 1 ELSE 0 END) / GREATEST(COUNT(*),1)) * 100, 0)
                 FROM staff_attendance
                 WHERE YEAR($staffDate) = YEAR(CURDATE()) AND MONTH($staffDate) = MONTH(CURDATE())"
            );
        }
        $staffAttendanceScore = min(20, ($staffAttendanceRate / 100) * 20);
    }
    $score += $staffAttendanceScore;
    return round($score);
}

function getGrowthInsightSentence(PDO $conn) {
    // AI-style (rule-based) one-liner for the score card.
    $growthScore = (int)calculateGrowthScore($conn);
    $feeRecovery = (float)getFeeCollectionRate($conn);
    $admissionsMomentum = (float)getAdmissionsMomentumRate($conn);
    $attendance = (float)getAttendanceBreadth($conn);
    $net = (float)getNetMonthlyPosition($conn);

    if ($growthScore >= 80) {
        return "Strong momentum detected. Protect attendance discipline and keep fee recovery follow-ups tight to sustain growth.";
    }

    if ($growthScore >= 60) {
        if ($feeRecovery < 70) {
            return "Healthy trends are visible, but fee recovery is pulling the score down. A targeted recovery sprint can lift next month’s position.";
        }
        if ($attendance < 85) {
            return "Good momentum overall, but attendance breadth needs attention. Track chronic absentees class-wise to protect results and retention.";
        }
        return "Stable momentum detected. Keep admissions outreach active and review monthly cash flow to stay on track.";
    }

    if ($net < 0) {
        return "Growth is under pressure mainly due to negative cash flow. Improve collection follow-ups and tighten discretionary expenses this month.";
    }

    if ($admissionsMomentum < 60) {
        return "Admissions momentum looks soft. A small referral/outreach drive can help lift intake and improve overall growth score.";
    }

    if ($feeRecovery < 60) {
        return "Fee recovery is weak right now. Prioritize reminders and focus on top pending families first to recover momentum.";
    }

    return "Growth is currently moderate. Focus on fee recovery, attendance, and consistent assessments to improve next month’s outlook.";
}

function getPrincipalSummary(PDO $conn) {
    $growthScore = calculateGrowthScore($conn);
    $momentum = sgf_percent(getAdmissionsMomentumRate($conn));
    $feeRecovery = sgf_percent(getFeeCollectionRate($conn));
    $pendingDues = sgf_price(getPendingDues($conn));
    $attendance = sgf_percent(getAttendanceBreadth($conn));
    $examTrend = 0;
    if (tableExists($conn, 'exam_marks')) {
        $totalObtained = (float)sgf_fetchScalar($conn, "SELECT COALESCE(SUM(obtained_marks), 0) FROM exam_marks");
        $totalMarks = (float)sgf_fetchScalar($conn, "SELECT COALESCE(SUM(total_marks), 0) FROM exam_marks");
        $examTrend = $totalMarks > 0 ? min(100, ($totalObtained / $totalMarks) * 100) : 0;
    }
    $projectAdmissions = (int)round((getAdmissionsThisMonth($conn) + getAdmissionsLastMonth($conn)) / 2 ?: 0);
    $avgMonthlyCollection = 0;
    if (tableExists($conn, 'fee_collections')) {
        $dateColumn = sgf_getDateColumn($conn, 'fee_collections', ['payment_date', 'created_at']);
        $paidColumn = sgf_pickColumns($conn, 'fee_collections', ['paid_amount', 'amount_paid', 'amount']);
        if ($dateColumn && $paidColumn) {
            $avgMonthlyCollection = (float)sgf_fetchScalar($conn, "SELECT COALESCE(AVG(month_sum), 0) FROM (SELECT SUM($paidColumn) AS month_sum FROM fee_collections WHERE YEAR($dateColumn) >= YEAR(DATE_SUB(CURDATE(), INTERVAL 3 MONTH)) AND MONTH($dateColumn) >= MONTH(DATE_SUB(CURDATE(), INTERVAL 3 MONTH)) GROUP BY YEAR($dateColumn), MONTH($dateColumn)) AS monthly_totals");
        }
    }

    return [
        "Growth score is {$growthScore}/100 with admissions momentum at {$momentum}%.",
        "Fee recovery stands at {$feeRecovery}% and pending dues are Rs. {$pendingDues}.",
        "Current student attendance is {$attendance}% while exam trend is " . sgf_percent($examTrend) . "%.",
        "Projected next month admissions: {$projectAdmissions}, projected fee recovery Rs. " . sgf_price($avgMonthlyCollection) . "."
    ];
}

function getRevenueForecast(PDO $conn) {
    if (!tableExists($conn, 'fee_collections')) {
        return 0.0;
    }
    $dateColumn = sgf_getDateColumn($conn, 'fee_collections', ['payment_date', 'created_at']);
    $paidColumn = sgf_pickColumns($conn, 'fee_collections', ['paid_amount', 'amount_paid', 'amount']);
    if (!$dateColumn || !$paidColumn) {
        return 0.0;
    }
    return round((float)sgf_fetchScalar($conn, "SELECT COALESCE(AVG(month_sum), 0) FROM (SELECT SUM($paidColumn) AS month_sum FROM fee_collections WHERE YEAR($dateColumn) >= YEAR(DATE_SUB(CURDATE(), INTERVAL 3 MONTH)) GROUP BY YEAR($dateColumn), MONTH($dateColumn)) as months"), 2);
}

function getExpenseForecast(PDO $conn) {
    if (!tableExists($conn, 'expenses')) {
        return 0.0;
    }
    $dateColumn = sgf_getDateColumn($conn, 'expenses', ['created_at']);
    if (!$dateColumn) {
        return 0.0;
    }
    return round((float)sgf_fetchScalar($conn, "SELECT COALESCE(AVG(month_sum), 0) FROM (SELECT SUM(amount) AS month_sum FROM expenses WHERE YEAR($dateColumn) >= YEAR(DATE_SUB(CURDATE(), INTERVAL 3 MONTH)) GROUP BY YEAR($dateColumn), MONTH($dateColumn)) as months"), 2);
}

function getRetentionRate(PDO $conn) {
    if (!tableExists($conn, 'students')) {
        return 0.0;
    }

    // Retention proxy: active students up to end of current month vs end of last month.
    // (Historical withdrawal dates may not be tracked consistently in all schemas.)
    $activeCondition = sgf_activeStudentStatusCondition();
    $dateColumn = sgf_getDateColumn($conn, 'students', ['created_at', 'updated_at']);
    if (!$dateColumn) {
        return 0.0;
    }
    $current = (float)sgf_fetchScalar($conn, "SELECT COUNT(*) FROM students WHERE $activeCondition AND DATE($dateColumn) <= LAST_DAY(CURDATE())");
    $previous = (float)sgf_fetchScalar($conn, "SELECT COUNT(*) FROM students WHERE $activeCondition AND DATE($dateColumn) <= LAST_DAY(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))");
    if ($previous <= 0) {
        return $current > 0 ? 100.0 : 0.0;
    }
    return min(100.0, ($current / $previous) * 100);
}

function getFeeMomentumRate(PDO $conn) {
    $current = getFeeCollectionRate($conn);
    if (!tableExists($conn, 'fee_collections')) {
        return 0.0;
    }
    $dateColumn = sgf_getDateColumn($conn, 'fee_collections', ['payment_date', 'created_at']);
    $paidColumn = sgf_pickColumns($conn, 'fee_collections', ['paid_amount', 'amount_paid', 'amount']);
    if (!$dateColumn || !$paidColumn) {
        return 0.0;
    }
    $currentCollection = (float)sgf_fetchScalar($conn, "SELECT COALESCE(SUM($paidColumn),0) FROM fee_collections WHERE YEAR($dateColumn) = YEAR(CURDATE()) AND MONTH($dateColumn) = MONTH(CURDATE()) AND COALESCE(status,'Paid') = 'Paid'");
    $lastCollection = (float)sgf_fetchScalar($conn, "SELECT COALESCE(SUM($paidColumn),0) FROM fee_collections WHERE YEAR($dateColumn) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND MONTH($dateColumn) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND COALESCE(status,'Paid') = 'Paid'");
    if ($lastCollection <= 0) {
        return $currentCollection > 0 ? 100.0 : 0.0;
    }
    return ($currentCollection - $lastCollection) / max(1, $lastCollection) * 100;
}

function getAdmissionsVsWithdrawals(PDO $conn) {
    $months = [];
    for ($i = 5; $i >= 0; $i--) {
        $date = new DateTime("first day of -$i month");
        $months[] = $date->format('Y-m');
    }
    $source = sgf_getAdmissionsSource($conn);
    $results = [];
    foreach ($months as $monthKey) {
        $monthsArr = explode('-', $monthKey);
        $year = (int)$monthsArr[0];
        $month = (int)$monthsArr[1];
        $admissions = 0;
        if ($source) {
            $dateColumn = sgf_getDateColumn($conn, $source, ['created_at', 'application_date', 'submitted_at', 'date']);
            if ($dateColumn) {
                $admissions = (int)sgf_fetchScalar($conn, "SELECT COUNT(*) FROM $source WHERE YEAR($dateColumn) = ? AND MONTH($dateColumn) = ?", [$year, $month]);
            }
        }
        $withdrawals = 0;
        if (tableExists($conn, 'students')) {
            $dateColumn = sgf_getDateColumn($conn, 'students', ['updated_at', 'created_at']);
            if ($dateColumn) {
                $withdrawals = (int)sgf_fetchScalar($conn, "SELECT COUNT(*) FROM students WHERE LOWER(COALESCE(status, 'active')) NOT IN ('active','present') AND YEAR($dateColumn) = ? AND MONTH($dateColumn) = ?", [$year, $month]);
            }
        }
        $results[] = [
            'month' => date('M', strtotime($monthKey . '-01')),
            'admissions' => $admissions,
            'withdrawals' => $withdrawals,
        ];
    }
    return $results;
}

function getFeeIncomeExpenseFlow(PDO $conn) {
    $months = [];
    for ($i = 5; $i >= 0; $i--) {
        $date = new DateTime("first day of -$i month");
        $months[] = $date->format('Y-m');
    }
    $flow = [];
    $feeDate = sgf_getDateColumn($conn, 'fee_collections', ['payment_date', 'created_at']);
    $paidColumn = sgf_pickColumns($conn, 'fee_collections', ['paid_amount', 'amount_paid', 'amount']);
    $incomeDate = sgf_getDateColumn($conn, 'income', ['created_at']);
    $expenseDate = sgf_getDateColumn($conn, 'expenses', ['created_at']);
    foreach ($months as $monthKey) {
        $monthsArr = explode('-', $monthKey);
        $year = (int)$monthsArr[0];
        $month = (int)$monthsArr[1];
        $fee = 0;
        $income = 0;
        $expense = 0;
        if ($feeDate && $paidColumn && tableExists($conn, 'fee_collections')) {
            $fee = (float)sgf_fetchScalar($conn, "SELECT COALESCE(SUM($paidColumn),0) FROM fee_collections WHERE YEAR($feeDate) = ? AND MONTH($feeDate) = ? AND COALESCE(status,'Paid') = 'Paid'", [$year, $month]);
        }
        if ($incomeDate && tableExists($conn, 'income')) {
            $income = (float)sgf_fetchScalar($conn, "SELECT COALESCE(SUM(amount),0) FROM income WHERE YEAR($incomeDate) = ? AND MONTH($incomeDate) = ?", [$year, $month]);
        }
        if ($expenseDate && tableExists($conn, 'expenses')) {
            $expense = (float)sgf_fetchScalar($conn, "SELECT COALESCE(SUM(amount),0) FROM expenses WHERE YEAR($expenseDate) = ? AND MONTH($expenseDate) = ?", [$year, $month]);
        }
        $flow[] = [
            'month' => date('M', strtotime($monthKey . '-01')),
            'fee' => round($fee, 2),
            'income' => round($income, 2),
            'expense' => round($expense, 2),
        ];
    }
    return $flow;
}

function getAcademicAttendanceTrend(PDO $conn) {
    $studentAttendance = 0;
    $teacherAttendance = 0;
    $examPerformance = 0;

    if (tableExists($conn, 'student_attendance')) {
        $dateColumn = sgf_getDateColumn($conn, 'student_attendance', ['attendance_date', 'created_at']);
        if ($dateColumn) {
            $studentAttendance = (float)sgf_fetchScalar($conn, "SELECT COALESCE((SUM(CASE WHEN LOWER(TRIM(status)) IN ('present','p','1','attended') THEN 1 ELSE 0 END) / GREATEST(COUNT(*),1)) * 100, 0) FROM student_attendance WHERE $dateColumn >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
        }
    }

    if (tableExists($conn, 'staff_attendance')) {
        $staffDateColumn = sgf_getDateColumn($conn, 'staff_attendance', ['created_at', 'attendance_date']);
        if ($staffDateColumn) {
            $teacherAttendance = (float)sgf_fetchScalar($conn, "SELECT COALESCE((SUM(CASE WHEN LOWER(TRIM(status)) IN ('present','p','1','attended') THEN 1 ELSE 0 END) / GREATEST(COUNT(*),1)) * 100, 0) FROM staff_attendance WHERE $staffDateColumn >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
        }
    }

    if (tableExists($conn, 'exam_marks')) {
        $totalObtained = (float)sgf_fetchScalar($conn, "SELECT COALESCE(SUM(obtained_marks), 0) FROM exam_marks WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
        $totalMarks = (float)sgf_fetchScalar($conn, "SELECT COALESCE(SUM(total_marks), 0) FROM exam_marks WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
        $examPerformance = $totalMarks > 0 ? min(100.0, ($totalObtained / $totalMarks) * 100) : 0;
    }

    return [
        'student_attendance' => sgf_percent($studentAttendance),
        'teacher_attendance' => sgf_percent($teacherAttendance),
        'exam_performance' => sgf_percent($examPerformance),
    ];
}

function getGrowthSnapshot(PDO $conn) {
    $teachers = 0;
    $support = 0;
    if (tableExists($conn, 'staff')) {
        $teachers = (int)sgf_fetchScalar($conn, "SELECT COUNT(*) FROM staff WHERE LOWER(COALESCE(designation, role, '')) LIKE '%teacher%' AND LOWER(COALESCE(status, 'active')) = 'active'");
        $support = (int)sgf_fetchScalar($conn, "SELECT COUNT(*) FROM staff WHERE LOWER(COALESCE(designation, role, '')) NOT LIKE '%teacher%' AND LOWER(COALESCE(status, 'active')) = 'active'");
    }
    $expectedFee = 0;
    if (tableExists($conn, 'fee_structure')) {
        $expectedFee = (float)sgf_fetchScalar($conn, "SELECT COALESCE(SUM(amount), 0) FROM fee_structure");
    } elseif (tableExists($conn, 'fee_collections')) {
        $expectedFee = (float)sgf_fetchScalar($conn, "SELECT COALESCE(SUM(amount), 0) FROM fee_collections WHERE YEAR(COALESCE(payment_date, created_at)) = YEAR(CURDATE()) AND MONTH(COALESCE(payment_date, created_at)) = MONTH(CURDATE())");
    }
    $paidThisMonth = 0;
    if (tableExists($conn, 'fee_collections')) {
        $paidColumn = sgf_pickColumns($conn, 'fee_collections', ['paid_amount', 'amount_paid', 'amount']);
        $dateColumn = sgf_getDateColumn($conn, 'fee_collections', ['payment_date', 'created_at']);
        if ($paidColumn && $dateColumn) {
            $paidThisMonth = (float)sgf_fetchScalar($conn, "SELECT COALESCE(SUM($paidColumn), 0) FROM fee_collections WHERE YEAR($dateColumn) = YEAR(CURDATE()) AND MONTH($dateColumn) = MONTH(CURDATE()) AND COALESCE(status,'Paid') = 'Paid'");
        }
    }
    $engagement = 0;
    if (tableExists($conn, 'whatsapp_logs')) {
        $engagement += (int)sgf_fetchScalar($conn, "SELECT COUNT(*) FROM whatsapp_logs WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())");
    }
    if (tableExists($conn, 'communication_logs')) {
        $commDate = sgf_getDateColumn($conn, 'communication_logs', ['created_at', 'sent_at']);
        $recipientsColumn = sgf_pickColumns($conn, 'communication_logs', ['recipients_count', 'recipients']);
        if ($commDate && $recipientsColumn) {
            $engagement += (int)sgf_fetchScalar($conn, "SELECT COALESCE(SUM($recipientsColumn), 0) FROM communication_logs WHERE YEAR($commDate) = YEAR(CURDATE()) AND MONTH($commDate) = MONTH(CURDATE())");
        }
    }

    return [
        'teachers' => $teachers,
        'support_staff' => $support,
        'expected_fee' => round($expectedFee, 2),
        'paid' => round($paidThisMonth, 2),
        'parent_engagement' => $engagement,
    ];
}

function getRiskAlerts(PDO $conn) {
    $alerts = [];
    $feeRecovery = getFeeCollectionRate($conn);
    $attendance = getAttendanceBreadth($conn);
    $staffAttendance = 0;
    if (tableExists($conn, 'staff_attendance')) {
        $staffDate = sgf_getDateColumn($conn, 'staff_attendance', ['attendance_date', 'created_at']);
        if ($staffDate) {
            $staffAttendance = (float)sgf_fetchScalar(
                $conn,
                "SELECT COALESCE((SUM(CASE WHEN LOWER(TRIM(status)) IN ('present','p','1','attended') THEN 1 ELSE 0 END) / GREATEST(COUNT(*),1)) * 100, 0)
                 FROM staff_attendance
                 WHERE YEAR($staffDate) = YEAR(CURDATE()) AND MONTH($staffDate) = MONTH(CURDATE())"
            );
        }
    }
    $netPosition = getNetMonthlyPosition($conn);
    $retention = getRetentionRate($conn);
    if ($feeRecovery < 50) {
        $alerts[] = "Fee recovery is weak at " . sgf_percent($feeRecovery) . "% with pending dues of Rs. " . sgf_price(getPendingDues($conn)) . ".";
    }
    if ($attendance < 80) {
        $alerts[] = "Student attendance is only " . sgf_percent($attendance) . "% this month.";
    }
    if ($staffAttendance > 0 && $staffAttendance < 85) {
        $alerts[] = "Staff attendance dipped to " . sgf_percent($staffAttendance) . "% and needs follow-up.";
    }
    if ($netPosition < 0) {
        $alerts[] = "This month net cash flow is negative by Rs. " . sgf_price(abs($netPosition)) . ".";
    }
    if ($retention < 90) {
        $alerts[] = "Student retention is " . sgf_percent($retention) . "% and could indicate rising churn.";
    }
    $missingSections = 0;
    $missingSubjects = 0;
    if (tableExists($conn, 'timetable')) {
        $missingSections = (int)sgf_fetchScalar($conn, "SELECT COUNT(DISTINCT class) FROM timetable WHERE section IS NULL OR section = ''");
        $missingSubjects = (int)sgf_fetchScalar($conn, "SELECT COUNT(DISTINCT class) FROM timetable WHERE subject IS NULL OR subject = ''");
    }
    if ($missingSections > 0 || $missingSubjects > 0) {
        $alerts[] = "{$missingSections} class(es) missing sections, {$missingSubjects} class(es) missing subject mapping.";
    }
    if (empty($alerts)) {
        $alerts[] = "No major growth risk alerts detected for current data.";
    }
    return $alerts;
}

function getGrowthSuggestions(PDO $conn) {
    $suggestions = [];
    $feeRecovery = getFeeCollectionRate($conn);
    $attendance = getAttendanceBreadth($conn);
    $examTrend = 0;
    if (tableExists($conn, 'exam_marks')) {
        $totalObtained = (float)sgf_fetchScalar($conn, "SELECT COALESCE(SUM(obtained_marks), 0) FROM exam_marks");
        $totalMarks = (float)sgf_fetchScalar($conn, "SELECT COALESCE(SUM(total_marks), 0) FROM exam_marks");
        $examTrend = $totalMarks > 0 ? min(100.0, ($totalObtained / $totalMarks) * 100) : 0;
    }
    if ($feeRecovery < 65) {
        $suggestions[] = "Prioritize fee reminder calls and target top pending families first.";
    }
    if ($attendance < 85) {
        $suggestions[] = "Track chronic absentees class-wise and involve parents within 24 hours of repeated absence.";
    }
    if ($examTrend < 60) {
        $suggestions[] = "Add more assessments and unlock academic growth insights.";
    }
    $parentEngagement = 0;
    if (tableExists($conn, 'whatsapp_logs')) {
        $parentEngagement += (int)sgf_fetchScalar($conn, "SELECT COUNT(*) FROM whatsapp_logs WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())");
    }
    if (tableExists($conn, 'communication_logs')) {
        $commDate = sgf_getDateColumn($conn, 'communication_logs', ['created_at', 'sent_at']);
        $recipientsColumn = sgf_pickColumns($conn, 'communication_logs', ['recipients_count', 'recipients']);
        if ($commDate && $recipientsColumn) {
            $parentEngagement += (int)sgf_fetchScalar($conn, "SELECT COALESCE(SUM($recipientsColumn),0) FROM communication_logs WHERE YEAR($commDate) = YEAR(CURDATE()) AND MONTH($commDate) = MONTH(CURDATE())");
        }
    }
    if ($parentEngagement < 20) {
        $suggestions[] = "A short monthly parent update can improve trust and retention.";
    }
    if (empty($suggestions)) {
        $suggestions[] = "Continue current growth actions and monitor performance weekly.";
    }
    return $suggestions;
}

function getWinsAndPositiveSignals(PDO $conn) {
    $wins = [];
    $currentAdmissions = getAdmissionsThisMonth($conn);
    $previousAdmissions = getAdmissionsLastMonth($conn);
    if ($currentAdmissions > $previousAdmissions) {
        $wins[] = "Admissions improved this month from {$previousAdmissions} to {$currentAdmissions}.";
    }
    $currentFee = (float)sgf_fetchScalar($conn, "SELECT COALESCE(SUM(COALESCE(paid_amount, amount_paid, amount, 0)),0) FROM fee_collections WHERE YEAR(COALESCE(payment_date, created_at)) = YEAR(CURDATE()) AND MONTH(COALESCE(payment_date, created_at)) = MONTH(CURDATE()) AND COALESCE(status,'Paid') = 'Paid'");
    $priorFee = (float)sgf_fetchScalar($conn, "SELECT COALESCE(SUM(COALESCE(paid_amount, amount_paid, amount, 0)),0) FROM fee_collections WHERE YEAR(COALESCE(payment_date, created_at)) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND MONTH(COALESCE(payment_date, created_at)) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND COALESCE(status,'Paid') = 'Paid'");
    if ($currentFee > $priorFee) {
        $wins[] = "Fee collection improved compared to last month.";
    }
    $attendance = getAttendanceBreadth($conn);
    if ($attendance >= 85) {
        $wins[] = "Student attendance is stable at " . sgf_percent($attendance) . "%.";
    }
    if (empty($wins)) {
        $wins[] = "No major insight detected yet.";
    }
    return $wins;
}

function getRecommendedActions(PDO $conn) {
    $actions = [];
    $feeRecovery = getFeeCollectionRate($conn);
    $attendance = getAttendanceBreadth($conn);
    $examTrend = 0;
    if (tableExists($conn, 'exam_marks')) {
        $totalObtained = (float)sgf_fetchScalar($conn, "SELECT COALESCE(SUM(obtained_marks), 0) FROM exam_marks");
        $totalMarks = (float)sgf_fetchScalar($conn, "SELECT COALESCE(SUM(total_marks), 0) FROM exam_marks");
        $examTrend = $totalMarks > 0 ? min(100.0, ($totalObtained / $totalMarks) * 100) : 0;
    }
    $actions[] = "Create a fee-recovery list and contact parents with highest pending balances.";
    $actions[] = "Review teacher attendance and schedule a punctuality check-in with lowest attendance staff.";
    $actions[] = "Reduce discretionary expenses this month and improve collection follow-up.";
    return $actions;
}

function getTeacherPerformanceSnapshot(PDO $conn) {
    $teachers = [];
    if (!tableExists($conn, 'staff')) {
        return [
            'top' => null,
            'needs_review' => null,
            'open_teachers' => [],
        ];
    }
    $attendanceData = [];
    if (tableExists($conn, 'staff_attendance')) {
        $attendanceData = sgf_fetchRows($conn, "SELECT staff_id, SUM(CASE WHEN LOWER(TRIM(status)) IN ('present','p','1','attended') THEN 1 ELSE 0 END) AS present_count, COUNT(*) AS total_count FROM staff_attendance WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY staff_id");
    }
    $attendanceMap = [];
    foreach ($attendanceData as $row) {
        $attendanceMap[(int)$row['staff_id']] = ($row['total_count'] > 0 ? ($row['present_count'] / $row['total_count']) * 100 : 0);
    }
    $nameExpr = "'Teacher'";
    if (columnExists($conn, 'staff', 'full_name')) {
        $nameExpr = 'full_name';
    } elseif (columnExists($conn, 'staff', 'first_name') && columnExists($conn, 'staff', 'last_name')) {
        $nameExpr = "CONCAT(first_name, ' ', last_name)";
    } elseif (columnExists($conn, 'staff', 'username')) {
        $nameExpr = 'username';
    }
    $assignedClassExpr = "''";
    if (columnExists($conn, 'staff', 'class')) {
        $assignedClassExpr = 'class';
    } elseif (columnExists($conn, 'staff', 'assigned_class')) {
        $assignedClassExpr = 'assigned_class';
    }
    $teacherRows = sgf_fetchRows($conn, "SELECT id, COALESCE($nameExpr, 'Teacher') AS name, COALESCE($assignedClassExpr, '') AS assigned_class FROM staff WHERE LOWER(COALESCE(designation, role, '')) LIKE '%teacher%' ORDER BY id ASC");
    $top = null;
    $needsReview = null;
    foreach ($teacherRows as $teacher) {
        $rate = $attendanceMap[$teacher['id']] ?? 0;
        $record = [
            'name' => $teacher['name'],
            'attendance' => sgf_percent($rate),
            'assigned' => trim($teacher['assigned_class']) ?: 'Unassigned',
        ];
        if ($top === null || $rate > ($top['raw'] ?? -1)) {
            $top = $record + ['raw' => $rate];
        }
        if ($needsReview === null || $rate < ($needsReview['raw'] ?? 101)) {
            $needsReview = $record + ['raw' => $rate];
        }
    }
    $openTeachers = [];
    foreach ($teacherRows as $teacher) {
        if (trim($teacher['assigned_class']) === '') {
            $openTeachers[] = [
                'name' => $teacher['name'],
                'attendance' => sgf_percent($attendanceMap[$teacher['id']] ?? 0),
                'assigned' => 'Unassigned',
            ];
        }
        if (count($openTeachers) >= 5) {
            break;
        }
    }
    return [
        'top' => $top,
        'needs_review' => $needsReview,
        'open_teachers' => $openTeachers,
    ];
}

function getOperationalHealth(PDO $conn) {
    $classesMissingSections = 0;
    $classesMissingSubjects = 0;
    if (tableExists($conn, 'timetable')) {
        $classesMissingSections = (int)sgf_fetchScalar($conn, "SELECT COUNT(DISTINCT class) FROM timetable WHERE section IS NULL OR section = ''");
        $classesMissingSubjects = (int)sgf_fetchScalar($conn, "SELECT COUNT(DISTINCT class) FROM timetable WHERE subject IS NULL OR subject = ''");
    }
    $incompleteProfiles = 0;
    if (tableExists($conn, 'students')) {
        $incompleteProfiles = (int)sgf_fetchScalar($conn, "SELECT COUNT(*) FROM students WHERE first_name IS NULL OR first_name = '' OR guardian_name IS NULL OR guardian_name = '' OR guardian_phone IS NULL OR guardian_phone = ''");
    }
    $unassignedTeachers = 0;
    if (tableExists($conn, 'staff')) {
        $assignedClassCol = null;
        if (columnExists($conn, 'staff', 'class')) {
            $assignedClassCol = 'class';
        } elseif (columnExists($conn, 'staff', 'assigned_class')) {
            $assignedClassCol = 'assigned_class';
        }

        if ($assignedClassCol !== null) {
            $unassignedTeachers = (int)sgf_fetchScalar($conn, "SELECT COUNT(*) FROM staff WHERE LOWER(COALESCE(designation, role, '')) LIKE '%teacher%' AND ($assignedClassCol = '' OR $assignedClassCol IS NULL)");
        } else {
            if (tableExists($conn, 'timetable')) {
                $unassignedTeachers = (int)sgf_fetchScalar($conn, "SELECT COUNT(*) FROM staff WHERE LOWER(COALESCE(designation, role, '')) LIKE '%teacher%' AND id NOT IN (SELECT DISTINCT teacher_id FROM timetable WHERE teacher_id IS NOT NULL)");
            } else {
                $unassignedTeachers = 0;
            }
        }
    }
    return [
        'classes_missing_sections' => $classesMissingSections,
        'classes_missing_subjects' => $classesMissingSubjects,
        'incomplete_student_profiles' => $incompleteProfiles,
        'unassigned_teachers' => $unassignedTeachers,
    ];
}

function getClasswiseGrowthInsights(PDO $conn) {
    if (!tableExists($conn, 'students')) {
        return [];
    }
    $students = sgf_fetchRows($conn, "SELECT id, class FROM students WHERE class IS NOT NULL AND class <> ''");
    $classMap = [];
    foreach ($students as $student) {
        $className = $student['class'];
        if (!isset($classMap[$className])) {
            $classMap[$className] = ['students' => 0, 'attendance' => 0, 'result' => 0, 'pending' => 0, 'insight' => 'No data yet'];
        }
        $classMap[$className]['students']++;
    }
    foreach ($classMap as $className => &$row) {
        if (tableExists($conn, 'student_attendance')) {
            $row['attendance'] = (float)sgf_fetchScalar($conn, "SELECT COALESCE((SUM(CASE WHEN LOWER(TRIM(s.status)) IN ('present','p','1','attended') THEN 1 ELSE 0 END) / GREATEST(COUNT(s.id),1)) * 100,0) FROM student_attendance s JOIN students t ON t.id = s.student_id WHERE t.class = ?", [$className]);
        }
        if (tableExists($conn, 'exam_marks')) {
            $row['result'] = (float)sgf_fetchScalar($conn, "SELECT COALESCE((SUM(obtained_marks) / GREATEST(SUM(total_marks),1)) * 100, 0) FROM exam_marks WHERE class = ?", [$className]);
        }
        if (tableExists($conn, 'fee_collections')) {
            $amountCol = sgf_pickColumns($conn, 'fee_collections', ['amount']);
            $paidCol = sgf_pickColumns($conn, 'fee_collections', ['paid_amount', 'amount_paid']);
            if ($amountCol && $paidCol) {
                $row['pending'] = (float)sgf_fetchScalar($conn, "SELECT COALESCE(SUM(GREATEST($amountCol - COALESCE($paidCol,0),0)),0) FROM fee_collections fc JOIN students s ON s.id = fc.student_id WHERE s.class = ?", [$className]);
            }
        }
        if ($row['pending'] > 100000 || $row['attendance'] < 75 || $row['result'] < 50) {
            $row['insight'] = 'At Risk';
        } elseif ($row['attendance'] >= 85 && $row['result'] >= 65) {
            $row['insight'] = 'Good';
        } else {
            $row['insight'] = 'Needs Attention';
        }
        $row['attendance'] = sgf_percent($row['attendance']);
        $row['result'] = sgf_percent($row['result']);
    }
    unset($row);

    $rows = [];
    foreach ($classMap as $className => $row) {
        $rows[] = array_merge(['class' => $className], $row);
    }
    return $rows;
}

function getSmartActivityBlocks(PDO $conn) {
    $reports = 0;
    if (tableExists($conn, 'communication_logs')) {
        $reports = (int)sgf_fetchScalar($conn, "SELECT COUNT(*) FROM communication_logs WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())");
    }
    $features = tableExists($conn, 'feature_requests') ? (int)sgf_fetchScalar($conn, "SELECT COUNT(*) FROM feature_requests WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())") : 0;
    $broadcasts = 0;
    if (tableExists($conn, 'communication_logs')) {
        $commDate = sgf_getDateColumn($conn, 'communication_logs', ['created_at', 'sent_at']);
        $messageTypeColumn = sgf_pickColumns($conn, 'communication_logs', ['message_type', 'message']);
        if ($commDate && $messageTypeColumn) {
            $broadcasts = (int)sgf_fetchScalar($conn, "SELECT COUNT(*) FROM communication_logs WHERE $messageTypeColumn IN ('WhatsApp','SMS') AND YEAR($commDate) = YEAR(CURDATE()) AND MONTH($commDate) = MONTH(CURDATE())");
        }
    }
    return [
        'generated_reports' => $reports,
        'feature_requests' => $features,
        'parent_broadcasts' => $broadcasts,
    ];
}

function getAdmissionReadiness(PDO $conn) {
    $capacity = 30;
    $current = getActiveStudentCount($conn);
    $available = max(0, $capacity - $current);
    $classes = 0;
    $sections = 0;
    if (tableExists($conn, 'students')) {
        $classes = (int)sgf_fetchScalar($conn, "SELECT COUNT(DISTINCT class) FROM students WHERE class IS NOT NULL AND class <> ''");
        $sections = (int)sgf_fetchScalar($conn, "SELECT COUNT(DISTINCT section) FROM students WHERE section IS NOT NULL AND section <> ''");
    }
    return [
        'student_capacity' => $capacity,
        'current_students' => $current,
        'available_seats' => $available,
        'classes_sections' => "{$classes}/{$sections}",
    ];
}

function getFeeRecoveryBoard(PDO $conn) {
    if (!tableExists($conn, 'fee_collections') || !tableExists($conn, 'students')) {
        return [];
    }
    $amountCol = sgf_pickColumns($conn, 'fee_collections', ['amount']);
    $paidCol = sgf_pickColumns($conn, 'fee_collections', ['paid_amount', 'amount_paid']);
    if (!$amountCol || !$paidCol) {
        return [];
    }
    $rows = sgf_fetchRows($conn, "SELECT s.id, COALESCE(s.first_name, '') AS first_name, COALESCE(s.last_name, '') AS last_name, COALESCE(s.class, '') AS class, SUM(GREATEST($amountCol - COALESCE($paidCol,0),0)) AS pending FROM students s JOIN fee_collections fc ON fc.student_id = s.id GROUP BY s.id ORDER BY pending DESC LIMIT 5");
    return array_filter($rows, function ($row) {
        return isset($row['pending']) && (float)$row['pending'] > 0;
    });
}

function getStudentRiskWatchlist(PDO $conn) {
    if (!tableExists($conn, 'students')) {
        return [];
    }
    $threshold = 5000;
    $rows = [];
    $students = sgf_fetchRows($conn, "SELECT id, first_name, last_name, class FROM students WHERE class IS NOT NULL AND class <> ''");
    foreach ($students as $student) {
        $studentId = (int)$student['id'];
        $riskReasons = [];
        if (tableExists($conn, 'student_attendance')) {
            $dateColumn = sgf_getDateColumn($conn, 'student_attendance', ['attendance_date', 'created_at']);
            if ($dateColumn) {
                $present = (float)sgf_fetchScalar($conn, "SELECT COUNT(*) FROM student_attendance WHERE student_id = ? AND YEAR($dateColumn) = YEAR(CURDATE()) AND MONTH($dateColumn) = MONTH(CURDATE()) AND LOWER(TRIM(status)) IN ('present','p','1','attended')", [$studentId]);
                $total = (float)sgf_fetchScalar($conn, "SELECT COUNT(*) FROM student_attendance WHERE student_id = ? AND YEAR($dateColumn) = YEAR(CURDATE()) AND MONTH($dateColumn) = MONTH(CURDATE())", [$studentId]);
                $attendance = $total > 0 ? ($present / $total) * 100 : 100;
                if ($attendance < 60) {
                    $riskReasons[] = 'Attendance below 60%';
                }
            }
        }
        if (tableExists($conn, 'fee_collections')) {
            $amountCol = sgf_pickColumns($conn, 'fee_collections', ['amount']);
            $paidCol = sgf_pickColumns($conn, 'fee_collections', ['paid_amount', 'amount_paid']);
            if ($amountCol && $paidCol) {
                $pending = (float)sgf_fetchScalar($conn, "SELECT COALESCE(SUM(GREATEST($amountCol - COALESCE($paidCol,0),0)),0) FROM fee_collections WHERE student_id = ?", [$studentId]);
                if ($pending > $threshold) {
                    $riskReasons[] = 'High pending dues';
                }
            }
        }
        if (tableExists($conn, 'exam_marks')) {
            $percent = (float)sgf_fetchScalar($conn, "SELECT COALESCE((SUM(obtained_marks) / GREATEST(SUM(total_marks),1)) * 100,0) FROM exam_marks WHERE student_id = ?", [$studentId]);
            if ($percent < 40) {
                $riskReasons[] = 'Low exam performance';
            }
        }
        if (!empty($riskReasons)) {
            $rows[] = [
                'name' => trim($student['first_name'] . ' ' . $student['last_name']),
                'class' => $student['class'],
                'reason' => implode('; ', $riskReasons),
            ];
        }
    }
    return $rows;
}
