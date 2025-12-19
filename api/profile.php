<?php
require_once '../config.php';

requireAdmin();

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        getAdminProfile();
        break;
    
    case 'PUT':
        updateAdminProfile();
        break;
    
    default:
        jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

/**
 * Get admin profile
 */
function getAdminProfile() {
    global $db;
    
    $adminId = getAdminId();
    
    if (!$adminId) {
        jsonResponse(['success' => false, 'message' => 'Admin ID not found'], 400);
    }
    
    $stmt = $db->prepare("SELECT id, name, email, phone, role, is_active, last_login, created_at 
                          FROM admins 
                          WHERE id = ?");
    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        jsonResponse(['success' => false, 'message' => 'Admin not found'], 404);
    }
    
    $admin = $result->fetch_assoc();
    $stmt->close();
    
    jsonResponse([
        'success' => true,
        'admin' => $admin
    ]);
}

/**
 * Update admin profile
 */
function updateAdminProfile() {
    global $db;
    
    $adminId = getAdminId();
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$adminId) {
        jsonResponse(['success' => false, 'message' => 'Admin ID not found'], 400);
    }
    
    $name = isset($data['name']) ? sanitizeInput($data['name']) : null;
    $phone = isset($data['phone']) ? sanitizeInput($data['phone']) : null;
    
    if (!$name) {
        jsonResponse(['success' => false, 'message' => 'Name is required'], 400);
    }
    
    $stmt = $db->prepare("UPDATE admins SET name = ?, phone = ? WHERE id = ?");
    $stmt->bind_param('ssi', $name, $phone, $adminId);
    
    if ($stmt->execute()) {
        logActivity('admin_profile_updated', "Admin ID: $adminId updated profile", null, null, $adminId);
        
        jsonResponse([
            'success' => true,
            'message' => 'Profile updated successfully'
        ]);
    } else {
        jsonResponse(['success' => false, 'message' => 'Failed to update profile'], 500);
    }
    
    $stmt->close();
}
?>