<?php
require_once '../config.php';

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

/**
 * Admin Registration
 */
$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$requiredFields = ['name', 'email', 'password', 'role', 'registration_code'];
foreach ($requiredFields as $field) {
    if (!isset($data[$field]) || empty($data[$field])) {
        jsonResponse(['success' => false, 'message' => "Field '$field' is required"], 400);
    }
}

$name = sanitizeInput($data['name']);
$email = sanitizeInput($data['email']);
$password = $data['password'];
$phone = isset($data['phone']) ? sanitizeInput($data['phone']) : null;
$role = sanitizeInput($data['role']);
$registrationCode = sanitizeInput($data['registration_code']);

try {
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['success' => false, 'message' => 'Invalid email address'], 400);
    }
    
    // Validate password strength
    if (strlen($password) < 8) {
        jsonResponse(['success' => false, 'message' => 'Password must be at least 8 characters long'], 400);
    }
    
    // Check password complexity
    if (!preg_match('/[A-Z]/', $password) || 
        !preg_match('/[a-z]/', $password) || 
        !preg_match('/[0-9]/', $password) || 
        !preg_match('/[^a-zA-Z0-9]/', $password)) {
        jsonResponse([
            'success' => false, 
            'message' => 'Password must include uppercase, lowercase, number, and special character'
        ], 400);
    }
    
    // Validate role
    $validRoles = ['super_admin', 'admin', 'moderator'];
    if (!in_array($role, $validRoles)) {
        jsonResponse(['success' => false, 'message' => 'Invalid role'], 400);
    }
    
    // Verify registration code
    $codeStmt = $db->prepare("SELECT role, is_used, expires_at FROM registration_codes 
                              WHERE code = ? AND is_active = 1");
    $codeStmt->bind_param('s', $registrationCode);
    $codeStmt->execute();
    $codeResult = $codeStmt->get_result();
    
    if ($codeResult->num_rows === 0) {
        jsonResponse(['success' => false, 'message' => 'Invalid registration code'], 400);
    }
    
    $codeData = $codeResult->fetch_assoc();
    $codeStmt->close();
    
    // Check if code is already used
    if ($codeData['is_used']) {
        jsonResponse(['success' => false, 'message' => 'Registration code has already been used'], 400);
    }
    
    // Check if code is expired
    if ($codeData['expires_at'] && strtotime($codeData['expires_at']) < time()) {
        jsonResponse(['success' => false, 'message' => 'Registration code has expired'], 400);
    }
    
    // Verify role matches code
    if ($codeData['role'] !== $role) {
        jsonResponse(['success' => false, 'message' => 'Role does not match registration code'], 400);
    }
    
    // Check if email already exists
    $checkStmt = $db->prepare("SELECT id FROM admins WHERE email = ?");
    $checkStmt->bind_param('s', $email);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        jsonResponse(['success' => false, 'message' => 'Email address is already registered'], 400);
    }
    $checkStmt->close();
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert admin
    $stmt = $db->prepare("INSERT INTO admins (name, email, password, phone, role, is_active) 
                          VALUES (?, ?, ?, ?, ?, 1)");
    $stmt->bind_param('sssss', $name, $email, $hashedPassword, $phone, $role);
    
    if (!$stmt->execute()) {
        jsonResponse(['success' => false, 'message' => 'Failed to create admin account'], 500);
    }
    
    $adminId = $db->lastInsertId();
    $stmt->close();
    
    // Mark registration code as used
    $updateCodeStmt = $db->prepare("UPDATE registration_codes 
                                     SET is_used = 1, used_by_admin_id = ?, used_at = NOW() 
                                     WHERE code = ?");
    $updateCodeStmt->bind_param('is', $adminId, $registrationCode);
    $updateCodeStmt->execute();
    $updateCodeStmt->close();
    
    // Log activity
    logActivity('admin_registered', "New admin registered: $name ($email) with role: $role", null, null, $adminId);
    
    // Send welcome email (optional - implement if needed)
    // sendWelcomeEmail($email, $name, $role);
    
    jsonResponse([
        'success' => true,
        'message' => 'Admin account created successfully',
        'admin_id' => $adminId
    ], 201);
    
} catch (Exception $e) {
    error_log('Admin registration error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'An error occurred during registration'], 500);
}
?>