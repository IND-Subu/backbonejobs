<?php
require_once '../config.php';

// Check if user is admin
// requireAdmin();

$db = Database::getInstance();

try {
    // Total Users
    $usersStmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE is_active = 1");
    $usersStmt->execute();
    $totalUsers = $usersStmt->get_result()->fetch_assoc()['total'];
    $usersStmt->close();
    
    // New Users This Week
    $newUsersStmt = $db->prepare("SELECT COUNT(*) as total FROM users 
                                   WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $newUsersStmt->execute();
    $newUsersWeek = $newUsersStmt->get_result()->fetch_assoc()['total'];
    $newUsersStmt->close();
    
    // Total Employers
    $employersStmt = $db->prepare("SELECT COUNT(*) as total FROM employers WHERE is_active = 1");
    $employersStmt->execute();
    $totalEmployers = $employersStmt->get_result()->fetch_assoc()['total'];
    $employersStmt->close();
    
    // New Employers This Week
    $newEmployersStmt = $db->prepare("SELECT COUNT(*) as total FROM employers 
                                       WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $newEmployersStmt->execute();
    $newEmployersWeek = $newEmployersStmt->get_result()->fetch_assoc()['total'];
    $newEmployersStmt->close();
    
    // Active Jobs
    $activeJobsStmt = $db->prepare("SELECT COUNT(*) as total FROM jobs WHERE status = 'Active'");
    $activeJobsStmt->execute();
    $activeJobs = $activeJobsStmt->get_result()->fetch_assoc()['total'];
    $activeJobsStmt->close();
    
    // Inactive Jobs
    $inactiveJobsStmt = $db->prepare("SELECT COUNT(*) as total FROM jobs WHERE status != 'Active'");
    $inactiveJobsStmt->execute();
    $inactiveJobs = $inactiveJobsStmt->get_result()->fetch_assoc()['total'];
    $inactiveJobsStmt->close();
    
    // Total Applications
    $applicationsStmt = $db->prepare("SELECT COUNT(*) as total FROM applications");
    $applicationsStmt->execute();
    $totalApplications = $applicationsStmt->get_result()->fetch_assoc()['total'];
    $applicationsStmt->close();
    
    // New Applications Today
    $newAppsStmt = $db->prepare("SELECT COUNT(*) as total FROM applications 
                                  WHERE DATE(applied_date) = CURDATE()");
    $newAppsStmt->execute();
    $newAppsToday = $newAppsStmt->get_result()->fetch_assoc()['total'];
    $newAppsStmt->close();
    
    // Pending Approvals (employers awaiting verification)
    $pendingStmt = $db->prepare("SELECT COUNT(*) as total FROM employers 
                                  WHERE is_verified = 0 AND is_active = 1");
    $pendingStmt->execute();
    $pendingApprovals = $pendingStmt->get_result()->fetch_assoc()['total'];
    $pendingStmt->close();
    
    // Unread Feedback
    $feedbackStmt = $db->prepare("SELECT COUNT(*) as total FROM feedback WHERE status = 'unread'");
    $feedbackStmt->execute();
    $unreadFeedback = $feedbackStmt->get_result()->fetch_assoc()['total'];
    $feedbackStmt->close();
    
    jsonResponse([
        'success' => true,
        'stats' => [
            'total_users' => (int)$totalUsers,
            'new_users_week' => (int)$newUsersWeek,
            'total_employers' => (int)$totalEmployers,
            'new_employers_week' => (int)$newEmployersWeek,
            'active_jobs' => (int)$activeJobs,
            'inactive_jobs' => (int)$inactiveJobs,
            'total_applications' => (int)$totalApplications,
            'new_applications_today' => (int)$newAppsToday,
            'pending_approvals' => (int)$pendingApprovals,
            'unread_feedback' => (int)$unreadFeedback
        ]
    ]);
    
} catch (Exception $e) {
    error_log('Admin stats error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Failed to fetch statistics'], 500);
}
?>