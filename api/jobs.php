<?php
require_once 'config.php';

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            getJobById($_GET['id']);
        } else {
            getJobs();
        }
        break;
        
    case 'POST':
        requireEmployer();
        createJob();
        break;
        
    case 'PUT':
        requireEmployer();
        updateJob();
        break;
        
    case 'DELETE':
        requireEmployer();
        deleteJob();
        break;
        
    default:
        jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

function getJobs() {
    global $db;
    
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
    $category = isset($_GET['category']) ? sanitizeInput($_GET['category']) : '';
    $location = isset($_GET['location']) ? sanitizeInput($_GET['location']) : '';
    $title = isset($_GET['title']) ? sanitizeInput($_GET['title']) : '';
    $jobType = isset($_GET['job_type']) ? sanitizeInput($_GET['job_type']) : '';
    
    $query = "SELECT j.*, c.category_name, 
              (SELECT COUNT(*) FROM applications WHERE job_id = j.id) as application_count
              FROM jobs j
              LEFT JOIN job_categories c ON j.category_id = c.id
              WHERE j.status = 'Active'";
    
    $params = [];
    $types = '';
    
    if ($category) {
        $query .= " AND c.category_name = ?";
        $params[] = $category;
        $types .= 's';
    }
    
    if ($location) {
        $query .= " AND (j.location LIKE ? OR j.city LIKE ?)";
        $searchLocation = "%$location%";
        $params[] = $searchLocation;
        $params[] = $searchLocation;
        $types .= 'ss';
    }
    
    if ($title) {
        $query .= " AND j.title LIKE ?";
        $searchTitle = "%$title%";
        $params[] = $searchTitle;
        $types .= 's';
    }
    
    if ($jobType) {
        $query .= " AND j.job_type = ?";
        $params[] = $jobType;
        $types .= 's';
    }
    
    $query .= " ORDER BY j.is_featured DESC, j.posted_date DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';
    
    $stmt = $db->prepare($query);
    
    if ($types) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $jobs = [];
    while ($row = $result->fetch_assoc()) {
        $jobs[] = $row;
    }
    
    $stmt->close();
    
    jsonResponse([
        'success' => true,
        'jobs' => $jobs,
        'count' => count($jobs)
    ]);
}

function getJobById($id) {
    global $db;
    
    $id = intval($id);
    
    // Increment view count
    $db->query("UPDATE jobs SET views = views + 1 WHERE id = $id");
    
    $query = "SELECT j.*, c.category_name, e.company_name, e.whatsapp_number, e.company_logo
              FROM jobs j
              LEFT JOIN job_categories c ON j.category_id = c.id
              LEFT JOIN employers e ON j.employer_id = e.id
              WHERE j.id = ?";
    
    $stmt = $db->prepare($query);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        jsonResponse(['success' => false, 'message' => 'Job not found'], 404);
    }
    
    $job = $result->fetch_assoc();
    $stmt->close();
    
    // Check if user has applied
    if (isAuthenticated()) {
        $userId = getUserId();
        $checkStmt = $db->prepare("SELECT id FROM applications WHERE job_id = ? AND user_id = ?");
        $checkStmt->bind_param('ii', $id, $userId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $job['has_applied'] = $checkResult->num_rows > 0;
        $checkStmt->close();
    }
    
    // Check if user has saved this job
    if (isAuthenticated()) {
        $userId = getUserId();
        $savedStmt = $db->prepare("SELECT id FROM saved_jobs WHERE job_id = ? AND user_id = ?");
        $savedStmt->bind_param('ii', $id, $userId);
        $savedStmt->execute();
        $savedResult = $savedStmt->get_result();
        $job['is_saved'] = $savedResult->num_rows > 0;
        $savedStmt->close();
    }
    
    jsonResponse([
        'success' => true,
        'job' => $job
    ]);
}

