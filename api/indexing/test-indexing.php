<?php
/**
 * Test Google Indexing API - No Composer Version
 * Run this file to test if your setup is working correctly
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__.'/../config.php';
require_once 'google-indexing-api.php';

try {
    // Initialize API
    echo "1. Initializing Google Indexing API...";
    $indexingAPI = new GoogleIndexingAPI();
    echo "   ✅ API initialized successfully!;";
    
    // Test 1: Submit a job for indexing
    echo "2. Testing: Submit Job for Indexing\n";
    $testJobId = 177; // Change this to a real job ID from your database
    echo "   Testing with Job ID: $testJobId\n";
    
    $result = $indexingAPI->indexJob($testJobId);
    
    if ($result['success']) {
        echo "   ✅ SUCCESS: Job submitted for indexing!\n";
        echo "   Response: " . json_encode($result['response'], JSON_PRETTY_PRINT) . "\n\n";
    } else {
        echo "   ❌ FAILED: " . $result['message'] . "\n\n";
    }
    
    // Test 2: Check URL status (if first test succeeded)
    if ($result['success']) {
        echo "3. Testing: Check URL Status\n";
        $testUrl = 'https://www.backbonejobs.xyz/job-details.php?id=' . $testJobId;
        echo "   Checking URL: $testUrl\n";
        
        $statusResult = $indexingAPI->getUrlStatus($testUrl);
        
        if ($statusResult['success']) {
            echo "   ✅ SUCCESS: Got URL status!\n";
            echo "   Status: " . json_encode($statusResult['status'], JSON_PRETTY_PRINT) . "\n\n";
        } else {
            echo "   ⚠️  Status check failed (this is normal for new URLs): " . $statusResult['message'] . "\n\n";
        }
    }
    
    // Test 3: Batch submit (optional - comment out if not needed)
    /*
    echo "4. Testing: Batch Submit Jobs\n";
    $jobIds = [1, 2, 3]; // Change to real job IDs
    echo "   Submitting jobs: " . implode(', ', $jobIds) . "\n";
    
    $batchResults = $indexingAPI->batchIndexJobs($jobIds);
    
    foreach ($batchResults as $jobId => $batchResult) {
        if ($batchResult['success']) {
            echo "   ✅ Job $jobId: Success\n";
        } else {
            echo "   ❌ Job $jobId: Failed - " . $batchResult['message'] . "\n";
        }
    }
    echo "\n";
    */
    
    echo "========================================\n";
    echo "Test Completed!\n";
    echo "========================================\n\n";
    
    // Check database logging
    if (isset($db)) {
        echo "Checking indexing logs in database...\n";
        $logQuery = "SELECT * FROM indexing_log ORDER BY created_at DESC LIMIT 5";
        $logResult = $db->query($logQuery);
        
        if ($logResult && $logResult->num_rows > 0) {
            echo "Recent indexing attempts:\n";
            while ($log = $logResult->fetch_assoc()) {
                $status = $log['success'] ? '✅' : '❌';
                echo "$status Job {$log['job_id']} - {$log['action']} - {$log['created_at']}\n";
                echo "   Message: {$log['message']}\n";
            }
        } else {
            echo "No logs found in database (table might not exist yet)\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>