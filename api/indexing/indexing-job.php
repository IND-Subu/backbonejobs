<?php
/**
 * Cron Job: Auto-Index Jobs
 * 
 * This script runs periodically to:
 * 1. Index newly posted jobs that haven't been indexed yet
 * 2. Re-index jobs that were updated
 * 3. Remove deleted/expired jobs from Google index
 * 4. Handle failed indexing attempts
 * 
 * SETUP INSTRUCTIONS:
 * ====================
 * 
 * METHOD 1: cPanel Cron Jobs (Recommended)
 * -----------------------------------------
 * 1. Log into cPanel
 * 2. Find "Cron Jobs" under "Advanced"
 * 3. Add new cron job:
 *    - Common Settings: "Once Per Hour" or "Every 30 Minutes"
 *    - Command: /usr/bin/php /home2/backbone/public_html/cron/indexing-jobs.php
 * 
 * OR use this command format:
 *    0 * * * * /usr/bin/php /home2/backbone/public_html/cron/indexing-jobs.php
 *    (Runs every hour at minute 0)
 * 
 * METHOD 2: Manual Trigger via Web (For Testing)
 * -----------------------------------------------
 * Visit: https://www.backbonejobs.xyz/cron/indexing-jobs.php?key=YOUR_SECRET_KEY
 * 
 * METHOD 3: External Cron Service
 * --------------------------------
 * Use services like:
 * - https://cron-job.org
 * - https://easycron.com
 * - Set URL: https://www.backbonejobs.xyz/cron/indexing-jobs.php?key=YOUR_SECRET_KEY
 */

// Prevent direct browser access without key (for security)
$cronKey = '14589'; // CHANGE THIS!

if (php_sapi_name() !== 'cli') {
    // Running via web - require key
    if (!isset($_GET['key']) || $_GET['key'] !== $cronKey) {
        http_response_code(403);
        die('Access Denied');
    }
}

// Set time limit and memory for large operations
set_time_limit(300); // 5 minutes max
ini_set('memory_limit', '256M');

// Include required files
require_once dirname(__DIR__, 1) . '/config.php';
require_once __DIR__ . '/indexing-helper.php'; // Smart indexing with duplicate prevention

// Start execution
$startTime = microtime(true);
$logOutput = [];

function logMessage($message) {
    global $logOutput;
    $timestamp = date('[Y-m-d H:i:s]');
    $logOutput[] = "$timestamp $message";
    echo "$timestamp $message\n";
}

logMessage("========================================");
logMessage("Starting Indexing Cron Job");
logMessage("========================================");

$db = Database::getInstance();

