<?php
// ========================================
// FILE 4: admin/applications.php (listing)
// ========================================
require_once '../config.php';
requireAdmin();

$db = Database::getInstance();

$query = "SELECT a.*, 
          u.name as applicant_name, u.email as applicant_email,
          j.title as job_title, e.company_name
          FROM applications a
          LEFT JOIN users u ON a.user_id = u.id
          LEFT JOIN jobs j ON a.job_id = j.id
          LEFT JOIN employers e ON a.employer_id = e.id
          ORDER BY a.applied_date DESC";
$result = $db->query($query);
$applications = [];
while ($row = $result->fetch_assoc()) {
    $applications[] = $row;
}
jsonResponse(['success' => true, 'applications' => $applications]);
?>