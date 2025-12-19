<?php
/**
 * System Status Checker
 * Quick health check of entire indexing system
 */

require_once '../config.php';
requireAdmin();
$db = Database::getInstance();

$checks = [];

// Check 1: Service Account File
$checks[] = [
    'name' => 'Service Account JSON',
    'status' => file_exists('../../google-service-account.json'),
    'message' => file_exists('../../google-service-account.json') 
        ? 'File exists' 
        : 'File not found',
    'critical' => true
];

// Check 2: Service Account Protected
$ch = curl_init('https://www.backbonejobs.xyz/google-service-account.json');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_NOBODY, true);
curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$checks[] = [
    'name' => 'Service Account Security',
    'status' => $httpCode === 403,
    'message' => $httpCode === 403 
        ? 'Protected (403 Forbidden)' 
        : "Warning: HTTP $httpCode - Should be 403",
    'critical' => true
];

// Check 3: PHP Extensions
$checks[] = [
    'name' => 'cURL Extension',
    'status' => function_exists('curl_init'),
    'message' => function_exists('curl_init') ? 'Enabled' : 'Disabled',
    'critical' => true
];

$checks[] = [
    'name' => 'OpenSSL Extension',
    'status' => function_exists('openssl_sign'),
    'message' => function_exists('openssl_sign') ? 'Enabled' : 'Disabled',
    'critical' => true
];

// Check 4: Database Tables
$tables = ['indexing_log', 'cron_logs', 'jobs'];
foreach ($tables as $table) {
    $result = $db->query("SHOW TABLES LIKE '$table'");
    $checks[] = [
        'name' => "Table: $table",
        'status' => $result && $result->num_rows > 0,
        'message' => ($result && $result->num_rows > 0) ? 'Exists' : 'Missing',
        'critical' => $table === 'indexing_log' || $table === 'jobs'
    ];
}

