<?php
require_once '../config.php';

// requireAdmin();

$db = Database::getInstance();

/**
 * Get analytics data for specified time period
 */
$days = isset($_GET['days']) ? intval($_GET['days']) : 7;

if ($days < 1) $days = 7;
if ($days > 365) $days = 365;

try {
    $analytics = [];
    
    // Generate date range
    $dates = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $dates[] = $date;
    }
    
    // User registrations per day
    $userStmt = $db->prepare("
        SELECT DATE(created_at) as date, COUNT(*) as count
        FROM users
        WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
        GROUP BY DATE(created_at)
        ORDER BY date
    ");
    $userStmt->bind_param('i', $days);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    
    $usersByDate = [];
    while ($row = $userResult->fetch_assoc()) {
        $usersByDate[$row['date']] = (int)$row['count'];
    }
    $userStmt->close();
    
    // Employer registrations per day
    $employerStmt = $db->prepare("
        SELECT DATE(created_at) as date, COUNT(*) as count
        FROM employers
        WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
        GROUP BY DATE(created_at)
        ORDER BY date
    ");
    $employerStmt->bind_param('i', $days);
    $employerStmt->execute();
    $employerResult = $employerStmt->get_result();
    
    $employersByDate = [];
    while ($row = $employerResult->fetch_assoc()) {
        $employersByDate[$row['date']] = (int)$row['count'];
    }
    $employerStmt->close();
    
    // Jobs posted per day
    $jobsStmt = $db->prepare("
        SELECT DATE(posted_date) as date, COUNT(*) as count
        FROM jobs
        WHERE DATE(posted_date) >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
        GROUP BY DATE(posted_date)
        ORDER BY date
    ");
    $jobsStmt->bind_param('i', $days);
    $jobsStmt->execute();
    $jobsResult = $jobsStmt->get_result();
    
    $jobsByDate = [];
    while ($row = $jobsResult->fetch_assoc()) {
        $jobsByDate[$row['date']] = (int)$row['count'];
    }
    $jobsStmt->close();
    
    // Applications per day
    $appsStmt = $db->prepare("
        SELECT DATE(applied_date) as date, COUNT(*) as count
        FROM applications
        WHERE DATE(applied_date) >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
        GROUP BY DATE(applied_date)
        ORDER BY date
    ");
    $appsStmt->bind_param('i', $days);
    $appsStmt->execute();
    $appsResult = $appsStmt->get_result();
    
    $appsByDate = [];
    while ($row = $appsResult->fetch_assoc()) {
        $appsByDate[$row['date']] = (int)$row['count'];
    }
    $appsStmt->close();
    
    // Build analytics array
    foreach ($dates as $date) {
        $analytics[] = [
            'date' => $date,
            'users' => $usersByDate[$date] ?? 0,
            'employers' => $employersByDate[$date] ?? 0,
            'jobs' => $jobsByDate[$date] ?? 0,
            'applications' => $appsByDate[$date] ?? 0
        ];
    }
    
    // Get summary statistics
    $summary = [];
    
    // Total users
    $totalUsersStmt = $db->prepare("SELECT COUNT(*) as count FROM users");
    $totalUsersStmt->execute();
    $summary['total_users'] = (int)$totalUsersStmt->get_result()->fetch_assoc()['count'];
    $totalUsersStmt->close();
    
    // Total employers
    $totalEmployersStmt = $db->prepare("SELECT COUNT(*) as count FROM employers");
    $totalEmployersStmt->execute();
    $summary['total_employers'] = (int)$totalEmployersStmt->get_result()->fetch_assoc()['count'];
    $totalEmployersStmt->close();
    
    // Active jobs
    $activeJobsStmt = $db->prepare("SELECT COUNT(*) as count FROM jobs WHERE status = 'Active'");
    $activeJobsStmt->execute();
    $summary['active_jobs'] = (int)$activeJobsStmt->get_result()->fetch_assoc()['count'];
    $activeJobsStmt->close();
    
    // Total applications
    $totalAppsStmt = $db->prepare("SELECT COUNT(*) as count FROM applications");
    $totalAppsStmt->execute();
    $summary['total_applications'] = (int)$totalAppsStmt->get_result()->fetch_assoc()['count'];
    $totalAppsStmt->close();
    
    // Top categories by job count
    $topCategoriesStmt = $db->prepare("
        SELECT c.category_name, COUNT(j.id) as job_count
        FROM job_categories c
        LEFT JOIN jobs j ON c.id = j.category_id
        GROUP BY c.id, c.category_name
        ORDER BY job_count DESC
        LIMIT 5
    ");
    $topCategoriesStmt->execute();
    $topCategoriesResult = $topCategoriesStmt->get_result();
    
    $topCategories = [];
    while ($row = $topCategoriesResult->fetch_assoc()) {
        $topCategories[] = $row;
    }
    $topCategoriesStmt->close();
    
    // Application status breakdown
    $statusStmt = $db->prepare("
        SELECT status, COUNT(*) as count
        FROM applications
        GROUP BY status
    ");
    $statusStmt->execute();
    $statusResult = $statusStmt->get_result();
    
    $statusBreakdown = [];
    while ($row = $statusResult->fetch_assoc()) {
        $statusBreakdown[$row['status']] = (int)$row['count'];
    }
    $statusStmt->close();
    
    jsonResponse([
        'success' => true,
        'analytics' => $analytics,
        'summary' => $summary,
        'top_categories' => $topCategories,
        'status_breakdown' => $statusBreakdown,
        'period_days' => $days
    ]);
    
} catch (Exception $e) {
    error_log('Analytics error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Failed to fetch analytics'], 500);
}
?>