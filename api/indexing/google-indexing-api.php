<?php
/**
 * Google Indexing API Integration - Composer-Free Version
 * Uses native PHP cURL instead of Google API Client Library
 * 
 * Setup Steps:
 * 1. Go to Google Cloud Console: https://console.cloud.google.com/
 * 2. Create a new project or select existing one
 * 3. Enable "Web Search Indexing API"
 * 4. Create Service Account:
 *    - Go to "IAM & Admin" > "Service Accounts"
 *    - Create new service account
 *    - Download JSON key file
 * 5. Add service account email to Google Search Console:
 *    - Go to Search Console: https://search.google.com/search-console
 *    - Select your property
 *    - Settings > Users and permissions
 *    - Add service account email as Owner
 * 6. Save JSON key file as 'google-service-account.json' in your project root
 * 7. Make sure PHP cURL extension is enabled (it's usually enabled by default)
 */

class GoogleIndexingAPI {
    private $serviceAccountPath;
    private $siteUrl;
    private $accessToken;
    private $tokenExpiry;
    
    // Google API endpoints
    const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    const INDEXING_API_URL = 'https://indexing.googleapis.com/v3/urlNotifications:publish';
    const METADATA_URL = 'https://indexing.googleapis.com/v3/urlNotifications/metadata';
    
    public function __construct() {
        // Your website URL
        $this->siteUrl = 'https://www.backbonejobs.xyz'; // Change this to your domain
        
        // Path to your service account JSON file
        $this->serviceAccountPath = dirname(__DIR__, 3) . '/secure/service-account.json';
        
        if (!file_exists($this->serviceAccountPath)) {
            throw new Exception('Service account JSON file not found at: ' . $this->serviceAccountPath);
        }
        
        // Check if cURL is available
        if (!function_exists('curl_init')) {
            throw new Exception('cURL extension is not enabled. Please enable it in php.ini');
        }
    }
    
    /**
     * Get OAuth 2.0 access token using JWT
     * @return string Access token
     */
    private function getAccessToken() {
        // Return cached token if still valid
        if ($this->accessToken && $this->tokenExpiry > time()) {
            return $this->accessToken;
        }
        
        // Load service account credentials
        $credentials = json_decode(file_get_contents($this->serviceAccountPath), true);
        
        if (!$credentials) {
            throw new Exception('Invalid service account JSON file');
        }
        
        // Create JWT (JSON Web Token)
        $now = time();
        $expiry = $now + 3600; // Token valid for 1 hour
        
        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT'
        ];
        
        $claim = [
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/indexing',
            'aud' => self::TOKEN_URL,
            'iat' => $now,
            'exp' => $expiry
        ];
        
        // Encode header and claim
        $headerEncoded = $this->base64UrlEncode(json_encode($header));
        $claimEncoded = $this->base64UrlEncode(json_encode($claim));
        
        // Create signature
        $signatureInput = $headerEncoded . '.' . $claimEncoded;
        $signature = '';
        
        // Sign with private key
        $privateKey = openssl_pkey_get_private($credentials['private_key']);
        if (!$privateKey) {
            throw new Exception('Invalid private key in service account JSON');
        }
        
        openssl_sign($signatureInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        
        // Free key only for PHP < 8.0
        if (PHP_VERSION_ID < 80000) {
            openssl_free_key($privateKey);
        }
        
        $signatureEncoded = $this->base64UrlEncode($signature);
        
        // Complete JWT
        $jwt = $signatureInput . '.' . $signatureEncoded;
        
        // Request access token
        $ch = curl_init(self::TOKEN_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception('Failed to obtain access token. Response: ' . $response);
        }
        
        $tokenData = json_decode($response, true);
        
        if (!isset($tokenData['access_token'])) {
            throw new Exception('Access token not found in response');
        }
        
        // Cache the token
        $this->accessToken = $tokenData['access_token'];
        $this->tokenExpiry = time() + 3500; // Expire 100 seconds before actual expiry
        
        return $this->accessToken;
    }
    
    /**
     * Base64 URL-safe encoding
     */
    private function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Submit URL for indexing (for new or updated content)
     * @param string $url The full URL to index
     * @return array Response from Google
     */
    public function submitUrlForIndexing($url) {
        try {
            $accessToken = $this->getAccessToken();
            
            $notification = [
                'url' => $url,
                'type' => 'URL_UPDATED'
            ];
            
            $ch = curl_init(self::INDEXING_API_URL);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($notification));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError) {
                return [
                    'success' => false,
                    'message' => 'cURL error: ' . $curlError
                ];
            }
            
