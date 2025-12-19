<?php
/**
 * Admin Job Posting Management with Auto-Indexing
 * Allows admin to manage all jobs in the system
 */

require_once '../config.php';
require_once __DIR__.'/../indexing/google-indexing-api.php';

// Require admin authentication
requireAdmin();

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

// Handle different HTTP methods
switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            getJobDetails($_GET['id']);
        } else if (isset($_GET['stats'])) {
            getAllJobStats();
        } else {
            getAllJobs();
        }
        break;

    case 'POST':
        createJobForEmployer();
        break;

    case 'PUT':
        updateJob();
        break;

    case 'DELETE':
        deleteJob();
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

/**
 * Get all jobs in the system with filters
 */
function getAllJobs() {
    global $db;

    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
    $status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
    $employerId = isset($_GET['employer_id']) ? intval($_GET['employer_id']) : 0;
    $categoryId = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
    $search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';

    $query = "SELECT j.*, c.category_name, e.company_name as employer_company,
              e.contact_person, e.email as employer_email,
              (SELECT COUNT(*) FROM applications WHERE job_id = j.id) as application_count,
              (SELECT COUNT(*) FROM applications WHERE job_id = j.id AND status = 'Pending') as pending_count
              FROM jobs j
              LEFT JOIN job_categories c ON j.category_id = c.id
              LEFT JOIN employers e ON j.employer_id = e.id
              WHERE 1=1";

    $params = [];
    $types = '';

    if ($status) {
        $query .= " AND j.status = ?";
        $params[] = $status;
        $types .= 's';
    }

    if ($employerId > 0) {
        $query .= " AND j.employer_id = ?";
        $params[] = $employerId;
        $types .= 'i';
    }

    if ($categoryId > 0) {
        $query .= " AND j.category_id = ?";
        $params[] = $categoryId;
        $types .= 'i';
    }

    if ($search) {
        $query .= " AND (j.title LIKE ? OR j.company_name LIKE ? OR j.location LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= 'sss';
    }

    $query .= " ORDER BY j.posted_date DESC LIMIT ? OFFSET ?";
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

    // Get total count for pagination
    $countQuery = "SELECT COUNT(*) as total FROM jobs j WHERE 1=1";
    $countParams = [];
    $countTypes = '';

    if ($status) {
        $countQuery .= " AND j.status = ?";
        $countParams[] = $status;
        $countTypes .= 's';
    }

    if ($employerId > 0) {
        $countQuery .= " AND j.employer_id = ?";
        $countParams[] = $employerId;
        $countTypes .= 'i';
    }

    if ($categoryId > 0) {
        $countQuery .= " AND j.category_id = ?";
        $countParams[] = $categoryId;
        $countTypes .= 'i';
    }

    if ($search) {
        $countQuery .= " AND (j.title LIKE ? OR j.company_name LIKE ? OR j.location LIKE ?)";
        $searchTerm = "%$search%";
        $countParams[] = $searchTerm;
        $countParams[] = $searchTerm;
        $countParams[] = $searchTerm;
        $countTypes .= 'sss';
    }

    $countStmt = $db->prepare($countQuery);
    if ($countTypes) {
        $countStmt->bind_param($countTypes, ...$countParams);
    }
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalCount = $countResult->fetch_assoc()['total'];
    $countStmt->close();

    jsonResponse([
        'success' => true,
        'jobs' => $jobs,
        'total' => $totalCount,
        'count' => count($jobs)
    ]);
}

/**
 * Get detailed job information
 */