// Check 5: Recent Indexing Activity
$recentActivity = $db->query("
    SELECT COUNT(*) as count 
    FROM indexing_log 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
")->fetch_assoc();

$checks[] = [
    'name' => 'Recent Indexing Activity (24h)',
    'status' => $recentActivity['count'] > 0,
    'message' => $recentActivity['count'] . ' operations',
    'critical' => false
];

// Check 6: Cron Job Status
$lastCron = $db->query("
    SELECT created_at 
    FROM cron_logs 
    ORDER BY created_at DESC 
    LIMIT 1
")->fetch_assoc();

if ($lastCron) {
    $lastRun = strtotime($lastCron['created_at']);
    $hoursSince = round((time() - $lastRun) / 3600, 1);
    $cronHealthy = $hoursSince < 2;
    
    $checks[] = [
        'name' => 'Cron Job',
        'status' => $cronHealthy,
        'message' => $cronHealthy 
            ? "Last run: $hoursSince hours ago" 
            : "Warning: Last run $hoursSince hours ago",
        'critical' => false
    ];
} else {
    $checks[] = [
        'name' => 'Cron Job',
        'status' => false,
        'message' => 'Never run',
        'critical' => false
    ];
}

// Check 7: Success Rate
$successRate = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as successful
    FROM indexing_log
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
")->fetch_assoc();

if ($successRate['total'] > 0) {
    $rate = round(($successRate['successful'] / $successRate['total']) * 100, 1);
    $checks[] = [
        'name' => 'Success Rate (7 days)',
        'status' => $rate >= 90,
        'message' => "$rate% ({$successRate['successful']}/{$successRate['total']})",
        'critical' => false
    ];
}

// Check 8: Sitemaps
$sitemaps = ['sitemapindex.xml', 'sitemap-static.xml', 'sitemap-generator.php'];
foreach ($sitemaps as $sitemap) {
    $checks[] = [
        'name' => "Sitemap: $sitemap",
        'status' => file_exists("../../$sitemap"),
        'message' => file_exists("../../$sitemap") ? 'Exists' : 'Missing',
        'critical' => false
    ];
}

// Calculate overall health
$critical_checks = array_filter($checks, fn($c) => $c['critical']);
$critical_passed = array_filter($critical_checks, fn($c) => $c['status']);
$all_critical_passed = count($critical_checks) === count($critical_passed);

$total_checks = count($checks);
$passed_checks = count(array_filter($checks, fn($c) => $c['status']));
$health_percentage = round(($passed_checks / $total_checks) * 100, 1);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Status - BackboneJobs</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; background: #f5f7fa; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        
        .header { background: white; padding: 30px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); text-align: center; }
        .header h1 { font-size: 28px; color: #333; margin-bottom: 10px; }
        
        .health-score { font-size: 48px; font-weight: bold; margin: 20px 0; }
        .health-score.good { color: #10b981; }
        .health-score.warning { color: #f59e0b; }
        .health-score.critical { color: #ef4444; }
        
        .status-badge { display: inline-block; padding: 8px 16px; border-radius: 20px; font-size: 14px; font-weight: 600; }
        .status-badge.operational { background: #d1fae5; color: #065f46; }
        .status-badge.degraded { background: #fef3c7; color: #92400e; }
        .status-badge.down { background: #fee2e2; color: #991b1b; }
        
        .checks { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .check-item { display: flex; justify-content: space-between; align-items: center; padding: 15px; border-bottom: 1px solid #f1f3f4; }
        .check-item:last-child { border-bottom: none; }
        
        .check-name { font-weight: 500; color: #333; display: flex; align-items: center; gap: 10px; }
        .check-name .icon { font-size: 20px; }
        .check-name .critical-badge { background: #fee2e2; color: #991b1b; padding: 2px 8px; border-radius: 4px; font-size: 11px; }
        
        .check-result { display: flex; align-items: center; gap: 10px; }
        .check-status { font-weight: 600; }
        .check-status.pass { color: #10b981; }
        .check-status.fail { color: #ef4444; }
        
        .check-message { color: #666; font-size: 14px; }
        
        .actions { margin-top: 20px; display: flex; gap: 10px; flex-wrap: wrap; }
        .btn { display: inline-block; padding: 12px 24px; background: #667eea; color: white; text-decoration: none; border-radius: 8px; font-weight: 500; }
        .btn:hover { background: #5568d3; }
        .btn-secondary { background: #6b7280; }
        .btn-secondary:hover { background: #4b5563; }
        
        @media (max-width: 768px) {
            .check-item { flex-direction: column; align-items: flex-start; gap: 10px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 System Status Check</h1>
            
            <div class="health-score <?php 
                echo $health_percentage >= 90 ? 'good' : 
                    ($health_percentage >= 70 ? 'warning' : 'critical'); 
            ?>">
                <?php echo $health_percentage; ?>%
            </div>
            
            <div>
                <span class="status-badge <?php 
                    echo $all_critical_passed && $health_percentage >= 90 ? 'operational' : 
                        ($all_critical_passed && $health_percentage >= 70 ? 'degraded' : 'down'); 
                ?>">
                    <?php 
                    echo $all_critical_passed && $health_percentage >= 90 ? '✓ All Systems Operational' : 
                        ($all_critical_passed && $health_percentage >= 70 ? '⚠ Partially Degraded' : '✗ Critical Issues'); 
                    ?>
                </span>
            </div>
            
            <p style="margin-top: 15px; color: #666;">
                <?php echo $passed_checks; ?> of <?php echo $total_checks; ?> checks passed
            </p>
        </div>
        
        <div class="checks">
            <?php foreach ($checks as $check): ?>
                <div class="check-item">
                    <div class="check-name">
                        <span class="icon"><?php echo $check['status'] ? '✅' : '❌'; ?></span>
                        <span>
                            <?php echo $check['name']; ?>
                            <?php if ($check['critical']): ?>
                                <span class="critical-badge">CRITICAL</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="check-result">
                        <span class="check-message"><?php echo $check['message']; ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="actions">
            <a href="cron-dashboard.php" class="btn">📊 Cron Dashboard</a>
            <a href="indexing-report.php" class="btn">📋 Indexing Report</a>
            <a href="../../test-indexing.php" class="btn btn-secondary">🧪 Test API</a>
            <a href="?refresh=1" class="btn btn-secondary">🔄 Refresh</a>
        </div>
        
        <div style="text-align: center; margin-top: 20px; color: #999; font-size: 14px;">
            Last checked: <?php echo date('Y-m-d H:i:s'); ?>
        </div>
    </div>
</body>
</html>