function createJob() {
    global $db;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $required = ['title', 'description', 'category_id', 'location', 'salary_min', 'salary_max'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            jsonResponse(['success' => false, 'message' => "Field $field is required"], 400);
        }
    }
    
    $employerId = getEmployerId();
    
    // Get employer company name
    $empStmt = $db->prepare("SELECT company_name FROM employers WHERE id = ?");
    $empStmt->bind_param('i', $employerId);
    $empStmt->execute();
    $empResult = $empStmt->get_result();
    $employer = $empResult->fetch_assoc();
    $companyName = $employer['company_name'];
    $empStmt->close();
    
    $query = "INSERT INTO jobs (employer_id, category_id, title, company_name, description, requirements, 
              responsibilities, job_type, experience_required, education_required, salary_min, salary_max, 
              salary_negotiable, location, city, state, work_timings, benefits, vacancies, contact_email, 
              contact_phone, whatsapp_number, application_deadline, status) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $db->prepare($query);
    
    $salaryNegotiable = isset($data['salary_negotiable']) ? 1 : 0;
    $status = isset($data['status']) ? $data['status'] : 'Active';
    
    $stmt->bind_param('iissssssssddississsssss',
        $employerId,
        $data['category_id'],
        $data['title'],
        $companyName,
        $data['description'],
        $data['requirements'] ?? '',
        $data['responsibilities'] ?? '',
        $data['job_type'] ?? 'Full-Time',
        $data['experience_required'] ?? 'Fresher',
        $data['education_required'] ?? '',
        $data['salary_min'],
        $data['salary_max'],
        $salaryNegotiable,
        $data['location'],
        $data['city'] ?? '',
        $data['state'] ?? '',
        $data['work_timings'] ?? '',
        $data['benefits'] ?? '',
        $data['vacancies'] ?? 1,
        $data['contact_email'] ?? '',
        $data['contact_phone'] ?? '',
        $data['whatsapp_number'] ?? '',
        $data['application_deadline'] ?? null,
        $status
    );
    
    if ($stmt->execute()) {
        $jobId = $db->lastInsertId();
        logActivity('job_created', "Job ID: $jobId", null, $employerId, null);
        
        jsonResponse([
            'success' => true,
            'message' => 'Job posted successfully',
            'job_id' => $jobId
        ], 201);
    } else {
        jsonResponse(['success' => false, 'message' => 'Failed to create job'], 500);
    }
    
    $stmt->close();
}

function updateJob() {
    global $db;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['id'])) {
        jsonResponse(['success' => false, 'message' => 'Job ID required'], 400);
    }
    
    $jobId = intval($data['id']);
    $employerId = getEmployerId();
    
    // Verify ownership
    $checkStmt = $db->prepare("SELECT id FROM jobs WHERE id = ? AND employer_id = ?");
    $checkStmt->bind_param('ii', $jobId, $employerId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        jsonResponse(['success' => false, 'message' => 'Job not found or unauthorized'], 403);
    }
    $checkStmt->close();
    
    $query = "UPDATE jobs SET 
              title = ?, description = ?, requirements = ?, responsibilities = ?,
              job_type = ?, experience_required = ?, education_required = ?,
              salary_min = ?, salary_max = ?, salary_negotiable = ?,
              location = ?, city = ?, state = ?, work_timings = ?, benefits = ?,
              vacancies = ?, contact_email = ?, contact_phone = ?, whatsapp_number = ?,
              application_deadline = ?, status = ?
              WHERE id = ?";
    
    $stmt = $db->prepare($query);
    
    $salaryNegotiable = isset($data['salary_negotiable']) ? 1 : 0;
    
    $stmt->bind_param('sssssssddississssssssi',
        $data['title'],
        $data['description'],
        $data['requirements'] ?? '',
        $data['responsibilities'] ?? '',
        $data['job_type'] ?? 'Full-Time',
        $data['experience_required'] ?? '',
        $data['education_required'] ?? '',
        $data['salary_min'],
        $data['salary_max'],
        $salaryNegotiable,
        $data['location'],
        $data['city'] ?? '',
        $data['state'] ?? '',
        $data['work_timings'] ?? '',
        $data['benefits'] ?? '',
        $data['vacancies'] ?? 1,
        $data['contact_email'] ?? '',
        $data['contact_phone'] ?? '',
        $data['whatsapp_number'] ?? '',
        $data['application_deadline'] ?? null,
        $data['status'] ?? 'Active',
        $jobId
    );
    
    if ($stmt->execute()) {
        logActivity('job_updated', "Job ID: $jobId", null, $employerId, null);
        jsonResponse(['success' => true, 'message' => 'Job updated successfully']);
    } else {
        jsonResponse(['success' => false, 'message' => 'Failed to update job'], 500);
    }
    
    $stmt->close();
}

function deleteJob() {
    global $db;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['id'])) {
        jsonResponse(['success' => false, 'message' => 'Job ID required'], 400);
    }
    
    $jobId = intval($data['id']);
    $employerId = getEmployerId();
    
    // Verify ownership
    $stmt = $db->prepare("DELETE FROM jobs WHERE id = ? AND employer_id = ?");
    $stmt->bind_param('ii', $jobId, $employerId);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        logActivity('job_deleted', "Job ID: $jobId", null, $employerId, null);
        jsonResponse(['success' => true, 'message' => 'Job deleted successfully']);
    } else {
        jsonResponse(['success' => false, 'message' => 'Job not found or unauthorized'], 403);
    }
    
    $stmt->close();
}
?>