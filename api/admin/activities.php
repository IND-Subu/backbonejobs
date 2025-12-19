<?php
require_once '../config.php';

// requireAdmin();

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    getActivities();
} else {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

/**
 * Get activity logs with filters
 */
function getActivities() {
    global $db;
    
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
    $activityType = isset($_GET['type']) ? sanitizeInput($_GET['type']) : '';
    $dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
    $dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
    
    $query = "SELECT al.*, 
              u.name as user_name, u.email as user_email,
              e.company_name as employer_name
              FROM activity_log al
              LEFT JOIN users u ON al.user_id = u.id
              LEFT JOIN employers e ON al.employer_id = e.id
              WHERE 1=1";
    
    $params = [];
    $types = '';
    
    if ($activityType) {
        $query .= " AND al.activity_type = ?";
        $params[] = $activityType;
        $types .= 's';
    }
    
    if ($dateFrom) {
        $query .= " AND DATE(al.created_at) >= ?";
        $params[] = $dateFrom;
        $types .= 's';
    }
    
    if ($dateTo) {
        $query .= " AND DATE(al.created_at) <= ?";
        $params[] = $dateTo;
        $types .= 's';
    }
    
    $query .= " ORDER BY al.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';
    
    $stmt = $db->prepare($query);
    
    if ($types) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $activities = [];
    while ($row = $result->fetch_assoc()) {
        $activities[] = $row;
    }
    
    $stmt->close();
    
    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM activity_log WHERE 1=1";
    $countParams = [];
    $countTypes = '';
    
    if ($activityType) {
        $countQuery .= " AND activity_type = ?";
        $countParams[] = $activityType;
        $countTypes .= 's';
    }
    
    if ($dateFrom) {
        $countQuery .= " AND DATE(created_at) >= ?";
        $countParams[] = $dateFrom;
        $countTypes .= 's';
    }
    
    if ($dateTo) {
        $countQuery .= " AND DATE(created_at) <= ?";
        $countParams[] = $dateTo;
        $countTypes .= 's';
    }
    
    $countStmt = $db->prepare($countQuery);
    if ($countTypes) {
        $countStmt->bind_param($countTypes, ...$countParams);
    }
    $countStmt->execute();
    $totalCount = $countStmt->get_result()->fetch_assoc()['total'];
    $countStmt->close();
    
    jsonResponse([
        'success' => true,
        'activities' => $activities,
        'count' => count($activities),
        'total' => (int)$totalCount
    ]);
}
?>