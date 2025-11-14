<?php
require_once 'config.php';

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['stats'])) {
            requireAuth();
            getApplicationStats();
        } else if (isAuthenticated()) {
            getUserApplications();
        } else if (isEmployer()) {
            getEmployerApplications();
        } else {
            jsonResponse(['success' => false, 'message' => 'Authentication required'], 401);
        }
        break;
        
    case 'POST':
        requireAuth();
        submitApplication();
        break;
        
    case 'PUT':
        requireEmployer();
        updateApplicationStatus();
        break;
        
    default:
        jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

function getUserApplications() {
    global $db;
    
    $userId = getUserId();
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
    
    $query = "SELECT a.*, j.title as job_title, j.company_name, j.location, j.job_type
              FROM applications a
              INNER JOIN jobs j ON a.job_id = j.id
              WHERE a.user_id = ?
              ORDER BY a.applied_date DESC
              LIMIT ? OFFSET ?";
    
    $stmt = $db->prepare($query);
    $stmt->bind_param('iii', $userId, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $applications = [];
    while ($row = $result->fetch_assoc()) {
        $applications[] = $row;
    }
    
    $stmt->close();
    
    jsonResponse([
        'success' => true,
        'applications' => $applications,
        'count' => count($applications)
    ]);
}

function getEmployerApplications() {
    global $db;
    
    $employerId = getEmployerId();
    $jobId = isset($_GET['job_id']) ? intval($_GET['job_id']) : null;
    $status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
    
    $query = "SELECT a.*, u.name as applicant_name, u.email as applicant_email, 
              u.phone as applicant_phone, j.title as job_title
              FROM applications a
              INNER JOIN users u ON a.user_id = u.id
              INNER JOIN jobs j ON a.job_id = j.id
              WHERE a.employer_id = ?";
    
    $params = [$employerId];
    $types = 'i';
    
    if ($jobId) {
        $query .= " AND a.job_id = ?";
        $params[] = $jobId;
        $types .= 'i';
    }
    
    if ($status) {
        $query .= " AND a.status = ?";
        $params[] = $status;
        $types .= 's';
    }
    
    $query .= " ORDER BY a.applied_date DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $applications = [];
    while ($row = $result->fetch_assoc()) {
        $applications[] = $row;
    }
    
    $stmt->close();
    
    jsonResponse([
        'success' => true,
        'applications' => $applications,
        'count' => count($applications)
    ]);
}

function submitApplication() {
    global $db;
    
    $userId = getUserId();
    
    // Validate required fields
    if (!isset($_POST['job_id'])) {
        jsonResponse(['success' => false, 'message' => 'Job ID is required'], 400);
    }
    
    $jobId = intval($_POST['job_id']);
    
    // Check if job exists and is active
    $jobStmt = $db->prepare("SELECT employer_id, status FROM jobs WHERE id = ?");
    $jobStmt->bind_param('i', $jobId);
    $jobStmt->execute();
    $jobResult = $jobStmt->get_result();
    
    if ($jobResult->num_rows === 0) {
        jsonResponse(['success' => false, 'message' => 'Job not found'], 404);
    }
    
    $job = $jobResult->fetch_assoc();
    $jobStmt->close();
    
    if ($job['status'] !== 'Active') {
        jsonResponse(['success' => false, 'message' => 'This job is no longer accepting applications'], 400);
    }
    
    // Check if already applied
    $checkStmt = $db->prepare("SELECT id FROM applications WHERE job_id = ? AND user_id = ?");
    $checkStmt->bind_param('ii', $jobId, $userId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        jsonResponse(['success' => false, 'message' => 'You have already applied for this job'], 400);
    }
    $checkStmt->close();
    
    // Handle resume upload
    $resumePath = null;
    if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
        $uploadResult = uploadFile($_FILES['resume'], ['pdf', 'doc', 'docx']);
        if ($uploadResult['success']) {
            $resumePath = $uploadResult['filename'];
        } else {
            jsonResponse(['success' => false, 'message' => $uploadResult['message']], 400);
        }
    }
    
    // Insert application
    $query = "INSERT INTO applications (job_id, user_id, employer_id, cover_letter, resume_path, 
              expected_salary, available_from, status) 
              VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')";
    
    $stmt = $db->prepare($query);
    
    $coverLetter = isset($_POST['cover_letter']) ? sanitizeInput($_POST['cover_letter']) : '';
    $expectedSalary = isset($_POST['expected_salary']) ? floatval($_POST['expected_salary']) : null;
    $availableFrom = isset($_POST['available_from']) ? $_POST['available_from'] : null;
    
    $stmt->bind_param('iiissds',
        $jobId,
        $userId,
        $job['employer_id'],
        $coverLetter,
        $resumePath,
        $expectedSalary,
        $availableFrom
    );
    
    if ($stmt->execute()) {
        $applicationId = $db->lastInsertId();
        
        // Log activity
        logActivity('application_submitted', "Application ID: $applicationId", $userId, null, null);
        
        // Send notification to employer
        sendNotification(null, $job['employer_id'], 'Application', 'New Job Application', 
                        'You have received a new application', "/application-details.php?id=$applicationId");
        
        jsonResponse([
            'success' => true,
            'message' => 'Application submitted successfully',
            'application_id' => $applicationId
        ], 201);
    } else {
        // Delete uploaded file if application failed
        if ($resumePath) {
            deleteFile($resumePath);
        }
        jsonResponse(['success' => false, 'message' => 'Failed to submit application'], 500);
    }
    
    $stmt->close();
}

