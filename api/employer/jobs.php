<?php
require_once '../config.php';

$db = Database::getInstance();

// Require employer authentication
requireEmployer();

$employerId = getEmployerId();
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

$query = "SELECT j.*, c.category_name,
          (SELECT COUNT(*) FROM applications WHERE job_id = j.id) as application_count
          FROM jobs j
          LEFT JOIN job_categories c ON j.category_id = c.id
          WHERE j.employer_id = ?
          ORDER BY j.posted_date DESC
          LIMIT ? OFFSET ?";

$stmt = $db->prepare($query);
$stmt->bind_param('iii', $employerId, $limit, $offset);
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
?>