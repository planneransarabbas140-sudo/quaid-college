<?php
// File: logout.php
require_once 'config/db.php';
session_destroy();
setcookie('remember_token', '', time() - 3600, '/');
header("Location: " . BASE_URL . "index.php");
exit();
?>