function getJobDetails($jobId) {
    global $db;

    $jobId = intval($jobId);

    $query = "SELECT j.*, c.category_name, e.company_name as employer_company,
              e.contact_person, e.email as employer_email, e.phone as employer_phone,
              e.whatsapp_number as employer_whatsapp, e.is_verified
              FROM jobs j
              LEFT JOIN job_categories c ON j.category_id = c.id
              LEFT JOIN employers e ON j.employer_id = e.id
              WHERE j.id = ?";

    $stmt = $db->prepare($query);
    $stmt->bind_param('i', $jobId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        jsonResponse(['success' => false, 'message' => 'Job not found'], 404);
    }

    $job = $result->fetch_assoc();
    $stmt->close();

    // Get application statistics
    $statsStmt = $db->prepare("SELECT 
        COUNT(*) as total_applications,
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'Reviewed' THEN 1 ELSE 0 END) as reviewed,
        SUM(CASE WHEN status = 'Shortlisted' THEN 1 ELSE 0 END) as shortlisted,
        SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN status = 'Hired' THEN 1 ELSE 0 END) as hired
        FROM applications WHERE job_id = ?");
    $statsStmt->bind_param('i', $jobId);
    $statsStmt->execute();
    $statsResult = $statsStmt->get_result();
    $job['application_stats'] = $statsResult->fetch_assoc();
    $statsStmt->close();

    jsonResponse([
        'success' => true,
        'job' => $job
    ]);
}

/**
 * Admin creates job for an employer with auto-indexing
 */
function createJobForEmployer() {
    global $db;

    $data = json_decode(file_get_contents('php://input'), true);

    // Validate required fields
    $required = ['employer_id', 'title', 'description', 'category_id', 'location', 'salary_min', 'salary_max'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            jsonResponse(['success' => false, 'message' => "Field '$field' is required"], 400);
        }
    }

    $employerId = intval($data['employer_id']);

    // Verify employer exists
    $empStmt = $db->prepare("SELECT company_name, is_active FROM employers WHERE id = ?");
    $empStmt->bind_param('i', $employerId);
    $empStmt->execute();
    $empResult = $empStmt->get_result();

    if ($empResult->num_rows === 0) {
        jsonResponse(['success' => false, 'message' => 'Employer not found'], 404);
    }

    $employer = $empResult->fetch_assoc();
    $companyName = $employer['company_name'];
    $empStmt->close();

    // Validate category
    $categoryId = intval($data['category_id']);
    $catStmt = $db->prepare("SELECT id FROM job_categories WHERE id = ?");
    $catStmt->bind_param('i', $categoryId);
    $catStmt->execute();
    $catResult = $catStmt->get_result();

    if ($catResult->num_rows === 0) {
        jsonResponse(['success' => false, 'message' => 'Invalid category'], 400);
    }
    $catStmt->close();

    // Validate salary range
    $salaryMin = floatval($data['salary_min']);
    $salaryMax = floatval($data['salary_max']);

    if ($salaryMin < 0 || $salaryMax < 0) {
        jsonResponse(['success' => false, 'message' => 'Salary cannot be negative'], 400);
    }

    if ($salaryMin > $salaryMax) {
        jsonResponse(['success' => false, 'message' => 'Minimum salary cannot exceed maximum salary'], 400);
    }

    // Extract all values into variables
    $title = sanitizeInput($data['title']);
    $description = sanitizeInput($data['description']);
    $requirements = isset($data['requirements']) ? sanitizeInput($data['requirements']) : '';
    $responsibilities = isset($data['responsibilities']) ? sanitizeInput($data['responsibilities']) : '';
    $jobType = isset($data['job_type']) ? sanitizeInput($data['job_type']) : 'Full-Time';
    $experienceRequired = isset($data['experience_required']) ? sanitizeInput($data['experience_required']) : 'Any';
    $educationRequired = isset($data['education_required']) ? sanitizeInput($data['education_required']) : '';
    $salaryNegotiable = isset($data['salary_negotiable']) ? intval($data['salary_negotiable']) : 0;
    $location = isset($data['location']) ? sanitizeInput($data['location']) : '';
    $city = isset($data['city']) ? sanitizeInput($data['city']) : '';
    $state = isset($data['state']) ? sanitizeInput($data['state']) : '';
    $pincode = isset($data['pincode']) ? sanitizeInput($data['pincode']) : '';
    $workTimings = isset($data['work_timings']) ? sanitizeInput($data['work_timings']) : '';
    $benefits = isset($data['benefits']) ? sanitizeInput($data['benefits']) : '';
    $vacancies = isset($data['vacancies']) ? intval($data['vacancies']) : 1;
    $contactEmail = isset($data['contact_email']) ? sanitizeInput($data['contact_email']) : '';
    $contactPhone = isset($data['contact_phone']) ? sanitizeInput($data['contact_phone']) : '';
    $whatsappNumber = isset($data['whatsapp_number']) ? sanitizeInput($data['whatsapp_number']) : '';
    $applicationDeadline = isset($data['application_deadline']) ? $data['application_deadline'] : null;
    $status = isset($data['status']) ? sanitizeInput($data['status']) : 'Active';
    $isFeatured = isset($data['is_featured']) ? intval($data['is_featured']) : 0;

    // Validate status
    $validStatuses = ['Active', 'Inactive', 'Closed'];
    if (!in_array($status, $validStatuses)) {
        $status = 'Active';
    }

    // Insert job posting
    $query = "INSERT INTO jobs (
        employer_id, category_id, title, company_name, description,
        requirements, responsibilities, job_type, experience_required,
        education_required, salary_min, salary_max, salary_negotiable,
        location, city, state, pincode, work_timings, benefits, vacancies,
        contact_email, contact_phone, whatsapp_number, application_deadline, 
        status, is_featured, posted_date
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    $stmt = $db->prepare($query);

    $stmt->bind_param(
        "iisssssssssddssssisssssssi",
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
        $pincode,
        $workTimings,
        $benefits,
        $vacancies,
        $contactEmail,
        $contactPhone,
        $whatsappNumber,
        $applicationDeadline,
        $status,
        $isFeatured
    );

    if ($stmt->execute()) {
        $jobId = $db->lastInsertId();
        
        // Log activity
        $adminId = getAdminId();
        logActivity('admin_job_created', "Job ID: $jobId - $title for Employer ID: $employerId", null, null, $adminId);
        
        // AUTO-TRIGGER GOOGLE INDEXING (only if status is Active)
        if ($status === 'Active') {
            submitJobForIndexing($jobId);
        }
        
        jsonResponse([
            'success' => true,
            'message' => 'Job posted successfully' . ($status === 'Active' ? ' and submitted for indexing' : ''),
            'job_id' => $jobId
        ], 201);
    } else {
        jsonResponse(['success' => false, 'message' => 'Failed to post job: ' . $stmt->error], 500);
    }

    $stmt->close();
}

