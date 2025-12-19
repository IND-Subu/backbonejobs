<?php
require_once '../config.php';

requireAdmin();

$db = Database::getInstance();

$days = isset($_GET['days']) ? intval($_GET['days']) : 30;

if ($days < 1) $days = 30;
if ($days > 365) $days = 365;

try {
    $reports = [];
    
    // New users in period
    $usersStmt = $db->prepare("SELECT COUNT(*) as count FROM users 
                                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)");
    $usersStmt->bind_param('i', $days);
    $usersStmt->execute();
    $reports['new_users'] = (int)$usersStmt->get_result()->fetch_assoc()['count'];
    $usersStmt->close();
    
    // New employers in period
    $employersStmt = $db->prepare("SELECT COUNT(*) as count FROM employers 
                                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)");
    $employersStmt->bind_param('i', $days);
    $employersStmt->execute();
    $reports['new_employers'] = (int)$employersStmt->get_result()->fetch_assoc()['count'];
    $employersStmt->close();
    
    // New jobs in period
    $jobsStmt = $db->prepare("SELECT COUNT(*) as count FROM jobs 
                               WHERE posted_date >= DATE_SUB(NOW(), INTERVAL ? DAY)");
    $jobsStmt->bind_param('i', $days);
    $jobsStmt->execute();
    $reports['new_jobs'] = (int)$jobsStmt->get_result()->fetch_assoc()['count'];
    $jobsStmt->close();
    
    // New applications in period
    $appsStmt = $db->prepare("SELECT COUNT(*) as count FROM applications 
                               WHERE applied_date >= DATE_SUB(NOW(), INTERVAL ? DAY)");
    $appsStmt->bind_param('i', $days);
    $appsStmt->execute();
    $reports['new_applications'] = (int)$appsStmt->get_result()->fetch_assoc()['count'];
    $appsStmt->close();
    
    // Application status breakdown
    $statusQuery = "SELECT status, COUNT(*) as count FROM applications GROUP BY status";
    $statusResult = $db->query($statusQuery);
    $reports['application_status'] = [];
    while ($row = $statusResult->fetch_assoc()) {
        $reports['application_status'][$row['status']] = (int)$row['count'];
    }
    
    // Top categories by jobs
    $categoriesQuery = "SELECT 
                        c.category_name,
                        COUNT(DISTINCT j.id) as job_count,
                        COUNT(a.id) as application_count,
                        ROUND(COUNT(a.id) / NULLIF(COUNT(DISTINCT j.id), 0), 1) as avg_applications
                        FROM job_categories c
                        LEFT JOIN jobs j ON c.id = j.category_id
                        LEFT JOIN applications a ON j.id = a.job_id
                        GROUP BY c.id, c.category_name
                        HAVING job_count > 0
                        ORDER BY job_count DESC
                        LIMIT 10";
    $categoriesResult = $db->query($categoriesQuery);
    $reports['top_categories'] = [];
    while ($row = $categoriesResult->fetch_assoc()) {
        $reports['top_categories'][] = $row;
    }
    
    // Top employers by activity
    $employersQuery = "SELECT 
                       e.company_name,
                       e.is_verified,
                       COUNT(DISTINCT j.id) as job_count,
                       COUNT(a.id) as application_count
                       FROM employers e
                       LEFT JOIN jobs j ON e.id = j.employer_id
                       LEFT JOIN applications a ON e.id = a.employer_id
                       WHERE e.is_active = 1
                       GROUP BY e.id, e.company_name, e.is_verified
                       HAVING job_count > 0
                       ORDER BY job_count DESC
                       LIMIT 10";
    $employersResult = $db->query($employersQuery);
    $reports['top_employers'] = [];
    while ($row = $employersResult->fetch_assoc()) {
        $reports['top_employers'][] = $row;
    }
    
    // Growth trends (daily data for the period)
    $trendsStmt = $db->prepare("
        SELECT 
            DATE(created_at) as date,
            'users' as type,
            COUNT(*) as count
        FROM users
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY DATE(created_at)
        
        UNION ALL
        
        SELECT 
            DATE(created_at) as date,
            'employers' as type,
            COUNT(*) as count
        FROM employers
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY DATE(created_at)
        
        UNION ALL
        
        SELECT 
            DATE(posted_date) as date,
            'jobs' as type,
            COUNT(*) as count
        FROM jobs
        WHERE posted_date >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY DATE(posted_date)
        
        UNION ALL
        
        SELECT 
            DATE(applied_date) as date,
            'applications' as type,
            COUNT(*) as count
        FROM applications
        WHERE applied_date >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY DATE(applied_date)
        
        ORDER BY date DESC, type
    ");
    $trendsStmt->bind_param('iiii', $days, $days, $days, $days);
    $trendsStmt->execute();
    $trendsResult = $trendsStmt->get_result();
    $reports['trends'] = [];
    while ($row = $trendsResult->fetch_assoc()) {
        $reports['trends'][] = $row;
    }
    $trendsStmt->close();
    
    jsonResponse([
        'success' => true,
        'reports' => $reports,
        'period_days' => $days
    ]);
    
} catch (Exception $e) {
    error_log('Reports error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Failed to generate reports'], 500);
}
?>