try {
    // Initialize Google Indexing API
    logMessage("✓ Google Indexing API initialized");
    
    // ==========================================
    // TASK 1: Index New Jobs (not yet indexed)
    // ==========================================
    logMessage("\n--- Task 1: Indexing New Jobs ---");
    
    $newJobsQuery = "SELECT j.id, j.title, j.posted_date
                     FROM jobs j
                     LEFT JOIN indexing_log il ON j.id = il.job_id AND il.action = 'index' AND il.success = 1
                     WHERE j.status = 'Active' 
                     AND il.id IS NULL
                     AND j.posted_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                     LIMIT 50";
    
    $newJobs = $db->query($newJobsQuery);
    $newJobsCount = 0;
    $newJobsSuccess = 0;
    $newJobsSkipped = 0;
    
    if ($newJobs && $newJobs->num_rows > 0) {
        while ($job = $newJobs->fetch_assoc()) {
            $newJobsCount++;
            logMessage("Indexing Job #{$job['id']}: {$job['title']}");
            
            // Use smart indexing with duplicate prevention
            $result = smartIndexJob($job['id'], 'cron', false);
            
            if ($result['skipped']) {
                $newJobsSkipped++;
                logMessage("  ⊘ Skipped: {$result['message']}");
            } else if ($result['success']) {
                $newJobsSuccess++;
                logMessage("  ✓ Success");
            } else {
                logMessage("  ✗ Failed: {$result['message']}");
            }
            
            // Rate limiting
            usleep(100000); // 0.1 second delay
        }
    }
    
    logMessage("New jobs processed: $newJobsCount | Success: $newJobsSuccess | Skipped: $newJobsSkipped");
    
    // ==========================================
    // TASK 2: Re-index Recently Updated Jobs
    // ==========================================
    logMessage("\n--- Task 2: Re-indexing Updated Jobs ---");
    
    $updatedJobsQuery = "SELECT j.id, j.title, j.updated_at
                         FROM jobs j
                         WHERE j.status = 'Active'
                         AND j.updated_at IS NOT NULL
                         AND j.updated_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                         AND j.updated_at > j.posted_date
                         LIMIT 30";
    
    $updatedJobs = $db->query($updatedJobsQuery);
    $updatedJobsCount = 0;
    $updatedJobsSuccess = 0;
    $updatedJobsSkipped = 0;
    
    if ($updatedJobs && $updatedJobs->num_rows > 0) {
        while ($job = $updatedJobs->fetch_assoc()) {
            $updatedJobsCount++;
            logMessage("Re-indexing Job #{$job['id']}: {$job['title']}");
            
            // Use smart indexing - will skip if recently indexed AND no content change
            $result = smartIndexJob($job['id'], 'cron', false);
            
            if ($result['skipped']) {
                $updatedJobsSkipped++;
                logMessage("  ⊘ Skipped: {$result['message']}");
            } else if ($result['success']) {
                $updatedJobsSuccess++;
                logMessage("  ✓ Success");
            } else {
                logMessage("  ✗ Failed: {$result['message']}");
            }
            
            usleep(100000);
        }
    }
    
    logMessage("Updated jobs processed: $updatedJobsCount | Success: $updatedJobsSuccess | Skipped: $updatedJobsSkipped");
    
    // ==========================================
    // TASK 3: Remove Inactive/Deleted Jobs
    // ==========================================
    logMessage("\n--- Task 3: Removing Inactive Jobs ---");
    
    // Find jobs that were indexed but are now inactive or deleted
    $inactiveJobsQuery = "SELECT DISTINCT il.job_id, j.title, j.status
                          FROM indexing_log il
                          LEFT JOIN jobs j ON il.job_id = j.id
                          WHERE il.action = 'index' 
                          AND il.success = 1
                          AND (j.id IS NULL OR j.status != 'Active')
                          AND NOT EXISTS (
                              SELECT 1 FROM indexing_log il2 
                              WHERE il2.job_id = il.job_id 
                              AND il2.action = 'remove' 
                              AND il2.success = 1
                          )
                          LIMIT 20";
    
    $inactiveJobs = $db->query($inactiveJobsQuery);
    $inactiveJobsCount = 0;
    $inactiveJobsSuccess = 0;
    $inactiveJobsSkipped = 0;
    
    if ($inactiveJobs && $inactiveJobs->num_rows > 0) {
        while ($job = $inactiveJobs->fetch_assoc()) {
            $inactiveJobsCount++;
            $title = $job['title'] ?? 'Deleted Job';
            logMessage("Removing Job #{$job['job_id']}: $title");
            
            // Use smart removal with duplicate prevention
            $result = smartRemoveJob($job['job_id'], 'cron');
            
            if ($result['skipped']) {
                $inactiveJobsSkipped++;
                logMessage("  ⊘ Skipped: {$result['message']}");
            } else if ($result['success']) {
                $inactiveJobsSuccess++;
                logMessage("  ✓ Success");
            } else {
                logMessage("  ✗ Failed: {$result['message']}");
            }
            
            usleep(100000);
        }
    }
    
    logMessage("Inactive jobs processed: $inactiveJobsCount | Success: $inactiveJobsSuccess | Skipped: $inactiveJobsSkipped");
    
    // ==========================================
    // TASK 4: Retry Failed Attempts (up to 3 times)
    // ==========================================
    logMessage("\n--- Task 4: Retrying Failed Attempts ---");
    
    $failedQuery = "SELECT il.job_id, il.action, COUNT(*) as attempt_count, j.title
                    FROM indexing_log il
                    INNER JOIN jobs j ON il.job_id = j.id
                    WHERE il.success = 0
                    AND j.status = 'Active'
                    AND il.created_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
                    GROUP BY il.job_id, il.action
                    HAVING attempt_count < 3
                    LIMIT 10";
    
    $failedAttempts = $db->query($failedQuery);
    $retryCount = 0;
    $retrySuccess = 0;
    $retrySkipped = 0;
    
    if ($failedAttempts && $failedAttempts->num_rows > 0) {
        while ($attempt = $failedAttempts->fetch_assoc()) {
            $retryCount++;
            logMessage("Retrying Job #{$attempt['job_id']}: {$attempt['title']} (Attempt #{$attempt['attempt_count']})");
            
            if ($attempt['action'] === 'index') {
                $result = smartIndexJob($attempt['job_id'], 'cron', true); // Force retry
            } else {
                $result = smartRemoveJob($attempt['job_id'], 'cron');
            }
            
            if ($result['skipped']) {
                $retrySkipped++;
                logMessage("  ⊘ Skipped: {$result['message']}");
            } else if ($result['success']) {
                $retrySuccess++;
                logMessage("  ✓ Success on retry");
            } else {
                logMessage("  ✗ Retry failed");
            }
            
            usleep(100000);
        }
    }
    
    logMessage("Failed attempts retried: $retryCount | Success: $retrySuccess | Skipped: $retrySkipped");
    
    // ==========================================
    // SUMMARY
    // ==========================================
    $executionTime = round(microtime(true) - $startTime, 2);
    $totalProcessed = $newJobsCount + $updatedJobsCount + $inactiveJobsCount + $retryCount;
    $totalSuccess = $newJobsSuccess + $updatedJobsSuccess + $inactiveJobsSuccess + $retrySuccess;
    
    logMessage("\n========================================");
    logMessage("Cron Job Completed Successfully");
    logMessage("========================================");
    logMessage("Execution Time: {$executionTime}s");
    logMessage("Total Processed: $totalProcessed");
    logMessage("Total Success: $totalSuccess");
    logMessage("Success Rate: " . ($totalProcessed > 0 ? round(($totalSuccess / $totalProcessed) * 100, 1) : 0) . "%");
    
    // Save summary to database
    $summaryQuery = "INSERT INTO cron_logs (type, processed, successful, execution_time, created_at) 
                     VALUES ('indexing', ?, ?, ?, NOW())";
    $stmt = $db->prepare($summaryQuery);
    $stmt->bind_param('iid', $totalProcessed, $totalSuccess, $executionTime);
    $stmt->execute();
    $stmt->close();
    
} catch (Exception $e) {
    logMessage("\n❌ ERROR: " . $e->getMessage());
    logMessage("Stack trace: " . $e->getTraceAsString());
}

// Save log to file
$logDir = dirname(__DIR__) . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

$logFile = $logDir . '/indexing-cron-' . date('Y-m-d') . '.log';
file_put_contents($logFile, implode("\n", $logOutput) . "\n", FILE_APPEND);

logMessage("\nLog saved to: $logFile");

// Clean up old logs (keep last 30 days)
$oldLogs = glob($logDir . '/indexing-cron-*.log');
foreach ($oldLogs as $oldLog) {
    if (filemtime($oldLog) < strtotime('-30 days')) {
        unlink($oldLog);
    }
}

// Note: Database connection closes automatically
// $db->close(); // Not needed with singleton pattern

// Optional: Create cron_logs table
/*
CREATE TABLE IF NOT EXISTS cron_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(50) NOT NULL,
    processed INT NOT NULL,
    successful INT NOT NULL,
    execution_time DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type (type),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
*/
?>