/**
 * Admin updates any job with re-indexing
 */
function updateJob() {
    global $db;

    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['id'])) {
        jsonResponse(['success' => false, 'message' => 'Job ID is required'], 400);
    }

    $jobId = intval($data['id']);

    // Verify job exists and get current status
    $checkStmt = $db->prepare("SELECT id, employer_id, title, status FROM jobs WHERE id = ?");
    $checkStmt->bind_param('i', $jobId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows === 0) {
        jsonResponse(['success' => false, 'message' => 'Job not found'], 404);
    }

    $jobData = $checkResult->fetch_assoc();
    $oldStatus = $jobData['status'];
    $checkStmt->close();

    // Build update query dynamically
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
        'status' => 's',
        'category_id' => 'i',
        'is_featured' => 'i'
    ];

    $newStatus = $oldStatus; // Track status changes

    foreach ($allowedFields as $field => $type) {
        if (isset($data[$field])) {
            // Special validation
            if ($field === 'salary_min' || $field === 'salary_max') {
                if ($data[$field] < 0) {
                    jsonResponse(['success' => false, 'message' => 'Salary cannot be negative'], 400);
                }
            }
            
            if ($field === 'vacancies' && $data[$field] < 1) {
                jsonResponse(['success' => false, 'message' => 'Vacancies must be at least 1'], 400);
            }

            if ($field === 'category_id') {
                $catId = intval($data[$field]);
                $catCheck = $db->prepare("SELECT id FROM job_categories WHERE id = ?");
                $catCheck->bind_param('i', $catId);
                $catCheck->execute();
                if ($catCheck->get_result()->num_rows === 0) {
                    jsonResponse(['success' => false, 'message' => 'Invalid category'], 400);
                }
                $catCheck->close();
            }

            if ($field === 'status') {
                $validStatuses = ['Active', 'Inactive', 'Closed'];
                if (!in_array($data[$field], $validStatuses)) {
                    jsonResponse(['success' => false, 'message' => 'Invalid status'], 400);
                }
                $newStatus = $data[$field];
            }

            $updateFields[] = "$field = ?";
            $params[] = $type === 's' ? sanitizeInput($data[$field]) : $data[$field];
            $types .= $type;
        }
    }

    if (empty($updateFields)) {
        jsonResponse(['success' => false, 'message' => 'No fields to update'], 400);
    }

    // Add updated_at timestamp
    $updateFields[] = "updated_at = NOW()";
    
    // Add job ID to params
    $params[] = $jobId;
    $types .= 'i';

    $query = "UPDATE jobs SET " . implode(', ', $updateFields) . " WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        $adminId = getAdminId();
        logActivity('admin_job_updated', "Job ID: $jobId - {$jobData['title']}", null, null, $adminId);
        
        // HANDLE INDEXING BASED ON STATUS CHANGE
        if ($oldStatus === 'Active' && $newStatus !== 'Active') {
            // Job became inactive - remove from index
            removeJobFromIndex($jobId);
        } else if ($newStatus === 'Active') {
            // Job is active (newly activated or updated) - re-index
            submitJobForIndexing($jobId);
        }
        
        jsonResponse([
            'success' => true,
            'message' => 'Job updated successfully' . ($newStatus === 'Active' ? ' and re-indexed' : ($oldStatus === 'Active' && $newStatus !== 'Active' ? ' and removed from index' : ''))
        ]);
    } else {
        jsonResponse(['success' => false, 'message' => 'Failed to update job: ' . $stmt->error], 500);
    }

    $stmt->close();
}

