<?php
require_once '../config.php';

$db = Database::getInstance();

// Require employer authentication
requireEmployer();

$employerId = getEmployerId();

// Get employer statistics
$stats = [
    'total_jobs' => 0,
    'active_jobs' => 0,
    'total_applications' => 0,
    'pending_applications' => 0,
    'total_views' => 0
];

// Total and active jobs
$jobsQuery = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active,
    SUM(views) as total_views
    FROM jobs
    WHERE employer_id = ?";

$stmt = $db->prepare($jobsQuery);
$stmt->bind_param('i', $employerId);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $stats['total_jobs'] = (int)$row['total'];
    $stats['active_jobs'] = (int)$row['active'];
    $stats['total_views'] = (int)$row['total_views'];
}
$stmt->close();

// Total applications
$appsQuery = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending
    FROM applications
    WHERE employer_id = ?";

$stmt = $db->prepare($appsQuery);
$stmt->bind_param('i', $employerId);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $stats['total_applications'] = (int)$row['total'];
    $stats['pending_applications'] = (int)$row['pending'];
}
$stmt->close();

jsonResponse([
    'success' => true,
    'stats' => $stats
]);
?>