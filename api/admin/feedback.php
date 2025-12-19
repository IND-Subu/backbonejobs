<?php
require_once '../config.php';

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

// For GET and POST, authentication is optional (public feedback submission)
// For PUT and DELETE, require admin
if (in_array($method, ['PUT', 'DELETE'])) {
    requireAdmin();
}

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            requireAdmin();
            getFeedbackById($_GET['id']);
        } else {
            requireAdmin();
            getAllFeedback();
        }
        break;
    
    case 'POST':
        submitFeedback();
        break;
    
    case 'PUT':
        if (isset($_GET['action']) && $_GET['action'] === 'mark_all_read') {
            markAllAsRead();
        } else {
            updateFeedback();
        }
        break;
    
    case 'DELETE':
        deleteFeedback();
        break;
    
    default:
        jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

/**
 * Get all feedback (Admin only)
 */
function getAllFeedback() {
    global $db;
    
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
    $status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
    $type = isset($_GET['type']) ? sanitizeInput($_GET['type']) : '';
    
    $query = "SELECT * FROM feedback WHERE 1=1";
    $params = [];
    $types = '';
    
    if ($status) {
        $query .= " AND status = ?";
        $params[] = $status;
        $types .= 's';
    }
    
    if ($type) {
        $query .= " AND feedback_type = ?";
        $params[] = $type;
        $types .= 's';
    }
    
    $query .= " ORDER BY 
                CASE status 
                    WHEN 'unread' THEN 1 
                    WHEN 'read' THEN 2 
                    WHEN 'resolved' THEN 3 
                END,
                created_at DESC 
                LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';
    
    $stmt = $db->prepare($query);
    
    if ($types) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $feedback = [];
    while ($row = $result->fetch_assoc()) {
        $feedback[] = $row;
    }
    
    $stmt->close();
    
    jsonResponse([
        'success' => true,
        'feedback' => $feedback,
        'count' => count($feedback)
    ]);
}

/**
 * Get single feedback by ID (Admin only)
 */
function getFeedbackById($id) {
    global $db;
    
    $id = intval($id);
    
    $stmt = $db->prepare("SELECT * FROM feedback WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        jsonResponse(['success' => false, 'message' => 'Feedback not found'], 404);
    }
    
    $feedback = $result->fetch_assoc();
    $stmt->close();
    
    jsonResponse([
        'success' => true,
        'feedback' => $feedback
    ]);
}

/**
 * Submit new feedback (Public)
 */
function submitFeedback() {
    global $db;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (!isset($data['message']) || empty($data['message'])) {
        jsonResponse(['success' => false, 'message' => 'Message is required'], 400);
    }
    
    $name = isset($data['name']) ? sanitizeInput($data['name']) : null;
    $email = isset($data['email']) ? sanitizeInput($data['email']) : null;
    $phone = isset($data['phone']) ? sanitizeInput($data['phone']) : null;
    $message = sanitizeInput($data['message']);
    $feedbackType = isset($data['feedback_type']) ? sanitizeInput($data['feedback_type']) : 'general';
    
    // Validate feedback type
    $validTypes = ['bug', 'feature', 'general', 'complaint', 'suggestion'];
    if (!in_array($feedbackType, $validTypes)) {
        $feedbackType = 'general';
    }
    
    // Validate email if provided
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['success' => false, 'message' => 'Invalid email address'], 400);
    }
    
    // Get user ID if authenticated
    $userId = null;
    $employerId = null;
    
    if (isAuthenticated()) {
        $userType = getUserType();
        if ($userType === 'jobseeker') {
            $userId = getUserId();
        } elseif ($userType === 'employer') {
            $employerId = getEmployerId();
        }
    }
    
    // Insert feedback
    $stmt = $db->prepare("INSERT INTO feedback 
                          (user_id, employer_id, name, email, phone, message, feedback_type, status) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, 'unread')");
    
    $stmt->bind_param('iisssss', $userId, $employerId, $name, $email, $phone, $message, $feedbackType);
    
    if ($stmt->execute()) {
        $feedbackId = $db->lastInsertId();
        
        // Log activity
        logActivity('feedback_submitted', "Feedback ID: $feedbackId", $userId, $employerId, null);
        
        jsonResponse([
            'success' => true,
            'message' => 'Thank you for your feedback!',
            'feedback_id' => $feedbackId
        ], 201);
    } else {
        jsonResponse(['success' => false, 'message' => 'Failed to submit feedback'], 500);
    }
    
    $stmt->close();
}