/**
 * Admin deletes job (with application check) and removes from index
 */
function deleteJob() {
    global $db;

    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['id'])) {
        jsonResponse(['success' => false, 'message' => 'Job ID is required'], 400);
    }

    $jobId = intval($data['id']);
    $forceDelete = isset($data['force']) ? boolval($data['force']) : false;

    // Get job details
    $checkStmt = $db->prepare("SELECT title, employer_id, status FROM jobs WHERE id = ?");
    $checkStmt->bind_param('i', $jobId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows === 0) {
        jsonResponse(['success' => false, 'message' => 'Job not found'], 404);
    }

    $job = $checkResult->fetch_assoc();
    $jobTitle = $job['title'];
    $jobStatus = $job['status'];
    $checkStmt->close();

    // Check for applications
    $appStmt = $db->prepare("SELECT COUNT(*) as count FROM applications WHERE job_id = ?");
    $appStmt->bind_param('i', $jobId);
    $appStmt->execute();
    $appResult = $appStmt->get_result();
    $appCount = $appResult->fetch_assoc()['count'];
    $appStmt->close();

    if ($appCount > 0 && !$forceDelete) {
        jsonResponse([
            'success' => false,
            'message' => "This job has $appCount applications. Set force=true to delete anyway.",
            'application_count' => $appCount
        ], 400);
    }

    // REMOVE FROM GOOGLE INDEX before deleting (if it was active)
    if ($jobStatus === 'Active') {
        removeJobFromIndex($jobId);
    }

    // If force delete, remove applications first
    if ($appCount > 0 && $forceDelete) {
        $deleteAppsStmt = $db->prepare("DELETE FROM applications WHERE job_id = ?");
        $deleteAppsStmt->bind_param('i', $jobId);
        $deleteAppsStmt->execute();
        $deleteAppsStmt->close();
    }

    // Delete the job
    $deleteStmt = $db->prepare("DELETE FROM jobs WHERE id = ?");
    $deleteStmt->bind_param('i', $jobId);

    if ($deleteStmt->execute()) {
        $adminId = getAdminId();
        logActivity('admin_job_deleted', "Job ID: $jobId - $jobTitle" . ($forceDelete ? ' (forced)' : ''), null, null, $adminId);
        
        jsonResponse([
            'success' => true,
            'message' => 'Job deleted successfully' . ($jobStatus === 'Active' ? ' and removed from Google index' : '')
        ]);
    } else {
        jsonResponse(['success' => false, 'message' => 'Failed to delete job'], 500);
    }

    $deleteStmt->close();
}

