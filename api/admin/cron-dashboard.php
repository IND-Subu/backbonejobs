<?php
/**
 * Cron Job Monitoring Dashboard
 * Monitor all automated indexing activities
 */

require_once '../config.php';
requireAdmin();

$db = Database::getInstance();

// Get cron execution stats
$cronStats = $db->query("
    SELECT 
        COUNT(*) as total_runs,
        SUM(processed) as total_processed,
        SUM(successful) as total_successful,
        AVG(execution_time) as avg_time,
        MAX(created_at) as last_run
    FROM cron_logs
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
")->fetch_assoc();

// Get recent cron runs
$recentRuns = $db->query("
    SELECT * FROM cron_logs 
    ORDER BY created_at DESC 
    LIMIT 20
");

// Get today's indexing activity
$todayStats = $db->query("
    SELECT 
        action,
        success,
        COUNT(*) as count
    FROM indexing_log
    WHERE DATE(created_at) = CURDATE()
    GROUP BY action, success
");

$todayData = [
    'index_success' => 0,
    'index_failed' => 0,
    'remove_success' => 0,
    'remove_failed' => 0
];

while ($row = $todayStats->fetch_assoc()) {
    $key = $row['action'] . '_' . ($row['success'] ? 'success' : 'failed');
    $todayData[$key] = $row['count'];
}

// Check if cron is running (last run within 2 hours)
$lastRunTime = strtotime($cronStats['last_run'] ?? '1970-01-01');
$cronHealthy = (time() - $lastRunTime) < 7200; // 2 hours

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="300"> <!-- Auto refresh every 5 minutes -->
    <title>Cron Job Dashboard - BackboneJobs</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; background: #f5f7fa; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header h1 { font-size: 28px; margin-bottom: 5px; }
        .header p { opacity: 0.9; }
        .container { max-width: 1400px; margin: 0 auto; padding: 30px 20px; }
        
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .alert.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert.warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .alert.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); position: relative; overflow: hidden; }
        .stat-card::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; }
        .stat-card.blue::before { background: #3b82f6; }
        .stat-card.green::before { background: #10b981; }
        .stat-card.yellow::before { background: #f59e0b; }
        .stat-card.red::before { background: #ef4444; }
        .stat-card.purple::before { background: #8b5cf6; }
        
        .stat-card h3 { font-size: 14px; color: #666; margin-bottom: 10px; text-transform: uppercase; font-weight: 600; }
        .stat-card .number { font-size: 36px; font-weight: bold; color: #333; margin-bottom: 5px; }
        .stat-card .subtitle { font-size: 13px; color: #999; }
        
        .section { background: white; padding: 25px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .section h2 { margin-bottom: 20px; color: #333; font-size: 20px; display: flex; align-items: center; gap: 10px; }
        
        table { width: 100%; border-collapse: collapse; }
        thead { background: #f8f9fa; }
        th { padding: 12px; text-align: left; font-weight: 600; color: #333; font-size: 13px; border-bottom: 2px solid #e8eaed; }
        td { padding: 12px; border-bottom: 1px solid #f1f3f4; font-size: 13px; }
        tr:hover { background: #f8f9fa; }
        
        .status-dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 8px; }
        .status-dot.green { background: #10b981; }
        .status-dot.red { background: #ef4444; }
        .status-dot.yellow { background: #f59e0b; }
        
        .badge { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .badge.success { background: #d1fae5; color: #065f46; }
        .badge.danger { background: #fee2e2; color: #991b1b; }
        .badge.info { background: #dbeafe; color: #1e40af; }
        
        .progress-bar { width: 100%; height: 8px; background: #e5e7eb; border-radius: 4px; overflow: hidden; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, #10b981, #059669); transition: width 0.3s; }
        
        .btn { display: inline-block; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 6px; font-size: 14px; border: none; cursor: pointer; }
        .btn:hover { background: #5568d3; }
        
        .refresh-info { text-align: right; color: #999; font-size: 13px; margin-bottom: 10px; }
        
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: 1fr; }
            .header { padding: 20px; }
            table { font-size: 12px; }
            td, th { padding: 8px; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>⚙️ Cron Job Monitoring Dashboard</h1>
        <p>Real-time monitoring of automated indexing system</p>
    </div>
    
    <div class="container">
        <!-- Health Status Alert -->
        <?php if ($cronHealthy): ?>
            <div class="alert success">
                <span class="status-dot green"></span>
                <strong>System Healthy</strong> - Cron job is running normally. Last execution: <?php echo date('M d, Y H:i', $lastRunTime); ?>
            </div>
        <?php else: ?>
            <div class="alert error">
                <span class="status-dot red"></span>
                <strong>Warning!</strong> - Cron job hasn't run in over 2 hours. Last execution: <?php echo $cronStats['last_run'] ? date('M d, Y H:i', $lastRunTime) : 'Never'; ?>
            </div>
        <?php endif; ?>
        
        <div class="refresh-info">
            🔄 Auto-refreshes every 5 minutes | Last updated: <?php echo date('H:i:s'); ?>
        </div>
        
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card blue">
                <h3>Total Cron Runs</h3>
                <div class="number"><?php echo number_format($cronStats['total_runs'] ?? 0); ?></div>
                <div class="subtitle">Last 7 days</div>
            </div>
            
            <div class="stat-card green">
                <h3>Jobs Processed</h3>
                <div class="number"><?php echo number_format($cronStats['total_processed'] ?? 0); ?></div>
                <div class="subtitle">Via cron job</div>
            </div>
            
            <div class="stat-card yellow">
                <h3>Success Rate</h3>
                <div class="number">
                    <?php 
                    $processed = $cronStats['total_processed'] ?? 0;
                    $successful = $cronStats['total_successful'] ?? 0;
                    echo $processed > 0 ? round(($successful / $processed) * 100, 1) : 0;
                    ?>%
                </div>
                <div class="subtitle">Overall accuracy</div>
            </div>
            
            <div class="stat-card purple">
                <h3>Avg Execution Time</h3>
                <div class="number"><?php echo round($cronStats['avg_time'] ?? 0, 2); ?>s</div>
                <div class="subtitle">Per cron run</div>
            </div>
            
            <div class="stat-card green">
                <h3>Today: Indexed</h3>
                <div class="number"><?php echo $todayData['index_success']; ?></div>
                <div class="subtitle">
                    <?php if ($todayData['index_failed'] > 0): ?>
                        <span style="color: #ef4444;"><?php echo $todayData['index_failed']; ?> failed</span>
                    <?php else: ?>
                        All successful
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="stat-card red">
                <h3>Today: Removed</h3>
                <div class="number"><?php echo $todayData['remove_success']; ?></div>
                <div class="subtitle">
                    <?php if ($todayData['remove_failed'] > 0): ?>
                        <span style="color: #ef4444;"><?php echo $todayData['remove_failed']; ?> failed</span>
                    <?php else: ?>
                        All successful
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Recent Cron Executions -->
        <div class="section">
            <h2>📊 Recent Cron Executions</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Date & Time</th>
                        <th>Processed</th>
                        <th>Successful</th>
                        <th>Success Rate</th>
                        <th>Execution Time</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($recentRuns->num_rows > 0): ?>
                        <?php while ($run = $recentRuns->fetch_assoc()): ?>
                            <?php 
                            $successRate = $run['processed'] > 0 ? round(($run['successful'] / $run['processed']) * 100, 1) : 0;
                            $isGood = $successRate >= 90;
                            ?>
                            <tr>
                                <td>#<?php echo $run['id']; ?></td>
                                <td><?php echo date('M d, Y H:i:s', strtotime($run['created_at'])); ?></td>
                                <td><?php echo $run['processed']; ?></td>
                                <td><?php echo $run['successful']; ?></td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div class="progress-bar" style="width: 100px;">
                                            <div class="progress-fill" style="width: <?php echo $successRate; ?>%; background: <?php echo $isGood ? '#10b981' : '#ef4444'; ?>;"></div>
                                        </div>
                                        <span><?php echo $successRate; ?>%</span>
                                    </div>
                                </td>
                                <td><?php echo round($run['execution_time'], 2); ?>s</td>
                                <td>
                                    <span class="badge <?php echo $isGood ? 'success' : 'danger'; ?>">
                                        <?php echo $isGood ? '✓ Good' : '⚠ Issues'; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 30px; color: #999;">
                                No cron executions found. Make sure the cron job is set up correctly.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Quick Actions -->
        <div class="section">
            <h2>🚀 Quick Actions</h2>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="indexing-report.php" class="btn">📋 View Full Indexing Report</a>
                <a href="../../test-indexing.php" class="btn" style="background: #10b981;">🧪 Test Indexing API</a>
                <a href="?refresh=1" class="btn" style="background: #f59e0b;">🔄 Refresh Now</a>
            </div>
        </div>
        
        <!-- System Info -->
        <div class="section">
            <h2>ℹ️ System Information</h2>
            <table>
                <tr>
                    <td><strong>PHP Version:</strong></td>
                    <td><?php echo PHP_VERSION; ?></td>
                </tr>
                <tr>
                    <td><strong>cURL Enabled:</strong></td>
                    <td><?php echo function_exists('curl_init') ? '✅ Yes' : '❌ No'; ?></td>
                </tr>
                <tr>
                    <td><strong>OpenSSL Enabled:</strong></td>
                    <td><?php echo function_exists('openssl_sign') ? '✅ Yes' : '❌ No'; ?></td>
                </tr>
                <tr>
                    <td><strong>Server Time:</strong></td>
                    <td><?php echo date('Y-m-d H:i:s'); ?></td>
                </tr>
                <tr>
                    <td><strong>Timezone:</strong></td>
                    <td><?php echo date_default_timezone_get(); ?></td>
                </tr>
            </table>
        </div>
    </div>
</body>
</html>