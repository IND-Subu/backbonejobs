<?php
require_once '../config.php';

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$data = json_decode(file_get_contents('php://input'), true);

// Validate user type
$userType = $data['user_type'] ?? 'jobseeker';

if ($userType === 'employer') {
    registerEmployer($data);
} else {
    registerJobSeeker($data);
}

function registerJobSeeker($data) {
    global $db;
    
    // Validate required fields
    $required = ['name', 'email', 'phone', 'password', 'current_location'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            jsonResponse(['success' => false, 'message' => "Field $field is required"], 400);
        }
    }
    
    $name = sanitizeInput($data['name']);
    $email = sanitizeInput($data['email']);
    $phone = sanitizeInput($data['phone']);
    $password = $data['password'];
    $currentLocation = sanitizeInput($data['current_location']);
    
    // Validate email
    if (!validateEmail($email)) {
        jsonResponse(['success' => false, 'message' => 'Invalid email format'], 400);
    }
    
    // Validate phone
    if (!validatePhone($phone)) {
        jsonResponse(['success' => false, 'message' => 'Invalid phone number. Must be 10 digits starting with 6-9'], 400);
    }
    
    // Validate password
    if (strlen($password) < 6) {
        jsonResponse(['success' => false, 'message' => 'Password must be at least 6 characters'], 400);
    }
    
    // Check if email already exists
    $checkStmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $checkStmt->bind_param('s', $email);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        jsonResponse(['success' => false, 'message' => 'Email already registered'], 409);
    }
    $checkStmt->close();
    
    // Check if phone already exists
    $phoneStmt = $db->prepare("SELECT id FROM users WHERE phone = ?");
    $phoneStmt->bind_param('s', $phone);
    $phoneStmt->execute();
    $phoneResult = $phoneStmt->get_result();
    
    if ($phoneResult->num_rows > 0) {
        jsonResponse(['success' => false, 'message' => 'Phone number already registered'], 409);
    }
    $phoneStmt->close();
    
    // Hash password
    $hashedPassword = hashPassword($password);
    
    // Insert user
    $query = "INSERT INTO users (name, email, phone, password, current_location, is_active) 
              VALUES (?, ?, ?, ?, ?, 1)";
    
    $stmt = $db->prepare($query);
    $stmt->bind_param('sssss', $name, $email, $phone, $hashedPassword, $currentLocation);
    
    try {
        if ($stmt->execute()) {
            $userId = $db->lastInsertId();
            
            // Log activity
            logActivity('user_registered', "User ID: $userId", $userId, null, null);
            
            // Auto login
            $_SESSION['user_id'] = $userId;
            $_SESSION['user_email'] = $email;
            
            $token = generateToken();
            
            jsonResponse([
                'success' => true,
                'message' => 'Registration successful',
                'token' => $token,
                'user_type' => 'jobseeker',
                'user' => [
                    'id' => $userId,
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'current_location' => $currentLocation
                ]
            ], 201);
        } else {
            jsonResponse(['success' => false, 'message' => 'Registration failed'], 500);
        }
    } catch (Exception $e) {
        error_log("Registration error: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'An error occurred during registration'], 500);
    }
    
    $stmt->close();
}

function registerEmployer($data) {
    global $db;
    
    // Validate required fields
    $required = ['company_name', 'contact_person', 'email', 'phone', 'password', 'company_address', 'city', 'state'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            jsonResponse(['success' => false, 'message' => "Field $field is required"], 400);
        }
    }
    
    $companyName = sanitizeInput($data['company_name']);
    $contactPerson = sanitizeInput($data['contact_person']);
    $email = sanitizeInput($data['email']);
    $phone = sanitizeInput($data['phone']);
    $password = $data['password'];
    $whatsappNumber = isset($data['whatsapp_number']) ? sanitizeInput($data['whatsapp_number']) : $phone;
    $companyAddress = sanitizeInput($data['company_address']);
    $city = sanitizeInput($data['city']);
    $state = sanitizeInput($data['state']);
    
    // Validate email
    if (!validateEmail($email)) {
        jsonResponse(['success' => false, 'message' => 'Invalid email format'], 400);
    }
    
    // Validate phone
    if (!validatePhone($phone)) {
        jsonResponse(['success' => false, 'message' => 'Invalid phone number. Must be 10 digits starting with 6-9'], 400);
    }
    
    // Validate password
    if (strlen($password) < 6) {
        jsonResponse(['success' => false, 'message' => 'Password must be at least 6 characters'], 400);
    }
    
    // Check if email already exists
    $checkStmt = $db->prepare("SELECT id FROM employers WHERE email = ?");
    $checkStmt->bind_param('s', $email);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        jsonResponse(['success' => false, 'message' => 'Email already registered'], 409);
    }
    $checkStmt->close();
    
    // Check if phone already exists
    $phoneStmt = $db->prepare("SELECT id FROM employers WHERE phone = ?");
    $phoneStmt->bind_param('s', $phone);
    $phoneStmt->execute();
    $phoneResult = $phoneStmt->get_result();
    
    if ($phoneResult->num_rows > 0) {
        jsonResponse(['success' => false, 'message' => 'Phone number already registered'], 409);
    }
    $phoneStmt->close();
    
    // Hash password
    $hashedPassword = hashPassword($password);
    
    // Insert employer
    $query = "INSERT INTO employers (company_name, contact_person, email, phone, whatsapp_number, 
              password, company_address, city, state, is_active, is_verified) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0)";
    
    $stmt = $db->prepare($query);
    $stmt->bind_param('sssssssss', 
        $companyName, 
        $contactPerson, 
        $email, 
        $phone, 
        $whatsappNumber, 
        $hashedPassword, 
        $companyAddress, 
        $city, 
        $state
    );
    
    try {
        if ($stmt->execute()) {
            $employerId = $db->lastInsertId();
            
            // Log activity
            logActivity('employer_registered', "Employer ID: $employerId", null, $employerId, null);
            
            // Auto login
            $_SESSION['employer_id'] = $employerId;
            $_SESSION['employer_email'] = $email;
            
            $token = generateToken();
            
            jsonResponse([
                'success' => true,
                'message' => 'Registration successful. Your account will be verified soon.',
                'token' => $token,
                'user_type' => 'employer',
                'user' => [
                    'id' => $employerId,
                    'name' => $companyName,
                    'email' => $email,
                    'phone' => $phone,
                    'is_verified' => false
                ]
            ], 201);
        } else {
            jsonResponse(['success' => false, 'message' => 'Registration failed'], 500);
        }
    } catch (Exception $e) {
        error_log("Registration error: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'An error occurred during registration'], 500);
    }
    
    $stmt->close();
}
?>