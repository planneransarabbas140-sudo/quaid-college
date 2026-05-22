<?php
// File: profile.php - User Profile
require_once 'config/db.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

$database = new Database();
$db = $database->getConnection();

$user_id = getUserId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitizeInput($_POST['full_name']);
    $phone = sanitizeInput($_POST['phone']);
    $email = sanitizeInput($_POST['email']);
    
    $stmt = $db->prepare("UPDATE users SET full_name = :name, phone = :phone, email = :email WHERE id = :id");
    if ($stmt->execute([':name' => $full_name, ':phone' => $phone, ':email' => $email, ':id' => $user_id])) {
        $_SESSION['user_name'] = $full_name;
        $_SESSION['user_email'] = $email;
        setFlashMessage('success', 'Profile updated successfully!');
        redirect('profile.php');
    }
}

$stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
$stmt->execute([':id' => $user_id]);
$user = $stmt->fetch();

$page_title = "My Profile";
include 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">My Profile</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="mb-3">
                        <label>Full Name</label>
                        <input type="text" name="full_name" class="form-control" value="<?php echo $user['full_name']; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" value="<?php echo $user['email']; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label>Phone</label>
                        <input type="text" name="phone" class="form-control" value="<?php echo $user['phone']; ?>">
                    </div>
                    <div class="mb-3">
                        <label>Username</label>
                        <input type="text" class="form-control" value="<?php echo $user['username']; ?>" disabled>
                    </div>
                    <div class="mb-3">
                        <label>Role</label>
                        <input type="text" class="form-control" value="<?php echo ucfirst($user['role']); ?>" disabled>
                    </div>
                    <button type="submit" class="btn btn-primary">Update Profile</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>