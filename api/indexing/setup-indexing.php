<?php
/**
 * Setup Script for Google Indexing API
 * Run this once to create necessary database tables
 */

require_once __DIR__ .'/../config.php';

echo "========================================\n";
echo "Google Indexing API Setup\n";
echo "========================================\n\n";

$db = Database::getInstance();

// Create indexing_log table
echo "1. Creating indexing_log table...\n";
$sql1 = "CREATE TABLE IF NOT EXISTS indexing_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    success BOOLEAN NOT NULL,
    message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_job_id (job_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($db->query($sql1)) {
    echo "   ✅ Table 'indexing_log' created successfully!\n\n";
} else {
    echo "   ❌ Error: " . $db->error . "\n\n";
}

// Check if updated_at column exists in jobs table
echo "2. Checking/Adding updated_at column to jobs table...\n";
$checkColumn = $db->query("SHOW COLUMNS FROM jobs LIKE 'updated_at'");

if ($checkColumn->num_rows == 0) {
    $sql2 = "ALTER TABLE jobs 
             ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
             AFTER posted_date";
    
    if ($db->query($sql2)) {
        echo "   ✅ Column 'updated_at' added successfully!\n\n";
    } else {
        echo "   ❌ Error: " . $db->error . "\n\n";
    }
} else {
    echo "   ✅ Column 'updated_at' already exists!\n\n";
}

// Create sitemap_logs table (optional but useful)
echo "3. Creating sitemap_logs table...\n";
$sql3 = "CREATE TABLE IF NOT EXISTS sitemap_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(50) UNIQUE,
    entries_count INT NOT NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type (type),
    INDEX idx_generated (generated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($db->query($sql3)) {
    echo "   ✅ Table 'sitemap_logs' created successfully!\n\n";
} else {
    echo "   ❌ Error: " . $db->error . "\n\n";
}

echo "========================================\n";
echo "Setup Complete!\n";
echo "========================================\n\n";

// Check PHP requirements
echo "Checking PHP Requirements:\n";
echo "========================================\n";

// Check PHP version
$phpVersion = phpversion();
echo "PHP Version: $phpVersion ";
if (version_compare($phpVersion, '7.0.0', '>=')) {
    echo "✅\n";
} else {
    echo "❌ (Need 7.0 or higher)\n";
}

// Check cURL
echo "cURL Extension: ";
if (extension_loaded('curl')) {
    echo "✅ Enabled\n";
} else {
    echo "❌ Not enabled (Required!)\n";
}

// Check OpenSSL
echo "OpenSSL Extension: ";
if (extension_loaded('openssl')) {
    echo "✅ Enabled\n";
} else {
    echo "❌ Not enabled (Required!)\n";
}

// Check JSON
echo "JSON Extension: ";
if (extension_loaded('json')) {
    echo "✅ Enabled\n";
} else {
    echo "❌ Not enabled (Required!)\n";
}

// Check if service account file exists
echo "\nChecking Files:\n";
echo "========================================\n";
$serviceAccountPath = dirname(__DIR__, 3). '/secure/service-account.json';
echo "Service Account JSON: ";
if (file_exists($serviceAccountPath)) {
    echo "✅ Found\n";
    
    // Validate JSON
    $json = @file_get_contents($serviceAccountPath);
    $data = @json_decode($json, true);
    
    if ($data && isset($data['client_email']) && isset($data['private_key'])) {
        echo "   Email: " . $data['client_email'] . "\n";
        echo "   ✅ Valid JSON format\n";
    } else {
        echo "   ❌ Invalid JSON format\n";
    }
} else {
    echo "❌ Not found\n";
    echo "   Please upload google-service-account.json to: $serviceAccountPath\n";
}

echo "\n========================================\n";
echo "Next Steps:\n";
echo "========================================\n";
echo "1. If service account JSON is missing, upload it\n";
echo "2. Update google-indexing-api.php with your domain URL\n";
echo "3. Run test-indexing.php to verify setup\n";
echo "4. Update your jobs.php to include auto-indexing\n";
echo "5. Submit sitemap.xml to Google Search Console\n";
echo "\n";
?>