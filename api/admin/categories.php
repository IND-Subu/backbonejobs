<?php
require_once '../config.php';

requireAdminAndEmployer();

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        getCategories();
        break;
    
    case 'POST':
        createCategory();
        break;
    
    case 'PUT':
        updateCategory();
        break;
    
    case 'DELETE':
        deleteCategory();
        break;
    
    default:
        jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

/**
 * Get all categories with job counts
 */
function getCategories() {
    global $db;
    
    $query = "SELECT c.*, 
              (SELECT COUNT(*) FROM jobs WHERE category_id = c.id) as job_count
              FROM job_categories c
              ORDER BY c.category_name ASC";
    
    $result = $db->query($query);
    $categories = [];
    
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    
    jsonResponse([
        'success' => true,
        'categories' => $categories,
        'count' => count($categories)
    ]);
}

/**
 * Create new category
 */
function createCategory() {
    global $db;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['category_name']) || empty($data['category_name'])) {
        jsonResponse(['success' => false, 'message' => 'Category name is required'], 400);
    }
    
    $categoryName = sanitizeInput($data['category_name']);
    $description = isset($data['description']) ? sanitizeInput($data['description']) : '';
    $icon = isset($data['icon']) ? sanitizeInput($data['icon']) : '';
    $isActive = isset($data['is_active']) ? intval($data['is_active']) : 1;
    
    // Check if category already exists
    $checkStmt = $db->prepare("SELECT id FROM job_categories WHERE category_name = ?");
    $checkStmt->bind_param('s', $categoryName);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        jsonResponse(['success' => false, 'message' => 'Category already exists'], 400);
    }
    $checkStmt->close();
    
    // Insert category
    $stmt = $db->prepare("INSERT INTO job_categories (category_name, description, icon, is_active) 
                          VALUES (?, ?, ?, ?)");
    $stmt->bind_param('sssi', $categoryName, $description, $icon, $isActive);
    
    if ($stmt->execute()) {
        $categoryId = $db->lastInsertId();
        
        logActivity('category_created', "Category: $categoryName", null, null, getUserId());
        
        jsonResponse([
            'success' => true,
            'message' => 'Category created successfully',
            'category_id' => $categoryId
        ], 201);
    } else {
        jsonResponse(['success' => false, 'message' => 'Failed to create category'], 500);
    }
    
    $stmt->close();
}

/**
 * Update category
 */
function updateCategory() {
    global $db;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['id'])) {
        jsonResponse(['success' => false, 'message' => 'Category ID is required'], 400);
    }
    
    $categoryId = intval($data['id']);
    
    // Check if category exists
    $checkStmt = $db->prepare("SELECT id FROM job_categories WHERE id = ?");
    $checkStmt->bind_param('i', $categoryId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        jsonResponse(['success' => false, 'message' => 'Category not found'], 404);
    }
    $checkStmt->close();
    
    // Build update query
    $updateFields = [];
    $params = [];
    $types = '';
    
    $allowedFields = [
        'category_name' => 's',
        'description' => 's',
        'icon' => 's',
        'is_active' => 'i'
    ];
    
    foreach ($allowedFields as $field => $type) {
        if (isset($data[$field])) {
            $updateFields[] = "$field = ?";
            $params[] = $data[$field];
            $types .= $type;
        }
    }
    
    if (empty($updateFields)) {
        jsonResponse(['success' => false, 'message' => 'No fields to update'], 400);
    }
    
    $params[] = $categoryId;
    $types .= 'i';
    
    $query = "UPDATE job_categories SET " . implode(', ', $updateFields) . " WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        logActivity('category_updated', "Category ID: $categoryId", null, null, getUserId());
        
        jsonResponse([
            'success' => true,
            'message' => 'Category updated successfully'
        ]);
    } else {
        jsonResponse(['success' => false, 'message' => 'Failed to update category'], 500);
    }
    
    $stmt->close();
}

/**
 * Delete category
 */
function deleteCategory() {
    global $db;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['id'])) {
        jsonResponse(['success' => false, 'message' => 'Category ID is required'], 400);
    }
    
    $categoryId = intval($data['id']);
    
    // Check if category exists
    $checkStmt = $db->prepare("SELECT category_name FROM job_categories WHERE id = ?");
    $checkStmt->bind_param('i', $categoryId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        jsonResponse(['success' => false, 'message' => 'Category not found'], 404);
    }
    
    $category = $checkResult->fetch_assoc();
    $categoryName = $category['category_name'];
    $checkStmt->close();
    
    // Check if category has jobs
    $jobsStmt = $db->prepare("SELECT COUNT(*) as count FROM jobs WHERE category_id = ?");
    $jobsStmt->bind_param('i', $categoryId);
    $jobsStmt->execute();
    $jobsResult = $jobsStmt->get_result();
    $jobCount = $jobsResult->fetch_assoc()['count'];
    $jobsStmt->close();
    
    if ($jobCount > 0) {
        jsonResponse([
            'success' => false,
            'message' => "Cannot delete category with $jobCount associated job(s). Please reassign jobs first."
        ], 400);
    }
    
    // Delete category
    $stmt = $db->prepare("DELETE FROM job_categories WHERE id = ?");
    $stmt->bind_param('i', $categoryId);
    
    if ($stmt->execute()) {
        logActivity('category_deleted', "Category: $categoryName", null, null, getUserId());
        
        jsonResponse([
            'success' => true,
            'message' => 'Category deleted successfully'
        ]);
    } else {
        jsonResponse(['success' => false, 'message' => 'Failed to delete category'], 500);
    }
    
    $stmt->close();
}
?>