<?php
require_once 'config/db.php';
$database = new Database();
$db = $database->getConnection();

$correct_hash = '$2y$10$dnvye750HWTOWZgno4bF7eg8bRaChplaqT/WNEGbFn1zSRTc8YWM2'; // Hash for Admin@123

if (isset($_GET['fix'])) {
    $stmt = $db->prepare("UPDATE users SET password = :p WHERE username = 'admin'");
    $stmt->execute([':p' => $correct_hash]);
    echo "<div style='background:green; color:white; padding:10px; margin-bottom:20px;'>SUCCESS: Password for 'admin' has been reset to 'Admin@123'.</div>";
}

echo "<h1>Database Debugger</h1>";
echo "<h3>Users in Database:</h3>";
$users = $db->query("SELECT id, username, email, password, role FROM users")->fetchAll();

if (empty($users)) {
    echo "No users found.";
} else {
    echo "<table border='1' cellpadding='10'><tr><th>ID</th><th>Username</th><th>Email</th><th>Password (Hash)</th><th>Role</th></tr>";
    foreach ($users as $user) {
        $status = ($user['username'] === 'admin' && password_verify('Admin@123', $user['password'])) ? "✅ MATCH" : "❌ NO MATCH";
        echo "<tr>";
        echo "<td>{$user['id']}</td>";
        echo "<td>{$user['username']}</td>";
        echo "<td>{$user['email']}</td>";
        echo "<td>" . substr($user['password'], 0, 15) . "...</td>";
        echo "<td>{$user['role']} ($status)</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<br><a href='?fix=1' style='background:#4ec2b5; color:white; padding:15px 30px; text-decoration:none; border-radius:10px; display:inline-block; font-weight:bold;'>CLICK HERE TO FIX ADMIN PASSWORD NOW</a>";
?>
