<?php
require_once 'config.php';

$db = Database::getInstance();

// Get overall statistics
$stats = [
    'total_jobs' => 0,
    'active_jobs' => 0,
    'total_companies' => 0,
    'total_applications' => 0,
    'total_users' => 0
];

// Total and active jobs
$jobsQuery = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active
    FROM jobs";
$jobsResult = $db->query($jobsQuery);
if ($row = $jobsResult->fetch_assoc()) {
    $stats['total_jobs'] = (int)$row['total'];
    $stats['active_jobs'] = (int)$row['active'];
}

// Total companies
$companiesQuery = "SELECT COUNT(*) as total FROM employers WHERE is_active = 1";
$companiesResult = $db->query($companiesQuery);
if ($row = $companiesResult->fetch_assoc()) {
    $stats['total_companies'] = (int)$row['total'];
}

// Total applications
$applicationsQuery = "SELECT COUNT(*) as total FROM applications";
$applicationsResult = $db->query($applicationsQuery);
if ($row = $applicationsResult->fetch_assoc()) {
    $stats['total_applications'] = (int)$row['total'];
}

// Total users
$usersQuery = "SELECT COUNT(*) as total FROM users WHERE is_active = 1";
$usersResult = $db->query($usersQuery);
if ($row = $usersResult->fetch_assoc()) {
    $stats['total_users'] = (int)$row['total'];
}

jsonResponse([
    'success' => true,
    'stats' => $stats
]);
?>