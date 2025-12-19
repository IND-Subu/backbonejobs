<?php
require_once 'api/config.php';

$db = Database::getInstance();

// Get job ID from URL
$jobId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($jobId <= 0) {
    header('Location: jobs.html');
    exit;
}

// Increment view count
$updateStmt = $db->prepare("UPDATE jobs SET views = views + 1 WHERE id = ?");
$updateStmt->bind_param('i', $jobId);
$updateStmt->execute();
$updateStmt->close();

// Fetch job details
$query = "SELECT j.*, c.category_name, e.company_name, e.whatsapp_number,
          e.company_logo, e.company_description, e.is_verified
          FROM jobs j
          LEFT JOIN job_categories c ON j.category_id = c.id
          LEFT JOIN employers e ON j.employer_id = e.id
          WHERE j.id = ? AND j.status = 'Active'";

$stmt = $db->prepare($query);
$stmt->bind_param('i', $jobId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $jobNotFound = true;
    $job = null;
} else {
    $jobNotFound = false;
    $job = $result->fetch_assoc();
}
$stmt->close();

// Check if user has applied (if logged in)
$hasApplied = false;
$isSaved = false;

if (!$jobNotFound && isAuthenticated()) {
    $userId = getUserId();
    
    // Check application status
    $checkStmt = $db->prepare("SELECT id FROM applications WHERE job_id = ? AND user_id = ?");
    $checkStmt->bind_param('ii', $jobId, $userId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $hasApplied = $checkResult->num_rows > 0;
    $checkStmt->close();
    
    // Check saved status
    $savedStmt = $db->prepare("SELECT id FROM saved_jobs WHERE job_id = ? AND user_id = ?");
    $savedStmt->bind_param('ii', $jobId, $userId);
    $savedStmt->execute();
    $savedResult = $savedStmt->get_result();
    $isSaved = $savedResult->num_rows > 0;
    $savedStmt->close();
}

// Fetch similar jobs (same category)
$similarJobs = [];
if (!$jobNotFound) {
    $similarQuery = "SELECT j.*, c.category_name 
                     FROM jobs j
                     LEFT JOIN job_categories c ON j.category_id = c.id
                     WHERE j.category_id = ? AND j.id != ? AND j.status = 'Active'
                     ORDER BY j.posted_date DESC
                     LIMIT 3";
    
    $similarStmt = $db->prepare($similarQuery);
    $similarStmt->bind_param('ii', $job['category_id'], $jobId);
    $similarStmt->execute();
    $similarResult = $similarStmt->get_result();
    
    while ($row = $similarResult->fetch_assoc()) {
        $similarJobs[] = $row;
    }
    $similarStmt->close();
}

// Helper functions
function formatSalary($amount) {
    if ($amount >= 100000) {
        return number_format($amount / 100000, 1) . 'L';
    } else if ($amount >= 1000) {
        return number_format($amount / 1000, 1) . 'K';
    }
    return number_format($amount);
}

function getTimeAgo($dateString) {
    $date = new DateTime($dateString);
    $now = new DateTime();
    $interval = $now->diff($date);
    
    if ($interval->y > 0) {
        return $interval->y . ' year' . ($interval->y > 1 ? 's' : '') . ' ago';
    } elseif ($interval->m > 0) {
        return $interval->m . ' month' . ($interval->m > 1 ? 's' : '') . ' ago';
    } elseif ($interval->d > 0) {
        return $interval->d . ' day' . ($interval->d > 1 ? 's' : '') . ' ago';
    } elseif ($interval->h > 0) {
        return $interval->h . ' hour' . ($interval->h > 1 ? 's' : '') . ' ago';
    } elseif ($interval->i > 0) {
        return $interval->i . ' minute' . ($interval->i > 1 ? 's' : '') . ' ago';
    }
    return 'Just now';
}

function escapeHtml($text) {
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $jobNotFound ? 'Job Not Found' : escapeHtml($job['title']) . ' at ' . escapeHtml($job['company_name']); ?> - BackboneJobs</title>
    
    <?php if (!$jobNotFound): ?>
    <meta name="description" content="<?php echo escapeHtml(substr($job['description'], 0, 160)); ?>">
    <meta name="keywords" content="<?php echo escapeHtml($job['title'] . ', ' . $job['category_name'] . ', ' . $job['location']); ?>">
    
    <!-- Open Graph / Social Media -->
    <meta property="og:title" content="<?php echo escapeHtml($job['title'] . ' at ' . $job['company_name']); ?>">
    <meta property="og:description" content="<?php echo escapeHtml(substr($job['description'], 0, 200)); ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?>">
    
    <!-- Schema.org JSON-LD -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org/",
        "@type": "JobPosting",
        "title": <?php echo json_encode($job['title']); ?>,
        "description": <?php echo json_encode($job['description']); ?>,
        "datePosted": "<?php echo date('Y-m-d', strtotime($job['posted_date'])); ?>",
        "validThrough": "<?php echo $job['application_deadline'] ? date('Y-m-d', strtotime($job['application_deadline'])) : date('Y-m-d', strtotime($job['posted_date'] . ' +30 days')); ?>",
        "employmentType": "<?php echo strtoupper(str_replace('-', '_', $job['job_type'])); ?>",
        "hiringOrganization": {
            "@type": "Organization",
            "name": <?php echo json_encode($job['company_name']); ?>,
            "sameAs": "https://www.backbonejobs.xyz/"
        },
        "jobLocation": {
            "@type": "Place",
            "address": {
                "@type": "PostalAddress",
                "addressLocality": <?php echo json_encode($job['city'] ?: $job['location']); ?>,
                "streetAddress": <?php echo json_encode($job['location'] ?: $job['city']); ?>,
                <?php if (!empty($job['pincode'])): ?>
                    "postalCode": <?php echo json_encode($job['pincode']); ?>,
                <?php endif; ?>
                "addressRegion": <?php echo json_encode($job['state'] ?: 'IN'); ?>,
                "addressCountry": "IN"
            }
        },
        "baseSalary": {
            "@type": "MonetaryAmount",
            "currency": "INR",
            "value": {
                "@type": "QuantitativeValue",
                "minValue": <?php echo $job['salary_min']; ?>,
                "maxValue": <?php echo $job['salary_max']; ?>,
                "unitText": "MONTH"
            }
        }
    }
    </script>
    <?php endif; ?>
    
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/job-details.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="container">
            <div class="nav-brand">
                <a href="index.html" style="text-decoration: none; color: var(--primary-color);">
                    <h2>🦴 BackboneJobs</h2>
                </a>
            </div>
            <div class="nav-links">
                <a href="/">Home</a>
                <a href="jobs.html">Jobs</a>
                <a href="about.html">About</a>
                <a href="contact.html">Contact</a>
                <a href="" id="authLogin"></a>
                <button id="darkModeToggle" class="dark-toggle">🌙</button>
            </div>
            <button class="mobile-menu-btn" id="mobileMenuBtn">☰</button>
        </div>
    </nav>

    <?php if ($jobNotFound): ?>
    <!-- Error State -->
    <div class="error-container">
        <div class="error-content">
            <div class="error-icon">❌</div>
            <h2>Job Not Found</h2>
            <p>The job you're looking for doesn't exist or has been removed.</p>
            <a href="jobs.html" class="btn-primary">Browse All Jobs</a>
        </div>
    </div>
    <?php else: ?>
    
    <!-- Job Details Container -->
    <div class="job-details-container">
        <div class="container">
            <div class="job-details-layout">
                <!-- Main Content -->
                <main class="job-main-content">
                    <!-- Job Header -->
                    <div class="job-header-card">
                        <div class="job-header-top">
                            <div class="company-logo-large">
                                <?php if ($job['company_logo']): ?>
                                    <img src="<?php echo escapeHtml($job['company_logo']); ?>" alt="<?php echo escapeHtml($job['company_name']); ?>">
                                <?php else: ?>
                                    🏢
                                <?php endif; ?>
                            </div>
                            <div class="job-header-info">
                                <h1><?php echo escapeHtml($job['title']); ?></h1>
                                <p class="company-name">
                                    <?php echo escapeHtml($job['company_name']); ?>
                                    <?php if ($job['is_verified']): ?>
                                        <span style="color: #10b981;">✓ Verified</span>
                                    <?php endif; ?>
                                </p>
                                <div class="job-meta-info">
                                    <span>📍 <?php echo escapeHtml($job['location']); ?></span>
                                    <span>💼 <?php echo escapeHtml($job['job_type']); ?></span>
                                    <span>📅 <?php echo getTimeAgo($job['posted_date']); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="job-header-actions">
                            <button class="btn-save" id="btnSave" onclick="toggleSaveJob(<?php echo $jobId; ?>)">
                                <span id="saveIcon"><?php echo $isSaved ? '❤️' : '🤍'; ?></span> 
                                <?php echo $isSaved ? 'Saved' : 'Save Job'; ?>
                            </button>
                            <button class="btn-share" onclick="shareJobDetails()">
                                📤 Share
                            </button>
                        </div>
                    </div>

                    <!-- Job Description -->
                    <div class="job-section-card">
                        <h2>Job Description</h2>
                        <div class="content-text">
                            <?php echo nl2br(escapeHtml($job['description'])); ?>
                        </div>
                    </div>

                    <?php if (!empty($job['requirements'])): ?>
                    <!-- Requirements -->
                    <div class="job-section-card">
                        <h2>Requirements</h2>
                        <div class="content-text">
                            <?php echo nl2br(escapeHtml($job['requirements'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($job['responsibilities'])): ?>
                    <!-- Responsibilities -->
                    <div class="job-section-card">
                        <h2>Responsibilities</h2>
                        <div class="content-text">
                            <?php echo nl2br(escapeHtml($job['responsibilities'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($job['benefits'])): ?>
                    <!-- Benefits -->
                    <div class="job-section-card">
                        <h2>Benefits</h2>
                        <div class="content-text">
                            <?php echo nl2br(escapeHtml($job['benefits'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- About Company -->
                    <div class="job-section-card">
                        <h2>About the Company</h2>
                        <p class="content-text">
                            <?php echo nl2br(escapeHtml($job['company_description'] ?: 'No company description available.')); ?>
                        </p>
                    </div>
                </main>

                <!-- Sidebar -->
                <aside class="job-sidebar">
                    <!-- Apply Card -->
                    <div class="sidebar-card apply-card">
                        <div class="salary-display">
                            ₹<?php echo formatSalary($job['salary_min']); ?> - ₹<?php echo formatSalary($job['salary_max']); ?>
                        </div>
                        <p class="salary-label">Per Month<?php echo $job['salary_negotiable'] ? ' (Negotiable)' : ''; ?></p>
                        
                        <?php if ($hasApplied): ?>
                        <div class="applied-status">
                            <span>✅</span>
                            <p>You've already applied for this job</p>
                        </div>
                        <?php else: ?>
                        <button class="btn-apply-large" onclick="applyForJob(<?php echo $jobId; ?>)">
                            Apply Now
                        </button>
                        <?php endif; ?>
                    </div>

                    <!-- Job Details -->
                    <div class="sidebar-card">
                        <h3>Job Details</h3>
                        <div class="detail-list">
                            <div class="detail-item">
                                <span class="detail-label">💼 Job Type</span>
                                <span class="detail-value"><?php echo escapeHtml($job['job_type']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">👤 Experience</span>
                                <span class="detail-value"><?php echo escapeHtml($job['experience_required'] ?: 'Any'); ?></span>
                            </div>
                            <?php if (!empty($job['education_required'])): ?>
                            <div class="detail-item">
                                <span class="detail-label">🎓 Education</span>
                                <span class="detail-value"><?php echo escapeHtml($job['education_required']); ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="detail-item">
                                <span class="detail-label">🏢 Vacancies</span>
                                <span class="detail-value"><?php echo $job['vacancies']; ?></span>
                            </div>
                            <?php if (!empty($job['work_timings'])): ?>
                            <div class="detail-item">
                                <span class="detail-label">⏰ Work Timings</span>
                                <span class="detail-value"><?php echo escapeHtml($job['work_timings']); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($job['application_deadline']): ?>
                            <div class="detail-item">
                                <span class="detail-label">📅 Deadline</span>
                                <span class="detail-value"><?php echo date('d M Y', strtotime($job['application_deadline'])); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Contact Card -->
                    <div class="sidebar-card contact-card">
                        <h3>Contact Recruiter</h3>
                        <?php if (!empty($job['contact_email'])): ?>
                        <p class="contact-info">
                            📧 <span><?php echo escapeHtml($job['contact_email']); ?></span>
                        </p>
                        <?php endif; ?>
                        <?php if (!empty($job['contact_phone'])): ?>
                        <p class="contact-info">
                            📞 <span><?php echo escapeHtml($job['contact_phone']); ?></span>
                        </p>
                        <?php endif; ?>
                        <?php if (!empty($job['whatsapp_number'])): ?>
                        <button class="btn-whatsapp" onclick="contactViaWhatsApp('<?php echo escapeHtml($job['whatsapp_number']); ?>', '<?php echo escapeHtml($job['title']); ?>')">
                            <span>💬</span> Chat on WhatsApp
                        </button>
                        <?php endif; ?>
                    </div>

                    <!-- Report Job -->
                    <div class="sidebar-card report-card">
                        <button class="btn-report" onclick="reportJob(<?php echo $jobId; ?>)">
                            🚩 Report this job
                        </button>
                    </div>
                </aside>
            </div>

            <!-- Similar Jobs -->
            <?php if (count($similarJobs) > 0): ?>
            <div class="similar-jobs-section">
                <h2>Similar Jobs</h2>
                <div class="jobs-grid">
                    <?php foreach ($similarJobs as $similarJob): ?>
                    <div class="job-card" onclick="window.location.href='job-details.php?id=<?php echo $similarJob['id']; ?>'">
                        <div class="job-header">
                            <div>
                                <h3 class="job-title"><?php echo escapeHtml($similarJob['title']); ?></h3>
                                <p class="job-company"><?php echo escapeHtml($similarJob['company_name']); ?></p>
                            </div>
                            <span class="job-badge"><?php echo escapeHtml($similarJob['job_type']); ?></span>
                        </div>
                        <div class="job-details">
                            <div class="job-detail-item">
                                <span>📍</span>
                                <span><?php echo escapeHtml($similarJob['location']); ?></span>
                            </div>
                            <div class="job-detail-item">
                                <span>💼</span>
                                <span><?php echo escapeHtml($similarJob['experience_required'] ?: 'Any'); ?></span>
                            </div>
                            <div class="job-detail-item">
                                <span>📅</span>
                                <span><?php echo getTimeAgo($similarJob['posted_date']); ?></span>
                            </div>
                        </div>
                        <p style="color: var(--text-secondary); margin: 1rem 0;">
                            <?php echo escapeHtml(substr($similarJob['description'], 0, 100)) . '...'; ?>
                        </p>
                        <div class="job-footer">
                            <span class="job-salary">₹<?php echo formatSalary($similarJob['salary_min']); ?> - ₹<?php echo formatSalary($similarJob['salary_max']); ?></span>
                            <button class="btn-apply" onclick="event.stopPropagation(); applyForJob(<?php echo $similarJob['id']; ?>)">Apply Now</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>BackboneJobs</h3>
                    <p>Empowering support staff across India</p>
                </div>
                <div class="footer-section">
                    <h4>Quick Links</h4>
                    <a href="about.html">About Us</a>
                    <a href="contact.html">Contact</a>
                    <a href="faq.html">FAQ</a>
                </div>
                <div class="footer-section">
                    <h4>For Employers</h4>
                    <a href="employer-register.html">Post a Job</a>
                    <a href="employer-login.html">Employer Login</a>
                </div>
                <div class="footer-section">
                    <h4>Legal</h4>
                    <a href="privacy.html">Privacy Policy</a>
                    <a href="terms.html">Terms of Service</a>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2024 BackboneJobs. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="js/main.js"></script>
    <script src="js/auth.js"></script>
    
    <script>
        // Set initial saved state
        const isSaved = <?php echo $isSaved ? 'true' : 'false'; ?>;
        const hasApplied = <?php echo $hasApplied ? 'true' : 'false'; ?>;
        
        // Auth check for UI
        document.addEventListener("DOMContentLoaded", () => {
            const authLogin = document.getElementById('authLogin');
            
            if(checkAuth()){
                authLogin.textContent = "Logout";
                authLogin.href = "javascript:void(0)";
                authLogin.onclick = () => logout();
            } else {
                authLogin.textContent = "Login";
                authLogin.href = "login.html";
                authLogin.onclick = null;
            }
        });
        
        // Apply for job
        function applyForJob(jobId) {
            if (!checkAuth()) {
                showToast('Please login to apply for jobs', 'error');
                setTimeout(() => {
                    window.location.href = `login.html?redirect=job-details.php?id=${jobId}`;
                }, 1500);
                return;
            }
            window.location.href = `apply.html?job_id=${jobId}`;
        }
        
        // Toggle save job
        async function toggleSaveJob(jobId) {
            if (!checkAuth()) {
                showToast('Please login to save jobs', 'error');
                setTimeout(() => {
                    window.location.href = 'login.html';
                }, 1500);
                return;
            }
            
            try {
                const response = await fetch(API_URL + 'saved-jobs.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + localStorage.getItem('user_token')
                    },
                    body: JSON.stringify({ job_id: jobId })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    const saveIcon = document.getElementById('saveIcon');
                    const btnSave = document.getElementById('btnSave');
                    
                    if (data.saved) {
                        saveIcon.textContent = '❤️';
                        btnSave.innerHTML = '<span id="saveIcon">❤️</span> Saved';
                        showToast('Job saved!', 'success');
                    } else {
                        saveIcon.textContent = '🤍';
                        btnSave.innerHTML = '<span id="saveIcon">🤍</span> Save Job';
                        showToast('Job removed from saved', 'success');
                    }
                } else {
                    showToast(data.message || 'Failed to save job', 'error');
                }
            } catch (error) {
                console.error('Error saving job:', error);
                showToast('An error occurred', 'error');
            }
        }
        
        // Share job
        function shareJobDetails() {
            const shareUrl = window.location.href;
            const shareText = `Check out this job: <?php echo addslashes($job['title'] ?? ''); ?> at <?php echo addslashes($job['company_name'] ?? ''); ?>`;
            
            if (navigator.share) {
                navigator.share({
                    title: '<?php echo addslashes($job['title'] ?? ''); ?>',
                    text: shareText,
                    url: shareUrl
                }).catch(err => console.log('Error sharing:', err));
            } else {
                navigator.clipboard.writeText(shareUrl).then(() => {
                    showToast('Job link copied to clipboard!', 'success');
                }).catch(err => {
                    console.error('Failed to copy:', err);
                });
            }
        }
        
        // Contact via WhatsApp
        function contactViaWhatsApp(number, jobTitle) {
            const message = encodeURIComponent(`Hi, I'm interested in the ${jobTitle} position.`);
            const whatsappUrl = `https://wa.me/${number.replace(/[^0-9]/g, '')}?text=${message}`;
            window.open(whatsappUrl, '_blank');
        }
        
        // Report job
        function reportJob(jobId) {
            if (!checkAuth()) {
                showToast('Please login to report jobs', 'error');
                setTimeout(() => {
                    window.location.href = 'login.html';
                }, 1500);
                return;
            }
            
            const reason = prompt('Please specify the reason for reporting this job:');
            if (reason && reason.trim()) {
                // Here you would send the report to your backend
                showToast('Thank you for reporting. We will review this job posting.', 'success');
            }
        }
    </script>
</body>
</html>