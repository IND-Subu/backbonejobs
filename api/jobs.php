<?php
require_once 'config.php';
require_once __DIR__.'/indexing/google-indexing-api.php';

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

// Handle different HTTP methods
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

/**
 * Get list of jobs with filters
 */
function getJobs() {
    global $db;

    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

    $category = isset($_GET['category']) ? sanitizeInput($_GET['category']) : '';
    $location = isset($_GET['location']) ? sanitizeInput($_GET['location']) : '';
    $title = isset($_GET['title']) ? sanitizeInput($_GET['title']) : '';
    $jobType = isset($_GET['job_type']) ? sanitizeInput($_GET['job_type']) : '';
    $categoryId = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;

    // 🔐 Automatically detect logged-in employer
    $isEmployer = isEmployer();
    $employerId = $isEmployer ? getEmployerId() : 0;

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

    if ($categoryId > 0) {
        $query .= " AND j.category_id = ?";
        $params[] = $categoryId;
        $types .= 'i';
    }

    if ($location) {
        $query .= " AND (j.location LIKE ? OR j.city LIKE ? OR j.state LIKE ?)";
        $searchLocation = "%$location%";
        $params[] = $searchLocation;
        $params[] = $searchLocation;
        $params[] = $searchLocation;
        $types .= 'sss';
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

    // 🔐 Secure employer filter
    if ($isEmployer) {
        $query .= " AND j.employer_id = ?";
        $params[] = $employerId;
        $types .= 'i';
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


/**
 * Get single job by ID
 */
function getJobById($id) {
    global $db;

    $id = intval($id);

    $updateStmt = $db->prepare("UPDATE jobs SET views = views + 1 WHERE id = ?");
    $updateStmt->bind_param('i', $id);
    $updateStmt->execute();
    $updateStmt->close();

    $query = "SELECT j.*, c.category_name, e.company_name, e.whatsapp_number,
              e.company_logo, e.company_description, e.is_verified
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

    $job['has_applied'] = false;
    if (isAuthenticated()) {
        $userId = getUserId();
        $checkStmt = $db->prepare("SELECT id FROM applications WHERE job_id = ? AND user_id = ?");
        $checkStmt->bind_param('ii', $id, $userId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $job['has_applied'] = $checkResult->num_rows > 0;
        $checkStmt->close();
    }

    $job['is_saved'] = false;
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

/**
 * Create new job (fix included here)
 */
function createJob() {
    global $db;

    $data = json_decode(file_get_contents('php://input'), true);

    $required = ['title', 'description', 'category_id', 'location', 'salary_min', 'salary_max'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            jsonResponse(['success' => false, 'message' => "Field '$field' is required"], 400);
        }
    }

    $employerId = getEmployerId();

    $empStmt = $db->prepare("SELECT company_name, is_verified FROM employers WHERE id = ? AND is_active = 1");
    $empStmt->bind_param('i', $employerId);
    $empStmt->execute();
    $empResult = $empStmt->get_result();

    if ($empResult->num_rows === 0) {
        jsonResponse(['success' => false, 'message' => 'Employer account not found or inactive'], 403);
    }

    $employer = $empResult->fetch_assoc();
    $companyName = $employer['company_name'];
    $empStmt->close();

    $categoryId = intval($data['category_id']);
    $catStmt = $db->prepare("SELECT id FROM job_categories WHERE id = ?");
    $catStmt->bind_param('i', $categoryId);
    $catStmt->execute();
    $catResult = $catStmt->get_result();

    if ($catResult->num_rows === 0) {
        jsonResponse(['success' => false, 'message' => 'Invalid category'], 400);
    }
    $catStmt->close();

    $salaryMin = floatval($data['salary_min']);
    $salaryMax = floatval($data['salary_max']);

    if ($salaryMin > $salaryMax) {
        jsonResponse(['success' => false, 'message' => 'Minimum salary cannot exceed maximum salary'], 400);
    }

    if ($salaryMin < 0 || $salaryMax < 0) {
        jsonResponse(['success' => false, 'message' => 'Salary cannot be negative'], 400);
    }

    // --- FIX: Extract ALL values into variables ---
    $title               = $data['title'];
    $description         = $data['description'];
    $requirements        = $data['requirements'] ?? '';
    $responsibilities    = $data['responsibilities'] ?? '';
    $jobType             = $data['job_type'] ?? 'Full-Time';
    $experienceRequired  = $data['experience_required'] ?? 'Any';
    $educationRequired   = $data['education_required'] ?? '';
    $salaryNegotiable    = isset($data['salary_negotiable']) ? intval($data['salary_negotiable']) : 0;
    $location            = $data['location'];
    $city                = $data['city'] ?? '';
    $state               = $data['state'] ?? '';
    $workTimings         = $data['work_timings'] ?? '';
    $benefits            = $data['benefits'] ?? '';
    $vacancies           = intval($data['vacancies'] ?? 1);
    $contactEmail        = $data['contact_email'] ?? '';
    $contactPhone        = $data['contact_phone'] ?? '';
    $whatsappNumber      = $data['whatsapp_number'] ?? '';
    $applicationDeadline = $data['application_deadline'] ?? null;
    $status              = isset($data['status']) ? sanitizeInput($data['status']) : 'Active';

    $query = "INSERT INTO jobs (
        employer_id, category_id, title, company_name, description,
        requirements, responsibilities, job_type, experience_required,
        education_required, salary_min, salary_max, salary_negotiable,
        location, city, state, work_timings, benefits, vacancies,
        contact_email, contact_phone, whatsapp_number, application_deadline, status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $db->prepare($query);

    // Bind using extracted variables ONLY
    $stmt->bind_param(
        "iissssssssddississssssss",
        $employerId,
        $categoryId,
        $title,
        $companyName,
        $description,
        $requirements,
        $responsibilities,
        $jobType,
        $experienceRequired,
        $educationRequired,
        $salaryMin,
        $salaryMax,
        $salaryNegotiable,
        $location,
        $city,
        $state,
        $workTimings,
        $benefits,
        $vacancies,
        $contactEmail,
        $contactPhone,
        $whatsappNumber,
        $applicationDeadline,
        $status
    );

    if ($stmt->execute()) {
        $jobId = $db->lastInsertId();
        logActivity('job_created', "Job ID: $jobId - $title", null, $employerId, null);

        // AUTO-TRIGGER GOOGLE INDEXING
        ob_start();
        submitJobForIndexing($jobId);
        ob_end_clean();
    
        jsonResponse([
            'success' => true,
            'message' => 'Job posted successfully',
            'job_id' => $jobId
        ], 201);
    } else {
        jsonResponse(['success' => false, 'message' => $stmt->error], 500);
    }

    $stmt->close();
}

/**
 * Update job
 */
function updateJob() {
    global $db;

    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['id'])) {
        jsonResponse(['success' => false, 'message' => 'Job ID is required'], 400);
    }

    $jobId = intval($data['id']);
    $employerId = getEmployerId();

    $checkStmt = $db->prepare("SELECT id FROM jobs WHERE id = ? AND employer_id = ?");
    $checkStmt->bind_param('ii', $jobId, $employerId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows === 0) {
        jsonResponse(['success' => false, 'message' => 'Job not found or unauthorized'], 403);
    }
    $checkStmt->close();

    $updateFields = [];
    $params = [];
    $types = '';

    $allowedFields = [
        'title' => 's',
        'description' => 's',
        'requirements' => 's',
        'responsibilities' => 's',
        'job_type' => 's',
        'experience_required' => 's',
        'education_required' => 's',
        'salary_min' => 'd',
        'salary_max' => 'd',
        'salary_negotiable' => 'i',
        'location' => 's',
        'city' => 's',
        'state' => 's',
        'work_timings' => 's',
        'benefits' => 's',
        'vacancies' => 'i',
        'contact_email' => 's',
        'contact_phone' => 's',
        'whatsapp_number' => 's',
        'application_deadline' => 's',
        'status' => 's'
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

    $params[] = $jobId;
    $types .= 'i';

    $query = "UPDATE jobs SET " . implode(', ', $updateFields) . " WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        logActivity('job_updated', "Job ID: $jobId", null, $employerId, null);
        
        // RE-INDEX after update
        ob_start();
        submitJobForIndexing($jobId);
        ob_end_clean();
        
        jsonResponse(['success' => true, 'message' => 'Job updated successfully']);
    } else {
        jsonResponse(['success' => false, 'message' => $stmt->error], 500);
    }

    $stmt->close();
}

/**
 * Delete job
 */
function deleteJob() {
    global $db;

    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['id'])) {
        jsonResponse(['success' => false, 'message' => 'Job ID is required'], 400);
    }

    $jobId = intval($data['id']);
    $employerId = getEmployerId();

    $checkStmt = $db->prepare("SELECT title FROM jobs WHERE id = ? AND employer_id = ?");
    $checkStmt->bind_param('ii', $jobId, $employerId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows === 0) {
        jsonResponse(['success' => false, 'message' => 'Job not found or unauthorized'], 403);
    }

    $job = $checkResult->fetch_assoc();
    $jobTitle = $job['title'];
    $checkStmt->close();

    $stmt = $db->prepare("DELETE FROM jobs WHERE id = ?");
    $stmt->bind_param('i', $jobId);

    if ($stmt->execute()) {
        logActivity('job_deleted', "Job ID: $jobId - $jobTitle", null, $employerId, null);
        
        ob_start();
        // REMOVE FROM GOOGLE INDEX before deleting
        removeJobFromIndex($jobId);
        ob_end_clean();
        
        jsonResponse(['success' => true, 'message' => 'Job deleted successfully']);
    } else {
        jsonResponse(['success' => false, 'message' => $stmt->error], 500);
    }

    $stmt->close();
}

/**
 * Submit job to Google for indexing
 * Runs asynchronously to avoid blocking the main response
 */
function submitJobForIndexing($jobId) {
    try {
        $indexingAPI = new GoogleIndexingAPI();
        $result = $indexingAPI->indexJob($jobId);
        
        logIndexingAttempt($jobId, 'index', $result['success'] ? 1 : 0, $result['message']);
    } catch (Exception $e) {
        logIndexingAttempt($jobId, 'index', 0, 'Exception: ' . $e->getMessage());
    }
}

/**
 * Remove job from Google index
 */
function removeJobFromIndex($jobId) {
    try {
        $indexingAPI = new GoogleIndexingAPI();
        $result = $indexingAPI->removeJob($jobId);
        
        logIndexingAttempt($jobId, 'remove', $result['success'] ? 1 : 0, $result['message']);
    } catch (Exception $e) {
        logIndexingAttempt($jobId, 'remove', 0, 'Exception: ' . $e->getMessage());
    }
}
?>
