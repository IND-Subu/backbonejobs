<?php
require_once '../config.php';

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (!isset($data['email']) || !isset($data['password'])) {
    jsonResponse(['success' => false, 'message' => 'Email and password are required'], 400);
}

$email = sanitizeInput($data['email']);
$password = $data['password'];
$userType = $data['user_type'] ?? 'jobseeker';

// Validate email
if (!validateEmail($email)) {
    jsonResponse(['success' => false, 'message' => 'Invalid email format'], 400);
}

try {
    if ($userType === 'employer') {
        // Employer login
        $stmt = $db->prepare("SELECT id, company_name as name, email, password, is_active, is_verified FROM employers WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            jsonResponse(['success' => false, 'message' => 'Invalid credentials'], 401);
        }
        
        $employer = $result->fetch_assoc();
        $stmt->close();
        
        if (!$employer['is_active']) {
            jsonResponse(['success' => false, 'message' => 'Account is inactive'], 403);
        }
        
        if (!verifyPassword($password, $employer['password'])) {
            jsonResponse(['success' => false, 'message' => 'Invalid credentials'], 401);
        }
        
        // Set session
        $_SESSION['employer_id'] = $employer['id'];
        $_SESSION['employer_email'] = $employer['email'];
        
        // Update last login
        $db->query("UPDATE employers SET last_login = NOW() WHERE id = " . $employer['id']);
        
        // Log activity
        logActivity('employer_login', 'Employer logged in', null, $employer['id'], null);
        
        // Generate token
        $token = generateToken();
        
        unset($employer['password']);
        
        jsonResponse([
            'success' => true,
            'message' => 'Login successful',
            'token' => $token,
            'user_type' => 'employer',
            'user' => $employer
        ]);
        
    } else {
        // Job seeker login
        $stmt = $db->prepare("SELECT id, name, email, phone, password, is_active FROM users WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            jsonResponse(['success' => false, 'message' => 'Invalid credentials'], 401);
        }
        
        $user = $result->fetch_assoc();
        $stmt->close();
        
        if (!$user['is_active']) {
            jsonResponse(['success' => false, 'message' => 'Account is inactive'], 403);
        }
        
        if (!verifyPassword($password, $user['password'])) {
            jsonResponse(['success' => false, 'message' => 'Invalid credentials'], 401);
        }
        
        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        
        // Update last login
        $db->query("UPDATE users SET last_login = NOW() WHERE id = " . $user['id']);
        
        // Log activity
        logActivity('user_login', 'User logged in', $user['id'], null, null);
        
        // Generate token
        $token = generateToken();
        
        unset($user['password']);
        
        jsonResponse([
            'success' => true,
            'message' => 'Login successful',
            'token' => $token,
            'user_type' => 'jobseeker',
            'user' => $user
        ]);
    }
    
} catch (Exception $e) {
    error_log("Login error: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'An error occurred during login'], 500);
}