<?php
class Database {
    private $host     = 'switchyard.proxy.rlwy.net';
    private $port     = '52480';
    private $db_name  = 'railway';
    private $username = 'root';
    private $password = 'tpsoZPSwawoCMBtHEBERvDoEgWWOhFcP';
    private $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->db_name};charset=utf8mb4";
            $this->conn = new PDO($dsn,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(
                PDO::ATTR_ERRMODE,
                PDO::ERRMODE_EXCEPTION
            );
        } catch(PDOException $e) {
            echo "Connection Error: " . $e->getMessage();
        }
        return $this->conn;
    }
}

// Helper functions
function isLoggedIn() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['user_id']) && 
           !empty($_SESSION['user_id']);
}

function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

function sanitizeInput($data) {
    return htmlspecialchars(
        strip_tags(trim($data))
    );
}

function redirect($url) {
    header("Location: " . $url);
    exit();
}
?>