<?php
require_once '../config.php';

requireAdmin();

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    getPendingApprovals();
} elseif ($method === 'PUT') {
    updateApprovalStatus();
} else {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

/**
 * Get pending employer approvals
 */
function getPendingApprovals() {
    global $db;
    
    // Get pending employers (unverified and active)
    $pendingQuery = "SELECT * FROM employers 
                     WHERE is_verified = 0 AND is_active = 1 
                     ORDER BY created_at DESC";
    $pendingResult = $db->query($pendingQuery);
    $pendingEmployers = [];
    
    while ($row = $pendingResult->fetch_assoc()) {
        $pendingEmployers[] = $row;
    }
    
    // Get today's stats
    $today = date('Y-m-d');
    
    // Approved today (from activity logs)
    $approvedStmt = $db->prepare("SELECT COUNT(*) as count FROM activity_log 
                                   WHERE activity_type = 'employer_approved' 
                                   AND DATE(created_at) = ?");
    $approvedStmt->bind_param('s', $today);
    $approvedStmt->execute();
    $approvedToday = $approvedStmt->get_result()->fetch_assoc()['count'];
    $approvedStmt->close();
    
    // Rejected today
    $rejectedStmt = $db->prepare("SELECT COUNT(*) as count FROM activity_log 
                                   WHERE activity_type = 'employer_rejected' 
                                   AND DATE(created_at) = ?");
    $rejectedStmt->bind_param('s', $today);
    $rejectedStmt->execute();
    $rejectedToday = $rejectedStmt->get_result()->fetch_assoc()['count'];
    $rejectedStmt->close();
    
    jsonResponse([
        'success' => true,
        'pending_employers' => $pendingEmployers,
        'stats' => [
            'pending' => count($pendingEmployers),
            'approved_today' => (int)$approvedToday,
            'rejected_today' => (int)$rejectedToday
        ]
    ]);
}

/**
 * Update approval status
 */
function updateApprovalStatus() {
    global $db;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['employer_id']) || !isset($data['action'])) {
        jsonResponse(['success' => false, 'message' => 'Employer ID and action are required'], 400);
    }
    
    $employerId = intval($data['employer_id']);
    $action = sanitizeInput($data['action']);
    
    // Validate action
    if (!in_array($action, ['approve', 'reject'])) {
        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
    }
    
    // Check if employer exists
    $checkStmt = $db->prepare("SELECT company_name FROM employers WHERE id = ?");
    $checkStmt->bind_param('i', $employerId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        jsonResponse(['success' => false, 'message' => 'Employer not found'], 404);
    }
    
    $employer = $checkResult->fetch_assoc();
    $companyName = $employer['company_name'];
    $checkStmt->close();
    
    if ($action === 'approve') {
        // Approve employer
        $stmt = $db->prepare("UPDATE employers SET is_verified = 1 WHERE id = ?");
        $stmt->bind_param('i', $employerId);
        
        if ($stmt->execute()) {
            logActivity('employer_approved', "Employer: $companyName (ID: $employerId) approved", null, null, getAdminId());
            
            // Send notification to employer (optional)
            sendNotification(null, $employerId, 'Verification', 'Account Verified', 
                           'Congratulations! Your employer account has been verified.', '/employer-dashboard.html');
            
            jsonResponse([
                'success' => true,
                'message' => 'Employer approved successfully'
            ]);
        } else {
            jsonResponse(['success' => false, 'message' => 'Failed to approve employer'], 500);
        }
        
        $stmt->close();
        
    } else {
        // Reject employer (deactivate account)
        $stmt = $db->prepare("UPDATE employers SET is_active = 0, is_verified = 0 WHERE id = ?");
        $stmt->bind_param('i', $employerId);
        
        if ($stmt->execute()) {
            logActivity('employer_rejected', "Employer: $companyName (ID: $employerId) rejected", null, null, getAdminId());
            
            // Send notification to employer (optional)
            sendNotification(null, $employerId, 'Verification', 'Account Verification Failed', 
                           'Your employer account verification was not approved. Please contact support for more information.', null);
            
            jsonResponse([
                'success' => true,
                'message' => 'Employer rejected'
            ]);
        } else {
            jsonResponse(['success' => false, 'message' => 'Failed to reject employer'], 500);
        }
        
        $stmt->close();
    }
}
?>