function updateApplicationStatus() {
    global $db;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['application_id']) || !isset($data['status'])) {
        jsonResponse(['success' => false, 'message' => 'Application ID and status are required'], 400);
    }
    
    $applicationId = intval($data['application_id']);
    $status = sanitizeInput($data['status']);
    $notes = isset($data['notes']) ? sanitizeInput($data['notes']) : '';
    $employerId = getEmployerId();
    
    // Validate status
    $validStatuses = ['Pending', 'Reviewed', 'Shortlisted', 'Rejected', 'Hired'];
    if (!in_array($status, $validStatuses)) {
        jsonResponse(['success' => false, 'message' => 'Invalid status'], 400);
    }
    
    // Verify ownership
    $checkStmt = $db->prepare("SELECT user_id FROM applications WHERE id = ? AND employer_id = ?");
    $checkStmt->bind_param('ii', $applicationId, $employerId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        jsonResponse(['success' => false, 'message' => 'Application not found or unauthorized'], 403);
    }
    
    $application = $checkResult->fetch_assoc();
    $checkStmt->close();
    
    // Update application
    $query = "UPDATE applications SET status = ?, notes = ?, reviewed_date = NOW() WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param('ssi', $status, $notes, $applicationId);
    
    if ($stmt->execute()) {
        // Log activity
        logActivity('application_status_updated', "Application ID: $applicationId, Status: $status", null, $employerId, null);
        
        // Send notification to applicant
        sendNotification($application['user_id'], null, 'Status Update', 'Application Status Updated', 
                        "Your application status has been updated to: $status", "/application-details.php?id=$applicationId");
        
        jsonResponse([
            'success' => true,
            'message' => 'Application status updated successfully'
        ]);
    } else {
        jsonResponse(['success' => false, 'message' => 'Failed to update application status'], 500);
    }
    
    $stmt->close();
}

function getApplicationStats() {
    global $db;
    
    $userId = getUserId();
    
    $query = "SELECT 
              COUNT(*) as total,
              SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
              SUM(CASE WHEN status = 'Reviewed' THEN 1 ELSE 0 END) as reviewed,
              SUM(CASE WHEN status = 'Shortlisted' THEN 1 ELSE 0 END) as shortlisted,
              SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected,
              SUM(CASE WHEN status = 'Hired' THEN 1 ELSE 0 END) as hired
              FROM applications
              WHERE user_id = ?";
    
    $stmt = $db->prepare($query);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats = $result->fetch_assoc();
    $stmt->close();
    
    jsonResponse([
        'success' => true,
        'stats' => $stats
    ]);
}
?>