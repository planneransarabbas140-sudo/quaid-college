<?php
// File: modules/front_desk/new_inquiry.php
require_once '../../config/db.php';

if (!isLoggedIn()) {
    redirect('../../index.php');
}
requireRole(['admin', 'owner']);

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inquiry_date = $_POST['inquiry_date'];
    $name = sanitizeInput($_POST['name']);
    $phone = sanitizeInput($_POST['phone']);
    $email = sanitizeInput($_POST['email']);
    $purpose = sanitizeInput($_POST['purpose']);
    $assigned_to = !empty($_POST['assigned_to']) ? $_POST['assigned_to'] : null;
    
    try {
        $stmt = $db->prepare("INSERT INTO front_desk_inquiries (inquiry_date, name, phone, email, purpose, assigned_to) VALUES (:inquiry_date, :name, :phone, :email, :purpose, :assigned_to)");
        $stmt->execute([
            ':inquiry_date' => $inquiry_date,
            ':name' => $name,
            ':phone' => $phone,
            ':email' => $email,
            ':purpose' => $purpose,
            ':assigned_to' => $assigned_to
        ]);
        $success = "Inquiry added successfully!";
    } catch (PDOException $e) {
        $error = "Error adding inquiry: " . $e->getMessage();
    }
}

// Fetch staff for assignment
$staff = $db->query("SELECT id, full_name FROM users WHERE role IN ('admin', 'teacher')")->fetchAll();

$page_title = "New Inquiry";
include '../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h2 class="page-title mb-0">New Inquiry</h2>
        <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
</div>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Inquiry Date *</label>
                            <input type="date" class="form-control" name="inquiry_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Name *</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone *</label>
                            <input type="text" class="form-control" name="phone" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Purpose of Inquiry *</label>
                        <textarea class="form-control" name="purpose" rows="3" required></textarea>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">Assign To</label>
                        <select class="form-select" name="assigned_to">
                            <option value="">-- Select Staff (Optional) --</option>
                            <?php foreach ($staff as $user): ?>
                                <option value="<?php echo $user['id']; ?>"><?php echo $user['full_name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Inquiry</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
