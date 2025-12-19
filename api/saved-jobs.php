
<?php
require_once 'config.php';

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

// Require authentication for all operations
requireAuth();

$userId = getUserId();

switch ($method) {
    case 'GET':
        getSavedJobs();
        break;
        
    case 'POST':
        toggleSaveJob();
        break;
        
    case 'DELETE':
        unsaveJob();
        break;
        
    default:
        jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

function getSavedJobs() {
    global $db, $userId;
    
    $query = "SELECT sj.*, j.title, j.company_name, j.location, j.job_type, 
              j.salary_min, j.salary_max, j.posted_date, j.status,
              c.category_name
              FROM saved_jobs sj
              INNER JOIN jobs j ON sj.job_id = j.id
              LEFT JOIN job_categories c ON j.category_id = c.id
              WHERE sj.user_id = ?
              ORDER BY sj.saved_date DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $savedJobs = [];
    while ($row = $result->fetch_assoc()) {
        $savedJobs[] = $row;
    }
    
    $stmt->close();
    
    jsonResponse([
        'success' => true,
        'saved_jobs' => $savedJobs,
        'count' => count($savedJobs)
    ]);
}

function toggleSaveJob() {
    global $db, $userId;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['job_id'])) {
        jsonResponse(['success' => false, 'message' => 'Job ID is required'], 400);
    }
    
    $jobId = intval($data['job_id']);
    
    // Check if job exists
    $jobStmt = $db->prepare("SELECT id FROM jobs WHERE id = ?");
    $jobStmt->bind_param('i', $jobId);
    $jobStmt->execute();
    $jobResult = $jobStmt->get_result();
    
    if ($jobResult->num_rows === 0) {
        jsonResponse(['success' => false, 'message' => 'Job not found'], 404);
    }
    $jobStmt->close();
    
    // Check if already saved
    $checkStmt = $db->prepare("SELECT id FROM saved_jobs WHERE job_id = ? AND user_id = ?");
    $checkStmt->bind_param('ii', $jobId, $userId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        // Already saved, so remove it
        $checkStmt->close();
        
        $deleteStmt = $db->prepare("DELETE FROM saved_jobs WHERE job_id = ? AND user_id = ?");
        $deleteStmt->bind_param('ii', $jobId, $userId);
        
        if ($deleteStmt->execute()) {
            logActivity('job_unsaved', "Job ID: $jobId", $userId, null, null);
            
            jsonResponse([
                'success' => true,
                'message' => 'Job removed from saved',
                'saved' => false
            ]);
        } else {
            jsonResponse(['success' => false, 'message' => 'Failed to unsave job'], 500);
        }
        
        $deleteStmt->close();
    } else {
        // Not saved, so add it
        $checkStmt->close();
        
        $insertStmt = $db->prepare("INSERT INTO saved_jobs (user_id, job_id) VALUES (?, ?)");
        $insertStmt->bind_param('ii', $userId, $jobId);
        
        if ($insertStmt->execute()) {
            logActivity('job_saved', "Job ID: $jobId", $userId, null, null);
            
            jsonResponse([
                'success' => true,
                'message' => 'Job saved successfully',
                'saved' => true
            ], 201);
        } else {
            jsonResponse(['success' => false, 'message' => 'Failed to save job'], 500);
        }
        
        $insertStmt->close();
    }
}

function unsaveJob() {
    global $db, $userId;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['job_id'])) {
        jsonResponse(['success' => false, 'message' => 'Job ID is required'], 400);
    }
    
    $jobId = intval($data['job_id']);
    
    $stmt = $db->prepare("DELETE FROM saved_jobs WHERE job_id = ? AND user_id = ?");
    $stmt->bind_param('ii', $jobId, $userId);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            logActivity('job_unsaved', "Job ID: $jobId", $userId, null, null);
            
            jsonResponse([
                'success' => true,
                'message' => 'Job removed from saved'
            ]);
        } else {
            jsonResponse(['success' => false, 'message' => 'Job was not saved'], 404);
        }
    } else {
        jsonResponse(['success' => false, 'message' => 'Failed to unsave job'], 500);
    }
    
    $stmt->close();
}
?>