/**
 * Get comprehensive job statistics
 */
function getAllJobStats() {
    global $db;

    $query = "SELECT 
        COUNT(*) as total_jobs,
        SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active_jobs,
        SUM(CASE WHEN status = 'Inactive' THEN 1 ELSE 0 END) as inactive_jobs,
        SUM(CASE WHEN status = 'Closed' THEN 1 ELSE 0 END) as closed_jobs,
        SUM(CASE WHEN is_featured = 1 THEN 1 ELSE 0 END) as featured_jobs,
        SUM(views) as total_views,
        AVG(views) as avg_views,
        (SELECT COUNT(*) FROM applications) as total_applications,
        (SELECT COUNT(*) FROM applications WHERE status = 'Pending') as pending_applications,
        (SELECT COUNT(DISTINCT employer_id) FROM jobs) as employers_with_jobs
        FROM jobs";

    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats = $result->fetch_assoc();
    $stmt->close();

    // Get jobs by category
    $catStmt = $db->prepare("SELECT c.category_name, COUNT(j.id) as job_count 
                             FROM job_categories c 
                             LEFT JOIN jobs j ON c.id = j.category_id 
                             GROUP BY c.id, c.category_name 
                             ORDER BY job_count DESC 
                             LIMIT 10");
    $catStmt->execute();
    $catResult = $catStmt->get_result();
    $categoryStats = [];
    while ($row = $catResult->fetch_assoc()) {
        $categoryStats[] = $row;
    }
    $catStmt->close();

    // Get recent jobs
    $recentStmt = $db->prepare("SELECT id, title, company_name, status, posted_date 
                                FROM jobs 
                                ORDER BY posted_date DESC 
                                LIMIT 5");
    $recentStmt->execute();
    $recentResult = $recentStmt->get_result();
    $recentJobs = [];
    while ($row = $recentResult->fetch_assoc()) {
        $recentJobs[] = $row;
    }
    $recentStmt->close();

    jsonResponse([
        'success' => true,
        'stats' => $stats,
        'by_category' => $categoryStats,
        'recent_jobs' => $recentJobs
    ]);
}

/**
 * Submit job to Google for indexing
 * Runs asynchronously to avoid blocking the main response
 */
function submitJobForIndexing($jobId) {
    try {
        $indexingAPI = new GoogleIndexingAPI();
        $result = $indexingAPI->indexJob($jobId);
        
        logIndexingAttempt($jobId, 'index', $result['success'] ? 1 : 0, 'Admin: ' . $result['message']);
    } catch (Exception $e) {
        logIndexingAttempt($jobId, 'index', 0, 'Admin Exception: ' . $e->getMessage());
    }
}

/**
 * Remove job from Google index
 */
function removeJobFromIndex($jobId) {
    try {
        $indexingAPI = new GoogleIndexingAPI();
        $result = $indexingAPI->removeJob($jobId);
        
        logIndexingAttempt($jobId, 'remove', $result['success'] ? 1 : 0, 'Admin: ' . $result['message']);
    } catch (Exception $e) {
        logIndexingAttempt($jobId, 'remove', 0, 'Admin Exception: ' . $e->getMessage());
    }
}

?>