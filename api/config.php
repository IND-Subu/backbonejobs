<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'bb_jobs');

// Site Configuration
define('SITE_URL', 'http://localhost/backbonejobs/');
define('UPLOAD_PATH', '../uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB

// Session Configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 in production with HTTPS

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database Connection Class
class Database {
    private static $instance = null;
    private $conn;
    
    private function __construct() {
        try {
            $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            
            if ($this->conn->connect_error) {
                throw new Exception("Connection failed: " . $this->conn->connect_error);
            }
            
            $this->conn->set_charset("utf8mb4");
        } catch (Exception $e) {
            error_log($e->getMessage());
            die(json_encode(['success' => false, 'message' => 'Database connection failed']));
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->conn;
    }
    
    public function prepare($query) {
        return $this->conn->prepare($query);
    }
    
    public function query($query) {
        return $this->conn->query($query);
    }
    
    public function escape($string) {
        return $this->conn->real_escape_string($string);
    }
    
    public function lastInsertId() {
        return $this->conn->insert_id;
    }
}

// Helper Functions
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validatePhone($phone) {
    return preg_match('/^[6-9]\d{9}$/', $phone);
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

function isAuthenticated() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function isEmployer() {
    return isset($_SESSION['employer_id']) && !empty($_SESSION['employer_id']);
}

function isAdmin() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getEmployerId() {
    return $_SESSION['employer_id'] ?? null;
}

function getAdminId() {
    return $_SESSION['admin_id'] ?? null;
}

function requireAuth() {
    if (!isAuthenticated()) {
        jsonResponse(['success' => false, 'message' => 'Authentication required'], 401);
    }
}

function requireEmployer() {
    if (!isEmployer()) {
        jsonResponse(['success' => false, 'message' => 'Employer authentication required'], 401);
    }
}

function requireAdmin() {
    if (!isAdmin()) {
        jsonResponse(['success' => false, 'message' => 'Admin authentication required'], 401);
    }
}

function uploadFile($file, $allowedTypes = ['pdf', 'doc', 'docx'], $maxSize = MAX_FILE_SIZE) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'File upload error'];
    }
    
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'File size exceeds limit'];
    }
    
    $fileName = $file['name'];
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    if (!in_array($fileExt, $allowedTypes)) {
        return ['success' => false, 'message' => 'Invalid file type'];
    }
    
    $newFileName = uniqid() . '_' . time() . '.' . $fileExt;
    $uploadPath = UPLOAD_PATH . $newFileName;
    
    if (!is_dir(UPLOAD_PATH)) {
        mkdir(UPLOAD_PATH, 0755, true);
    }
    
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        return ['success' => true, 'filename' => $newFileName];
    }
    
    return ['success' => false, 'message' => 'Failed to move uploaded file'];
}

function deleteFile($filename) {
    $filePath = UPLOAD_PATH . $filename;
    if (file_exists($filePath)) {
        return unlink($filePath);
    }
    return false;
}

function logActivity($action, $description = '', $userId = null, $employerId = null, $adminId = null) {
    try {
        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO activity_log (user_id, employer_id, admin_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $stmt->bind_param('iiissss', $userId, $employerId, $adminId, $action, $description, $ipAddress, $userAgent);
        $stmt->execute();
        $stmt->close();
    } catch (Exception $e) {
        error_log("Activity log error: " . $e->getMessage());
    }
}

function sendNotification($userId, $employerId, $type, $title, $message, $link = null) {
    try {
        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO notifications (user_id, employer_id, type, title, message, link) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('iissss', $userId, $employerId, $type, $title, $message, $link);
        $stmt->execute();
        $stmt->close();
        return true;
    } catch (Exception $e) {
        error_log("Notification error: " . $e->getMessage());
        return false;
    }
}

// CORS Headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}
?>
