<?php
/**
 * Google Indexing Report Dashboard
 * View all indexing activities and statistics
 */

require_once '../config.php';
requireAdmin();

// Simple admin check (enhance this with your auth system)
session_start();
// if (!isset($_SESSION['admin'])) {
//     header('Location: /login.html');
//     exit;
// }

$db = Database::getInstance();

// Get statistics
$statsQuery = "SELECT 
    COUNT(*) as total_attempts,
    SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as successful,
    SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as failed,
    SUM(CASE WHEN action = 'index' THEN 1 ELSE 0 END) as index_attempts,
    SUM(CASE WHEN action = 'remove' THEN 1 ELSE 0 END) as remove_attempts
    FROM indexing_log";

$stats = $db->query($statsQuery)->fetch_assoc();

// Get recent logs
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
$action = isset($_GET['action']) ? $_GET['action'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';

$logsQuery = "SELECT l.*, j.title, j.company_name, j.status as job_status
              FROM indexing_log l
              LEFT JOIN jobs j ON l.job_id = j.id
              WHERE 1=1";

$params = [];
$types = '';

if ($action) {
    $logsQuery .= " AND l.action = ?";
    $params[] = $action;
    $types .= 's';
}

if ($status === 'success') {
    $logsQuery .= " AND l.success = 1";
} elseif ($status === 'failed') {
    $logsQuery .= " AND l.success = 0";
}

$logsQuery .= " ORDER BY l.created_at DESC LIMIT ?";
$params[] = $limit;
$types .= 'i';

$stmt = $db->prepare($logsQuery);
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$logs = $stmt->get_result();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Indexing Report - BackboneJobs</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; background: #f5f7fa; }
        .header { background: #4285f4; color: white; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header h1 { font-size: 24px; }
        .container { max-width: 1400px; margin: 0 auto; padding: 30px 20px; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .stat-card h3 { font-size: 14px; color: #666; margin-bottom: 10px; text-transform: uppercase; }
        .stat-card .number { font-size: 32px; font-weight: bold; color: #333; }
        .stat-card.success .number { color: #0f9d58; }
        .stat-card.error .number { color: #db4437; }
        .stat-card.info .number { color: #4285f4; }
        
        .filters { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .filters h3 { margin-bottom: 15px; color: #333; }
        .filters form { display: flex; gap: 15px; flex-wrap: wrap; align-items: end; }
        .filter-group { display: flex; flex-direction: column; }
        .filter-group label { font-size: 14px; color: #666; margin-bottom: 5px; }
        .filter-group select, .filter-group input { padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; }
        .btn { padding: 8px 20px; background: #4285f4; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; }
        .btn:hover { background: #357ae8; }
        .btn-secondary { background: #f1f3f4; color: #333; }
        .btn-secondary:hover { background: #e8eaed; }
        
        .logs-table { background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; }
        thead { background: #f8f9fa; }
        th { padding: 15px; text-align: left; font-weight: 600; color: #333; font-size: 14px; border-bottom: 2px solid #e8eaed; }
        td { padding: 15px; border-bottom: 1px solid #f1f3f4; font-size: 14px; }
        tr:hover { background: #f8f9fa; }
        
        .status-badge { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .status-badge.success { background: #c8e6c9; color: #1b5e20; }
        .status-badge.failed { background: #ffcdd2; color: #b71c1c; }
        .action-badge { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 12px; background: #e3f2fd; color: #1565c0; }
        
        .message-cell { max-width: 400px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: #666; font-size: 13px; }
        .job-title { font-weight: 500; color: #333; }
        .company-name { color: #666; font-size: 13px; }
        
        .no-data { padding: 40px; text-align: center; color: #666; }
        
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: 1fr; }
            .filters form { flex-direction: column; }
            .filter-group { width: 100%; }
            table { font-size: 12px; }
            td, th { padding: 10px; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🔍 Google Indexing Report</h1>
    </div>
    
    <div class="container">
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card info">
                <h3>Total Attempts</h3>
                <div class="number"><?php echo number_format($stats['total_attempts']); ?></div>
            </div>
            <div class="stat-card success">
                <h3>Successful</h3>
                <div class="number"><?php echo number_format($stats['successful']); ?></div>
            </div>
            <div class="stat-card error">
                <h3>Failed</h3>
                <div class="number"><?php echo number_format($stats['failed']); ?></div>
            </div>
            <div class="stat-card">
                <h3>Index Requests</h3>
                <div class="number"><?php echo number_format($stats['index_attempts']); ?></div>
            </div>
            <div class="stat-card">
                <h3>Remove Requests</h3>
                <div class="number"><?php echo number_format($stats['remove_attempts']); ?></div>
            </div>
            <div class="stat-card success">
                <h3>Success Rate</h3>
                <div class="number">
                    <?php 
                    if ($stats['total_attempts'] > 0) {
                        echo round(($stats['successful'] / $stats['total_attempts']) * 100, 1) . '%';
                    } else {
                        echo '0%';
                    }
                    ?>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filters">
            <h3>Filters</h3>
            <form method="GET">
                <div class="filter-group">
                    <label>Action</label>
                    <select name="action">
                        <option value="">All Actions</option>
                        <option value="index" <?php echo $action === 'index' ? 'selected' : ''; ?>>Index</option>
                        <option value="remove" <?php echo $action === 'remove' ? 'selected' : ''; ?>>Remove</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="">All Status</option>
                        <option value="success" <?php echo $status === 'success' ? 'selected' : ''; ?>>Success</option>
                        <option value="failed" <?php echo $status === 'failed' ? 'selected' : ''; ?>>Failed</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Limit</label>
                    <select name="limit">
                        <option value="50" <?php echo $limit === 50 ? 'selected' : ''; ?>>50</option>
                        <option value="100" <?php echo $limit === 100 ? 'selected' : ''; ?>>100</option>
                        <option value="200" <?php echo $limit === 200 ? 'selected' : ''; ?>>200</option>
                        <option value="500" <?php echo $limit === 500 ? 'selected' : ''; ?>>500</option>
                    </select>
                </div>
                <button type="submit" class="btn">Apply Filters</button>
                <a href="?" class="btn btn-secondary">Reset</a>
            </form>
        </div>
        
        <!-- Logs Table -->
        <div class="logs-table">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Job Details</th>
                        <th>Action</th>
                        <th>Status</th>
                        <th>Message</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($logs->num_rows > 0): ?>
                        <?php while ($log = $logs->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $log['job_id']; ?></td>
                                <td>
                                    <div class="job-title"><?php echo htmlspecialchars($log['title'] ?? 'Job Deleted'); ?></div>
                                    <div class="company-name"><?php echo htmlspecialchars($log['company_name'] ?? 'N/A'); ?></div>
                                </td>
                                <td>
                                    <span class="action-badge"><?php echo strtoupper($log['action']); ?></span>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $log['success'] ? 'success' : 'failed'; ?>">
                                        <?php echo $log['success'] ? '✓ Success' : '✗ Failed'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="message-cell" title="<?php echo htmlspecialchars($log['message']); ?>">
                                        <?php echo htmlspecialchars($log['message']); ?>
                                    </div>
                                </td>
                                <td><?php echo date('M d, Y H:i', strtotime($log['created_at'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="no-data">No indexing logs found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>