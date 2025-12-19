<?php
require_once '../config.php';

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['email']) || !isset($data['password'])) {
    jsonResponse(['success' => false, 'message' => 'Email and password are required'], 400);
}

$email = sanitizeInput($data['email']);
$password = $data['password'];
$rememberMe = isset($data['remember_me']) ? (bool)$data['remember_me'] : false;

try {
    // FIXED COLUMN NAMES
    $stmt = $db->prepare("
        SELECT id, username, full_name, email, password, role, is_active, last_login
        FROM admins
        WHERE email = ?
    ");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        usleep(500000);
        jsonResponse(['success' => false, 'message' => 'Invalid email or password'], 401);
    }

    $admin = $result->fetch_assoc();
    $stmt->close();

    // Check active status
    if (!$admin['is_active']) {
        jsonResponse(['success' => false, 'message' => 'Account is deactivated'], 403);
    }

    // Password verify
    if (!password_verify($password, $admin['password'])) {
        usleep(500000);
        logActivity('admin_login_failed', "Failed login attempt for: $email", null, null, null);
        jsonResponse(['success' => false, 'message' => 'Invalid email or password'], 401);
    }

    // Generate token
    $token = bin2hex(random_bytes(32));
    $tokenExpiry = $rememberMe ? (time() + 2592000) : (time() + 86400);
    
    // Clear old employer or admin session
    unset($_SESSION['employer_id']);
    unset($_SESSION['user_id']);
    
    session_start();
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_token'] = $token;
    $_SESSION['user_type'] = 'admin';
    $_SESSION['admin_role'] = $admin['role'];

    // Update last login
    $updateStmt = $db->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?");
    $updateStmt->bind_param('i', $admin['id']);
    $updateStmt->execute();
    $updateStmt->close();

    logActivity('admin_login', 'Admin logged in successfully', null, null, $admin['id']);

    unset($admin['password']);

    jsonResponse([
        'success' => true,
        'message' => 'Login successful',
        'token' => $token,
        'admin' => [
            'id' => $admin['id'],
            'username' => $admin['username'],
            'full_name' => $admin['full_name'],
            'email' => $admin['email'],
            'role' => $admin['role']
        ]
    ]);

} catch (Exception $e) {
    error_log('Admin login error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'An error occurred during login'], 500);
}
?>
