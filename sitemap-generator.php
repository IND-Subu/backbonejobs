<?php
/**
 * Dynamic Jobs Sitemap Generator - Database Version
 * Fast, efficient, and SEO-optimized
 */

require_once 'api/config.php';

ob_clean();
header("Content-Type: application/xml; charset=UTF-8");
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Configuration
$baseUrl = "https://www.backbonejobs.xyz";

// Get database instance
$db = Database::getInstance();

// Start XML output
echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

try {
    // Fetch all active jobs with optimized query
    $query = "SELECT 
                id, 
                title,
                posted_date, 
                updated_at,
                is_featured,
                views
              FROM jobs 
              WHERE status = 'Active' 
              ORDER BY is_featured DESC, posted_date DESC
              LIMIT 10000";
    
    $result = $db->query($query);
    
    if (!$result) {
        throw new Exception("Database query failed");
    }
    
    $jobCount = 0;
    
    while ($job = $result->fetch_assoc()) {
        // Validate job ID
        if (empty($job['id'])) {
            continue;
        }
        
        $jobCount++;
        
        // Build job URL
        $jobUrl = $baseUrl . "/job-details.php?id=" . urlencode($job['id']);
        
        // Determine last modified date
        $lastmod = date("Y-m-d"); // Default to today
        
        if (!empty($job['updated_at']) && $job['updated_at'] !== "0000-00-00 00:00:00") {
            $lastmod = date("Y-m-d", strtotime($job['updated_at']));
        } elseif (!empty($job['posted_date']) && $job['posted_date'] !== "0000-00-00 00:00:00") {
            $lastmod = date("Y-m-d", strtotime($job['posted_date']));
        }
        
        // Calculate smart priority based on job age
        $priority = "0.80"; // Default
        $changefreq = "daily"; // Default
        
        if (!empty($job['posted_date']) && $job['posted_date'] !== "0000-00-00 00:00:00") {
            $postedTime = strtotime($job['posted_date']);
            $daysOld = (time() - $postedTime) / 86400;
            
            if ($daysOld <= 3) {
                // Very fresh jobs (0-3 days) - highest priority
                $priority = "0.95";
                $changefreq = "hourly";
            } elseif ($daysOld <= 7) {
                // Fresh jobs (4-7 days)
                $priority = "0.90";
                $changefreq = "daily";
            } elseif ($daysOld <= 14) {
                // Recent jobs (8-14 days)
                $priority = "0.85";
                $changefreq = "daily";
            } elseif ($daysOld <= 30) {
                // Month-old jobs
                $priority = "0.80";
                $changefreq = "daily";
            } elseif ($daysOld <= 60) {
                // Older jobs (31-60 days)
                $priority = "0.75";
                $changefreq = "weekly";
            } else {
                // Very old jobs (60+ days)
                $priority = "0.70";
                $changefreq = "weekly";
            }
        }
        
        // Boost priority for featured jobs
        if (!empty($job['is_featured']) && $job['is_featured'] == 1) {
            $priority = min(1.0, floatval($priority) + 0.05);
            $priority = number_format($priority, 2);
            $changefreq = "hourly"; // Featured jobs get crawled more often
        }
        
        // Boost priority for high-view jobs (popular content)
        if (!empty($job['views']) && $job['views'] >= 100) {
            $priority = min(1.0, floatval($priority) + 0.02);
            $priority = number_format($priority, 2);
        }
        
        // Output URL entry
        echo "  <url>\n";
        echo "    <loc>" . htmlspecialchars($jobUrl, ENT_XML1) . "</loc>\n";
        echo "    <lastmod>" . htmlspecialchars($lastmod) . "</lastmod>\n";
        echo "    <changefreq>" . htmlspecialchars($changefreq) . "</changefreq>\n";
        echo "    <priority>" . htmlspecialchars($priority) . "</priority>\n";
        echo "  </url>\n";
    }
    
    $result->free();
    
    // Optional: Log sitemap generation
    $logQuery = "INSERT INTO sitemap_logs (type, entries_count, generated_at) 
                 VALUES ('jobs', ?, NOW())
                 ON DUPLICATE KEY UPDATE entries_count = ?, generated_at = NOW()";
    
    if ($stmt = $db->prepare($logQuery)) {
        $stmt->bind_param('ii', $jobCount, $jobCount);
        $stmt->execute();
        $stmt->close();
    }
    
} catch (Exception $e) {
    // Fallback on error - at least output homepage
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($baseUrl . "/", ENT_XML1) . "</loc>\n";
    echo "    <lastmod>" . date("Y-m-d") . "</lastmod>\n";
    echo "    <changefreq>daily</changefreq>\n";
    echo "    <priority>1.0</priority>\n";
    echo "  </url>\n";
    
    // Log error
    error_log("Sitemap generation error: " . $e->getMessage());
}

echo "</urlset>";

// Optional: Create sitemap_logs table (run once)
/*
CREATE TABLE IF NOT EXISTS sitemap_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(50) UNIQUE,
    entries_count INT NOT NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type (type),
    INDEX idx_generated (generated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
*/
?>