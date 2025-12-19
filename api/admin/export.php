<?php
/**
 * Admin Data Export
 * Exports system data in various formats (CSV, JSON)
 */

require_once '../config.php';

// Require admin authentication
requireAdmin();

$db = Database::getInstance();

// Get export type from request
$type = isset($_GET['type']) ? sanitizeInput($_GET['type']) : '';
$format = isset($_GET['format']) ? sanitizeInput($_GET['format']) : 'csv';

// Validate export type
$validTypes = ['users', 'employers', 'jobs', 'applications', 'all'];
if (!in_array($type, $validTypes)) {
    jsonResponse(['success' => false, 'message' => 'Invalid export type'], 400);
}

// Validate format
$validFormats = ['csv', 'json'];
if (!in_array($format, $validFormats)) {
    $format = 'csv';
}

// Export based on type
switch ($type) {
    case 'users':
        exportUsers($format);
        break;
    case 'employers':
        exportEmployers($format);
        break;
    case 'jobs':
        exportJobs($format);
        break;
    case 'applications':
        exportApplications($format);
        break;
    case 'all':
        exportAll($format);
        break;
}

/**
 * Export users data
 */
function exportUsers($format) {
    global $db;

    $query = "SELECT id, name, email, phone, date_of_birth, gender, 
              current_location, city, state, experience_years, 
              current_job_title, education, is_active, is_verified, 
              created_at, last_login
              FROM users
              ORDER BY created_at DESC";

    $result = $db->query($query);
    $data = [];

    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }

    if ($format === 'csv') {
        exportCSV($data, 'users_export_' . date('Y-m-d'));
    } else {
        exportJSON($data, 'users_export_' . date('Y-m-d'));
    }
}

/**
 * Export employers data
 */
function exportEmployers($format) {
    global $db;

    $query = "SELECT id, company_name, contact_person, email, phone, 
              whatsapp_number, company_address, city, state, 
              company_description, industry, company_size, website,
              is_verified, is_active, created_at
              FROM employers
              ORDER BY created_at DESC";

    $result = $db->query($query);
    $data = [];

    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }

    if ($format === 'csv') {
        exportCSV($data, 'employers_export_' . date('Y-m-d'));
    } else {
        exportJSON($data, 'employers_export_' . date('Y-m-d'));
    }
}

/**
 * Export jobs data
 */
function exportJobs($format) {
    global $db;

    $query = "SELECT j.id, j.title, j.company_name, c.category_name,
              j.description, j.job_type, j.experience_required,
              j.education_required, j.salary_min, j.salary_max,
              j.location, j.city, j.state, j.vacancies,
              j.status, j.is_featured, j.views, j.posted_date,
              j.application_deadline,
              (SELECT COUNT(*) FROM applications WHERE job_id = j.id) as application_count
              FROM jobs j
              LEFT JOIN job_categories c ON j.category_id = c.id
              ORDER BY j.posted_date DESC";

    $result = $db->query($query);
    $data = [];

    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }

    if ($format === 'csv') {
        exportCSV($data, 'jobs_export_' . date('Y-m-d'));
    } else {
        exportJSON($data, 'jobs_export_' . date('Y-m-d'));
    }
}

/**
 * Export applications data
 */
function exportApplications($format) {
    global $db;

    $query = "SELECT a.id, u.name as applicant_name, u.email as applicant_email,
              u.phone as applicant_phone, j.title as job_title, j.company_name,
              a.status, a.expected_salary, a.available_from,
              a.applied_date, a.reviewed_date
              FROM applications a
              INNER JOIN users u ON a.user_id = u.id
              INNER JOIN jobs j ON a.job_id = j.id
              ORDER BY a.applied_date DESC";

    $result = $db->query($query);
    $data = [];

    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }

    if ($format === 'csv') {
        exportCSV($data, 'applications_export_' . date('Y-m-d'));
    } else {
        exportJSON($data, 'applications_export_' . date('Y-m-d'));
    }
}

/**
 * Export all data in a single file
 */
function exportAll($format) {
    global $db;

    $allData = [];

    // Get users
    $usersQuery = "SELECT * FROM users ORDER BY created_at DESC";
    $usersResult = $db->query($usersQuery);
    $allData['users'] = [];
    while ($row = $usersResult->fetch_assoc()) {
        $allData['users'][] = $row;
    }

    // Get employers
    $employersQuery = "SELECT * FROM employers ORDER BY created_at DESC";
    $employersResult = $db->query($employersQuery);
    $allData['employers'] = [];
    while ($row = $employersResult->fetch_assoc()) {
        $allData['employers'][] = $row;
    }

    // Get jobs
    $jobsQuery = "SELECT j.*, c.category_name 
                  FROM jobs j 
                  LEFT JOIN job_categories c ON j.category_id = c.id 
                  ORDER BY j.posted_date DESC";
    $jobsResult = $db->query($jobsQuery);
    $allData['jobs'] = [];
    while ($row = $jobsResult->fetch_assoc()) {
        $allData['jobs'][] = $row;
    }

    // Get applications
    $appsQuery = "SELECT * FROM applications ORDER BY applied_date DESC";
    $appsResult = $db->query($appsQuery);
    $allData['applications'] = [];
    while ($row = $appsResult->fetch_assoc()) {
        $allData['applications'][] = $row;
    }

    // Get categories
    $catsQuery = "SELECT * FROM job_categories ORDER BY category_name";
    $catsResult = $db->query($catsQuery);
    $allData['categories'] = [];
    while ($row = $catsResult->fetch_assoc()) {
        $allData['categories'][] = $row;
    }

    if ($format === 'json') {
        exportJSON($allData, 'complete_export_' . date('Y-m-d'));
    } else {
        // For CSV, export each section separately in a ZIP file
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Use JSON format for complete export. CSV format only supports individual sections.'
        ]);
        exit;
    }
}

/**
 * Export data as CSV
 */
function exportCSV($data, $filename) {
    if (empty($data)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'No data to export']);
        exit;
    }

    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Create output stream
    $output = fopen('php://output', 'w');

    // Add BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Add column headers
    $headers = array_keys($data[0]);
    fputcsv($output, $headers);

    // Add data rows
    foreach ($data as $row) {
        fputcsv($output, $row);
    }

    fclose($output);
    exit;
}

/**
 * Export data as JSON
 */
function exportJSON($data, $filename) {
    if (empty($data)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'No data to export']);
        exit;
    }

    // Set headers for JSON download
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.json"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Output JSON
    echo json_encode([
        'exported_at' => date('Y-m-d H:i:s'),
        'total_records' => is_array($data) && isset($data['users']) ? 
            array_sum(array_map('count', $data)) : count($data),
        'data' => $data
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    exit;
}

// Log the export activity
$adminId = getAdminId();
logActivity('data_exported', "Export Type: $type, Format: $format", null, null, $adminId);

?>