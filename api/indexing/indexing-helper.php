<?php
/**
 * Smart Indexing Helper Functions
 * Prevents duplicate indexing and implements cooldown periods
 * 
 * Include this file in jobs.php, admin-job-posting.php, and cron/indexing-jobs.php
 */

require_once 'google-indexing-api.php';

/**
 * Check if job was recently indexed (cooldown period)
 * 
 * @param int $jobId Job ID to check
 * @param int $cooldownHours Hours to wait before re-indexing (default: 6 hours)
 * @return bool True if recently indexed (should skip), False if needs indexing
 */
function wasRecentlyIndexed($jobId, $cooldownHours = 6) {
    $db = Database::getInstance();
    
    $query = "SELECT created_at 
              FROM indexing_log 
              WHERE job_id = ? 
              AND action = 'index' 
              AND success = 1 
              ORDER BY created_at DESC 
              LIMIT 1";
    
    $stmt = $db->prepare($query);
    $stmt->bind_param('i', $jobId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        return false; // Never indexed before
    }
    
    $lastIndexed = $result->fetch_assoc()['created_at'];
    $stmt->close();
    
    // Calculate hours since last indexing
    $lastIndexedTime = strtotime($lastIndexed);
    $hoursSince = (time() - $lastIndexedTime) / 3600;
    
    // Return true if within cooldown period (should skip)
    return $hoursSince < $cooldownHours;
}

/**
 * Check if job content was actually updated
 * Only re-index if content changed
 * 
 * @param int $jobId Job ID to check
 * @return bool True if content changed, False if no changes
 */
function hasJobContentChanged($jobId) {
    $db = Database::getInstance();
    
    // Get current job data
    $query = "SELECT title, description, requirements, responsibilities, 
              location, salary_min, salary_max, status, updated_at 
              FROM jobs WHERE id = ?";
    
    $stmt = $db->prepare($query);
    $stmt->bind_param('i', $jobId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        return false; // Job doesn't exist
    }
    
    $currentData = $result->fetch_assoc();
    $stmt->close();
    
    // If no updated_at, treat as changed (first time)
    if (empty($currentData['updated_at']) || $currentData['updated_at'] === '0000-00-00 00:00:00') {
        return true;
    }
    
    // Check if updated within last hour (recently modified)
    $updatedTime = strtotime($currentData['updated_at']);
    $hoursSinceUpdate = (time() - $updatedTime) / 3600;
    
    return $hoursSinceUpdate < 1; // Content changed in last hour
}

/**
 * Smart job indexing with duplicate prevention
 * 
 * @param int $jobId Job ID to index
 * @param string $source Source of indexing request (employer/admin/cron)
 * @param bool $forceIndex Force indexing even if recently indexed
 * @return array Result with success status and message
 */
function smartIndexJob($jobId, $source = 'system', $forceIndex = false) {
    $db = Database::getInstance();
    
    // Check if job exists and is active
    $jobCheck = $db->query("SELECT status FROM jobs WHERE id = $jobId");
    if (!$jobCheck || $jobCheck->num_rows === 0) {
        return [
            'success' => false,
            'message' => 'Job not found',
            'skipped' => false
        ];
    }
    
    $job = $jobCheck->fetch_assoc();
    
    // Don't index inactive jobs
    if ($job['status'] !== 'Active') {
        return [
            'success' => false,
            'message' => 'Job is not active',
            'skipped' => true
        ];
    }
    
    // Check cooldown period (skip if recently indexed) unless forced
    if (!$forceIndex && wasRecentlyIndexed($jobId, 6)) {
        return [
            'success' => true,
            'message' => 'Skipped: Recently indexed (within 6 hours)',
            'skipped' => true
        ];
    }
    
    // For cron jobs, check if content actually changed
    if ($source === 'cron' && !$forceIndex && !hasJobContentChanged($jobId)) {
        return [
            'success' => true,
            'message' => 'Skipped: No content changes detected',
            'skipped' => true
        ];
    }
    
    // Proceed with indexing
    try {
        $indexingAPI = new GoogleIndexingAPI();
        $result = $indexingAPI->indexJob($jobId);
        
        if ($result['success']) {
            logIndexingAttempt($jobId, 'index', 1, ucfirst($source) . ': Success');
            return [
                'success' => true,
                'message' => 'Successfully indexed',
                'skipped' => false
            ];
        } else {
            logIndexingAttempt($jobId, 'index', 0, ucfirst($source) . ': ' . $result['message']);
            return [
                'success' => false,
                'message' => $result['message'],
                'skipped' => false
            ];
        }
    } catch (Exception $e) {
        logIndexingAttempt($jobId, 'index', 0, ucfirst($source) . ' Exception: ' . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Exception: ' . $e->getMessage(),
            'skipped' => false
        ];
    }
}

