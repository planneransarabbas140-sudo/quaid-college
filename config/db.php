<?php
if (!defined('BASE_URL')) {
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $basePath = '/';
    $knownRoots = ['/modules/', '/admin/'];

    foreach ($knownRoots as $root) {
        $pos = strpos($scriptName, $root);
        if ($pos !== false) {
            $basePath = substr($scriptName, 0, $pos + 1);
            break;
        }
    }

    define('BASE_URL', $basePath ?: '/');
}

if (!function_exists('startSecureSession')) {
    function startSecureSession() {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        $sessionPath = __DIR__ . '/../storage/sessions';
        if (is_dir(dirname($sessionPath)) && !is_dir($sessionPath)) {
            @mkdir($sessionPath, 0775, true);
        }
        if (is_dir($sessionPath) && is_writable($sessionPath)) {
            session_save_path($sessionPath);
        }

        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => BASE_URL,
                'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        session_start();
    }
}

class Database {
    private $host     = 'switchyard.proxy.rlwy.net';
    private $port     = '52480';
    private $db_name  = 'railway';
    private $username = 'root';
    private $password = 'tpsoZPSwawoCMBtHEBERvDoEgWWOhFcP';
    private $conn;

    public function __construct() {
        $url = getenv('MYSQL_URL') ?: getenv('DATABASE_URL');
        if ($url) {
            $parts = parse_url($url);
            if ($parts !== false) {
                $this->host = $parts['host'] ?? $this->host;
                $this->port = isset($parts['port']) ? (string)$parts['port'] : $this->port;
                $this->username = isset($parts['user']) ? rawurldecode($parts['user']) : $this->username;
                $this->password = isset($parts['pass']) ? rawurldecode($parts['pass']) : $this->password;
                $this->db_name = isset($parts['path']) ? ltrim($parts['path'], '/') : $this->db_name;
            }
        } else {
            $this->host = getenv('MYSQLHOST') ?: getenv('DB_HOST') ?: $this->host;
            $this->port = getenv('MYSQLPORT') ?: getenv('DB_PORT') ?: $this->port;
            $this->db_name = getenv('MYSQLDATABASE') ?: getenv('DB_DATABASE') ?: $this->db_name;
            $this->username = getenv('MYSQLUSER') ?: getenv('DB_USERNAME') ?: $this->username;
            $this->password = getenv('MYSQLPASSWORD') ?: getenv('DB_PASSWORD') ?: $this->password;
        }
    }

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
        startSecureSession();
    }
    return isset($_SESSION['user_id']) && 
           !empty($_SESSION['user_id']);
}

function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getUserRole() {
    return strtolower((string)($_SESSION['user_role'] ?? $_SESSION['role'] ?? 'guest'));
}

function enforcePasswordChange() {
    $current = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    if (!empty($_SESSION['must_change_password']) && strpos($current, '/change-password.php') === false) {
        redirect(BASE_URL . 'change-password.php');
    }
}

function generateCsrfToken() {
    if (session_status() === PHP_SESSION_NONE) {
        startSecureSession();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfTokenInput() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function verifyCsrfToken() {
    if (session_status() === PHP_SESSION_NONE) {
        startSecureSession();
    }
    $token = $_POST['csrf_token'] ?? $_POST['_csrf'] ?? '';
    return is_string($token) && $token !== '' && hash_equals((string)($_SESSION['csrf_token'] ?? ''), $token);
}

function tableExists(PDO $db, string $table): bool {
    try {
        $stmt = $db->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('tableExists failed: ' . $e->getMessage());
        return false;
    }
}

function columnExists(PDO $db, string $table, string $column): bool {
    try {
        $stmt = $db->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $stmt->execute([$column]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('columnExists failed: ' . $e->getMessage());
        return false;
    }
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
