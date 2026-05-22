<?php
// File: change-password.php
require_once 'config/db.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

$database = new Database();
$db = $database->getConnection();
$user_id = getUserId();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!verifyCsrfToken()) {
        $error = 'Security check failed. Please refresh the page and try again.';
    } elseif (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'All fields are required.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New password and confirmation do not match.';
    } else {
        $stmt = $db->prepare('SELECT password FROM users WHERE id = :id');
        $stmt->execute([':id' => $user_id]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($current_password, $user['password'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new_password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } else {
            $stmt = $db->prepare('UPDATE users SET password = :password WHERE id = :id');
            $stmt->execute([
                ':password' => password_hash($new_password, PASSWORD_DEFAULT),
                ':id' => $user_id,
            ]);

            setFlashMessage('success', 'Password changed successfully.');
            redirect('change-password.php');
        }
    }
}

$page_title = 'Change Password';
include 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">Change Password</h6>
            </div>
            <div class="card-body">
                <?php displayFlashMessage(); ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <?= csrfTokenInput() ?>
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password</label>
                        <input type="password" id="current_password" name="current_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" id="new_password" name="new_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Update Password</button>
                    <a href="profile.php" class="btn btn-secondary">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