/**
 * Smart job removal with duplicate prevention
 * 
 * @param int $jobId Job ID to remove from index
 * @param string $source Source of removal request
 * @return array Result with success status and message
 */
function smartRemoveJob($jobId, $source = 'system') {
    // Check if already removed recently (within 24 hours)
    $db = Database::getInstance();
    
    $query = "SELECT created_at 
              FROM indexing_log 
              WHERE job_id = ? 
              AND action = 'remove' 
              AND success = 1 
              AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
              LIMIT 1";
    
    $stmt = $db->prepare($query);
    $stmt->bind_param('i', $jobId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $stmt->close();
        return [
            'success' => true,
            'message' => 'Skipped: Already removed recently',
            'skipped' => true
        ];
    }
    $stmt->close();
    
    // Proceed with removal
    try {
        $indexingAPI = new GoogleIndexingAPI();
        $result = $indexingAPI->removeJob($jobId);
        
        if ($result['success']) {
            logIndexingAttempt($jobId, 'remove', 1, ucfirst($source) . ': Success');
            return [
                'success' => true,
                'message' => 'Successfully removed',
                'skipped' => false
            ];
        } else {
            logIndexingAttempt($jobId, 'remove', 0, ucfirst($source) . ': ' . $result['message']);
            return [
                'success' => false,
                'message' => $result['message'],
                'skipped' => false
            ];
        }
    } catch (Exception $e) {
        logIndexingAttempt($jobId, 'remove', 0, ucfirst($source) . ' Exception: ' . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Exception: ' . $e->getMessage(),
            'skipped' => false
        ];
    }
}

/**
 * Batch smart indexing with rate limiting
 * 
 * @param array $jobIds Array of job IDs to index
 * @param string $source Source of batch request
 * @param int $delayMs Delay between requests in milliseconds (default: 200ms)
 * @return array Results summary
 */
function batchSmartIndexJobs($jobIds, $source = 'system', $delayMs = 200) {
    $results = [
        'total' => count($jobIds),
        'indexed' => 0,
        'skipped' => 0,
        'failed' => 0
    ];
    
    foreach ($jobIds as $jobId) {
        $result = smartIndexJob($jobId, $source, false);
        
        if ($result['skipped']) {
            $results['skipped']++;
        } else if ($result['success']) {
            $results['indexed']++;
        } else {
            $results['failed']++;
        }
        
        // Rate limiting delay
        usleep($delayMs * 1000);
    }
    
    return $results;
}

/**
 * Get indexing statistics for a job
 * 
 * @param int $jobId Job ID
 * @return array Indexing statistics
 */
function getJobIndexingStats($jobId) {
    $db = Database::getInstance();
    
    $query = "SELECT 
              COUNT(*) as total_attempts,
              SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as successful,
              SUM(CASE WHEN action = 'index' THEN 1 ELSE 0 END) as index_attempts,
              SUM(CASE WHEN action = 'remove' THEN 1 ELSE 0 END) as remove_attempts,
              MAX(created_at) as last_attempt,
              (SELECT created_at FROM indexing_log 
               WHERE job_id = ? AND action = 'index' AND success = 1 
               ORDER BY created_at DESC LIMIT 1) as last_successful_index
              FROM indexing_log 
              WHERE job_id = ?";
    
    $stmt = $db->prepare($query);
    $stmt->bind_param('ii', $jobId, $jobId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats = $result->fetch_assoc();
    $stmt->close();
    
    return $stats;
}

/**
 * Clean up old duplicate logs (optional maintenance)
 * Run this periodically to keep logs clean
 * 
 * @param int $keepDays Days to keep logs (default: 30)
 * @return int Number of deleted records
 */
function cleanupOldIndexingLogs($keepDays = 30) {
    $db = Database::getInstance();
    
    $query = "DELETE FROM indexing_log 
              WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
    
    $stmt = $db->prepare($query);
    $stmt->bind_param('i', $keepDays);
    $stmt->execute();
    $deleted = $stmt->affected_rows;
    $stmt->close();
    
    return $deleted;
}

?>