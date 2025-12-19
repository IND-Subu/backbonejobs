<?php
// ========================================
// FILE 1: admin/users.php
// ========================================
require_once '../config.php';
requireAdmin();

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $query = "SELECT u.*, 
              (SELECT COUNT(*) FROM applications WHERE user_id = u.id) as application_count
              FROM users u ORDER BY u.created_at DESC";
    $result = $db->query($query);
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    jsonResponse(['success' => true, 'users' => $users]);
} elseif ($method === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = intval($data['user_id']);
    $isActive = intval($data['is_active']);
    
    $stmt = $db->prepare("UPDATE users SET is_active = ? WHERE id = ?");
    $stmt->bind_param('ii', $isActive, $userId);
    
    if ($stmt->execute()) {
        logActivity('user_status_updated', "User ID: $userId status changed", null, null, getAdminId());
        jsonResponse(['success' => true, 'message' => 'User updated']);
    } else {
        jsonResponse(['success' => false, 'message' => 'Update failed'], 500);
    }
}
?>