/**
 * Update feedback (Admin only)
 */
function updateFeedback() {
    global $db;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['id'])) {
        jsonResponse(['success' => false, 'message' => 'Feedback ID is required'], 400);
    }
    
    $feedbackId = intval($data['id']);
    
    // Check if feedback exists
    $checkStmt = $db->prepare("SELECT id FROM feedback WHERE id = ?");
    $checkStmt->bind_param('i', $feedbackId);
    $checkStmt->execute();
    
    if ($checkStmt->get_result()->num_rows === 0) {
        jsonResponse(['success' => false, 'message' => 'Feedback not found'], 404);
    }
    $checkStmt->close();
    
    // Build update query
    $updateFields = [];
    $params = [];
    $types = '';
    
    if (isset($data['status'])) {
        $validStatuses = ['unread', 'read', 'resolved'];
        if (in_array($data['status'], $validStatuses)) {
            $updateFields[] = "status = ?";
            $params[] = $data['status'];
            $types .= 's';
        }
    }
    
    if (isset($data['admin_response'])) {
        $updateFields[] = "admin_response = ?";
        $params[] = sanitizeInput($data['admin_response']);
        $types .= 's';
        
        $updateFields[] = "responded_at = NOW()";
    }
    
    if (empty($updateFields)) {
        jsonResponse(['success' => false, 'message' => 'No fields to update'], 400);
    }
    
    $params[] = $feedbackId;
    $types .= 'i';
    
    $query = "UPDATE feedback SET " . implode(', ', $updateFields) . " WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        // If send_email is requested and email exists, send notification
        if (isset($data['send_email']) && $data['send_email'] && isset($data['admin_response'])) {
            // Get feedback details
            $fbStmt = $db->prepare("SELECT email, name FROM feedback WHERE id = ?");
            $fbStmt->bind_param('i', $feedbackId);
            $fbStmt->execute();
            $fbResult = $fbStmt->get_result();
            
            if ($fbResult->num_rows > 0) {
                $feedback = $fbResult->fetch_assoc();
                if ($feedback['email']) {
                    // TODO: Send email notification
                    // sendEmail($feedback['email'], 'Response to Your Feedback', $data['admin_response']);
                }
            }
            $fbStmt->close();
        }
        
        logActivity('feedback_updated', "Feedback ID: $feedbackId", null, null, getUserId());
        
        jsonResponse([
            'success' => true,
            'message' => 'Feedback updated successfully'
        ]);
    } else {
        jsonResponse(['success' => false, 'message' => 'Failed to update feedback'], 500);
    }
    
    $stmt->close();
}

/**
 * Mark all feedback as read (Admin only)
 */
function markAllAsRead() {
    global $db;
    
    $stmt = $db->prepare("UPDATE feedback SET status = 'read' WHERE status = 'unread'");
    
    if ($stmt->execute()) {
        $affectedRows = $stmt->affected_rows;
        
        logActivity('feedback_bulk_update', "Marked $affectedRows feedback as read", null, null, getUserId());
        
        jsonResponse([
            'success' => true,
            'message' => "$affectedRows feedback marked as read"
        ]);
    } else {
        jsonResponse(['success' => false, 'message' => 'Failed to update feedback'], 500);
    }
    
    $stmt->close();
}

/**
 * Delete feedback (Admin only)
 */
function deleteFeedback() {
    global $db;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['id'])) {
        jsonResponse(['success' => false, 'message' => 'Feedback ID is required'], 400);
    }
    
    $feedbackId = intval($data['id']);
    
    $stmt = $db->prepare("DELETE FROM feedback WHERE id = ?");
    $stmt->bind_param('i', $feedbackId);
    
    if ($stmt->execute()) {
        logActivity('feedback_deleted', "Feedback ID: $feedbackId", null, null, getUserId());
        
        jsonResponse([
            'success' => true,
            'message' => 'Feedback deleted successfully'
        ]);
    } else {
        jsonResponse(['success' => false, 'message' => 'Failed to delete feedback'], 500);
    }
    
    $stmt->close();
}
?>