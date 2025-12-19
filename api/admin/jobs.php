<?php
// ========================================
// FILE 3: admin/jobs.php
// ========================================
require_once '../config.php';
requireAdmin();

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $query = "SELECT j.*, c.category_name, e.company_name,
              (SELECT COUNT(*) FROM applications WHERE job_id = j.id) as application_count
              FROM jobs j
              LEFT JOIN job_categories c ON j.category_id = c.id
              LEFT JOIN employers e ON j.employer_id = e.id
              ORDER BY j.posted_date DESC";
    $result = $db->query($query);
    $jobs = [];
    while ($row = $result->fetch_assoc()) {
        $jobs[] = $row;
    }
    jsonResponse(['success' => true, 'jobs' => $jobs]);
} elseif ($method === 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true);
    $jobId = intval($data['job_id']);
    
    $stmt = $db->prepare("DELETE FROM jobs WHERE id = ?");
    $stmt->bind_param('i', $jobId);
    
    if ($stmt->execute()) {
        logActivity('job_deleted_by_admin', "Job ID: $jobId", null, null, getAdminId());
        jsonResponse(['success' => true]);
    } else {
        jsonResponse(['success' => false], 500);
    }
}
?>