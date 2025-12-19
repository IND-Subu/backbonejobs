<?php
// FILE 2: admin/employers.php
// ========================================
require_once '../config.php';
requireAdmin();

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $query = "SELECT e.*, 
              (SELECT COUNT(*) FROM jobs WHERE employer_id = e.id) as job_count
              FROM employers e ORDER BY e.created_at DESC";
    $result = $db->query($query);
    $employers = [];
    while ($row = $result->fetch_assoc()) {
        $employers[] = $row;
    }
    jsonResponse(['success' => true, 'employers' => $employers]);
} elseif ($method === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    $empId = intval($data['employer_id']);
    
    $updates = [];
    $params = [];
    $types = '';
    
    if (isset($data['is_active'])) {
        $updates[] = "is_active = ?";
        $params[] = intval($data['is_active']);
        $types .= 'i';
    }
    if (isset($data['is_verified'])) {
        $updates[] = "is_verified = ?";
        $params[] = intval($data['is_verified']);
        $types .= 'i';
    }
    
    $params[] = $empId;
    $types .= 'i';
    
    $query = "UPDATE employers SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        logActivity('employer_updated', "Employer ID: $empId", null, null, getAdminId());
        jsonResponse(['success' => true]);
    } else {
        jsonResponse(['success' => false], 500);
    }
}
?>