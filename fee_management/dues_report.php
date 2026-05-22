<?php
// File: modules/fee_management/dues_report.php - Outstanding Dues Report
require_once '../../config/db.php';

if (!isLoggedIn()) {
    redirect('../../index.php');
}

$database = new Database();
$db = $database->getConnection();

// Get students with outstanding dues
$query = "SELECT 
            s.id, s.student_id, s.first_name, s.last_name, s.class, s.section, s.guardian_name, s.guardian_phone,
            COALESCE(SUM(fc.amount - fc.paid_amount), 0) as total_due,
            COUNT(CASE WHEN fc.status IN ('Pending', 'Partially Paid') THEN 1 END) as pending_invoices,
            MAX(fc.due_date) as last_due_date
          FROM students s
          LEFT JOIN fee_collections fc ON s.id = fc.student_id AND fc.status IN ('Pending', 'Partially Paid')
          GROUP BY s.id
          HAVING total_due > 0
          ORDER BY total_due DESC";

$dues_students = $db->query($query)->fetchAll();

$total_due = array_sum(array_column($dues_students, 'total_due'));

$page_title = "Outstanding Dues Report";
include '../../includes/header.php';
?>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold">Outstanding Dues Report</h6>
        <div>
            <button onclick="window.print()" class="btn btn-primary btn-sm">
                <i class="fas fa-print"></i> Print Report
            </button>
            <a href="export_dues.php" class="btn btn-success btn-sm">
                <i class="fas fa-file-excel"></i> Export to Excel
            </a>
        </div>
    </div>
    <div class="card-body">
        <div class="alert alert-warning mb-4">
            <strong>Total Outstanding Dues:</strong> PKR <?php echo number_format($total_due, 2); ?>
            <strong class="ms-4">Students with Dues:</strong> <?php echo count($dues_students); ?>
        </div>
        
        <?php if (count($dues_students) > 0): ?>
        <div class="table-responsive">
            <table class="table table-bordered datatable">
                <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Student Name</th>
                        <th>Class</th>
                        <th>Guardian</th>
                        <th>Contact</th>
                        <th>Pending Invoices</th>
                        <th>Total Due (PKR)</th>
                        <th>Last Due Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dues_students as $student): ?>
                    <tr>
                        <td><?php echo $student['student_id']; ?></td>
                        <td><?php echo $student['first_name'] . ' ' . $student['last_name']; ?></td>
                        <td><?php echo $student['class'] . '-' . $student['section']; ?></td>
                        <td><?php echo $student['guardian_name']; ?></td>
                        <td><?php echo $student['guardian_phone']; ?></td>
                        <td class="text-center"><?php echo $student['pending_invoices']; ?></td>
                        <td class="text-danger fw-bold">PKR <?php echo number_format($student['total_due'], 2); ?></td>
                        <td><?php echo $student['last_due_date'] ? date('d M Y', strtotime($student['last_due_date'])) : '-'; ?></td>
                        <td>
                            <a href="add_collection.php?student_id=<?php echo $student['id']; ?>" class="btn btn-sm btn-success">
                                <i class="fas fa-money-bill"></i> Receive Payment
                            </a>
                            <a href="../student_profile/view.php?id=<?php echo $student['id']; ?>" class="btn btn-sm btn-info">
                                <i class="fas fa-user"></i> View
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="table-warning">
                        <th colspan="6" class="text-end">TOTAL:</th>
                        <th>PKR <?php echo number_format($total_due, 2); ?></th>
                        <th colspan="2"></th>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <!-- Send Reminder Section -->
        <div class="mt-4">
            <div class="card">
                <div class="card-header">
                    <h6>Send Payment Reminders</h6>
                </div>
                <div class="card-body">
                    <form action="send_reminders.php" method="POST">
                        <div class="row">
                            <div class="col-md-4">
                                <select name="reminder_type" class="form-select" required>
                                    <option value="all">All Students with Dues</option>
                                    <option value="overdue_30">Overdue by 30+ days</option>
                                    <option value="overdue_60">Overdue by 60+ days</option>
                                    <option value="custom">Custom Selection</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" name="send_sms" class="btn btn-info">
                                    <i class="fas fa-sms"></i> Send SMS Reminders
                                </button>
                                <button type="submit" name="send_email" class="btn btn-primary">
                                    <i class="fas fa-envelope"></i> Send Email Reminders
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> No outstanding dues! All students have cleared their fees.
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>