            if ($httpCode === 200) {
                return [
                    'success' => true,
                    'message' => 'URL submitted successfully',
                    'response' => json_decode($response, true)
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'HTTP ' . $httpCode . ': ' . $response,
                    'http_code' => $httpCode
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Submit URL for removal (for deleted content)
     * @param string $url The full URL to remove
     * @return array Response from Google
     */
    public function removeUrl($url) {
        try {
            $accessToken = $this->getAccessToken();
            
            $notification = [
                'url' => $url,
                'type' => 'URL_DELETED'
            ];
            
            $ch = curl_init(self::INDEXING_API_URL);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($notification));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError) {
                return [
                    'success' => false,
                    'message' => 'cURL error: ' . $curlError
                ];
            }
            
            if ($httpCode === 200) {
                return [
                    'success' => true,
                    'message' => 'URL removal submitted successfully',
                    'response' => json_decode($response, true)
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'HTTP ' . $httpCode . ': ' . $response,
                    'http_code' => $httpCode
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get indexing status of a URL
     * @param string $url The full URL to check
     * @return array Status information
     */
    public function getUrlStatus($url) {
        try {
            $accessToken = $this->getAccessToken();
            
            $queryUrl = self::METADATA_URL . '?url=' . urlencode($url);
            
            $ch = curl_init($queryUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                return [
                    'success' => true,
                    'status' => json_decode($response, true)
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'HTTP ' . $httpCode . ': ' . $response
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Submit job URL for indexing
     * @param int $jobId The job ID
     * @return array Response
     */
    public function indexJob($jobId) {
        $jobUrl = $this->siteUrl . '/job-details.php?id=' . $jobId;
        return $this->submitUrlForIndexing($jobUrl);
    }
    
    /**
     * Remove job URL from index
     * @param int $jobId The job ID
     * @return array Response
     */
    public function removeJob($jobId) {
        $jobUrl = $this->siteUrl . '/job-details.php?id=' . $jobId;
        return $this->removeUrl($jobUrl);
    }
    
    /**
     * Batch submit multiple URLs (up to 100 per batch)
     * @param array $urls Array of URLs
     * @return array Results for each URL
     */
    public function batchSubmitUrls($urls) {
        $results = [];
        
        foreach ($urls as $url) {
            $results[$url] = $this->submitUrlForIndexing($url);
            
            // Add small delay to avoid rate limiting
            usleep(100000); // 0.1 second delay
        }
        
        return $results;
    }
    
    /**
     * Batch submit job IDs
     * @param array $jobIds Array of job IDs
     * @return array Results for each job
     */
    public function batchIndexJobs($jobIds) {
        $results = [];
        
        foreach ($jobIds as $jobId) {
            $results[$jobId] = $this->indexJob($jobId);
            
            // Add small delay to avoid rate limiting
            usleep(100000); // 0.1 second delay
        }
        
        return $results;
    }
}

/**
 * Log indexing attempts
 */
function logIndexingAttempt($jobId, $action, $success, $message) {
    $db = Database::getInstance();
    
    $query = "INSERT INTO indexing_log (job_id, action, success, message, created_at) 
              VALUES (?, ?, ?, ?, NOW())";
    
    $stmt = $db->prepare($query);
    $stmt->bind_param('isis', $jobId, $action, $success, $message);
    $stmt->execute();
    $stmt->close();
}

/**
 * Create indexing_log table (run this once)
 */
// function createIndexingLogTable() {
//     $db = Database::getInstance();
    
//     $sql = "CREATE TABLE IF NOT EXISTS indexing_log (
//         id INT AUTO_INCREMENT PRIMARY KEY,
//         job_id INT NOT NULL,
//         action VARCHAR(50) NOT NULL,
//         success BOOLEAN NOT NULL,
//         message TEXT,
//         created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
//         INDEX idx_job_id (job_id),
//         INDEX idx_created_at (created_at)
//     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
//     $db->query($sql);
// }

// Example usage:
/*
try {
    require_once 'config.php';
    
    $indexingAPI = new GoogleIndexingAPI();
    
    // Submit a job for indexing
    $result = $indexingAPI->indexJob(123);
    
    if ($result['success']) {
        echo "Job submitted for indexing successfully!\n";
        logIndexingAttempt(123, 'index', 1, 'Success');
    } else {
        echo "Error: " . $result['message'] . "\n";
        logIndexingAttempt(123, 'index', 0, $result['message']);
    }
    
    // Remove a job from index
    $result = $indexingAPI->removeJob(456);
    
    // Batch submit multiple jobs
    $jobIds = [1, 2, 3, 4, 5];
    $results = $indexingAPI->batchIndexJobs($jobIds);
    
    foreach ($results as $jobId => $result) {
        echo "Job $jobId: " . ($result['success'] ? 'Success' : 'Failed') . "\n";
    }
    
    // Check status of a URL
    $status = $indexingAPI->getUrlStatus('https://www.backbonejobs.xyz/job-details.php?id=123');
    print_r($status);
    
} catch (Exception $e) {
    echo "Error initializing Google Indexing API: " . $e->getMessage() . "\n";
}
*/
?>