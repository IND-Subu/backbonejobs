// Employer Dashboard JavaScript

// User Menu Toggle
const userMenuBtn = document.getElementById('userMenuBtn');
const userDropdown = document.getElementById('userDropdown');

if (userMenuBtn) {
    userMenuBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        userDropdown.classList.toggle('show');
    });
    
    document.addEventListener('click', () => {
        userDropdown.classList.remove('show');
    });
}

// Load Dashboard Data
async function loadEmployerDashboard() {
    try {
        const userData = JSON.parse(localStorage.getItem('user_data'));
        
        if (userData) {
            // Update company name displays
            if (document.getElementById('companyName')) {
                document.getElementById('companyName').textContent = userData.company_name || 'Company';
            }
            if (document.getElementById('companyNameDisplay')) {
                document.getElementById('companyNameDisplay').textContent = userData.company_name || 'Company';
            }
        }
        
        // Load all dashboard components
        await Promise.all([
            loadEmployerStats(),
            loadRecentApplications(),
            loadActiveJobs()
        ]);
        
    } catch (error) {
        console.error('Error loading dashboard:', error);
        showToast('Failed to load dashboard data', 'error');
    }
}

// Load Employer Statistics
async function loadEmployerStats() {
    try {
        const response = await fetch(API_URL + 'employer/stats.php', {
            headers: {
                'Authorization': 'Bearer ' + localStorage.getItem('user_token')
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            const stats = data.stats;
            
            // Animate counters
            if (document.getElementById('totalJobs')) {
                animateCounter('totalJobs', stats.active_jobs || 0); // replaced total_jobs with active_jobs
            }
            if (document.getElementById('totalApplications')) {
                animateCounter('totalApplications', stats.total_applications || 0);
            }
            if (document.getElementById('pendingApplications')) {
                animateCounter('pendingApplications', stats.pending_applications || 0);
            }
            if (document.getElementById('totalViews')) {
                animateCounter('totalViews', stats.total_views || 0);
            }
        } else {
            console.error('Failed to load stats:', data.message);
        }
    } catch (error) {
        console.error('Error loading stats:', error);
        // Set default values on error
        if (document.getElementById('totalJobs')) document.getElementById('totalJobs').textContent = '0';
        if (document.getElementById('totalApplications')) document.getElementById('totalApplications').textContent = '0';
        if (document.getElementById('pendingApplications')) document.getElementById('pendingApplications').textContent = '0';
        if (document.getElementById('totalViews')) document.getElementById('totalViews').textContent = '0';
    }
}

// Load Recent Applications
async function loadRecentApplications() {
    const table = document.getElementById('applicationsTable');
    if (!table) return;
    
    try {
        const response = await fetch(API_URL + 'applications.php?limit=5', {
            headers: {
                'Authorization': 'Bearer ' + localStorage.getItem('user_token')
            }
        });
        
        const data = await response.json();
        
        if (data.success && data.applications && data.applications.length > 0) {
            table.innerHTML = data.applications.map(app => `
                <tr>
                    <td><strong>${escapeHtml(app.applicant_name || 'N/A')}</strong></td>
                    <td>${escapeHtml(app.job_title || 'N/A')}</td>
                    <td>${new Date(app.applied_date).toLocaleDateString()}</td>
                    <td>
                        <span class="status-badge status-${app.status ? app.status.toLowerCase() : 'pending'}">
                            ${app.status || 'Pending'}
                        </span>
                    </td>
                    <td>
                        <button class="btn-action" onclick="viewApplication(${app.id})">View</button>
                    </td>
                </tr>
            `).join('');
        } else {
            table.innerHTML = `
                <tr>
                    <td colspan="5" style="text-align: center; padding: 2rem;">
                        <p style="color: var(--text-secondary);">No applications received yet.</p>
                        <a href="post-job.html" class="btn-primary" style="margin-top: 1rem; display: inline-block;">Post a Job</a>
                    </td>
                </tr>
            `;
        }
    } catch (error) {
        console.error('Error loading applications:', error);
        table.innerHTML = `
            <tr>
                <td colspan="5" style="text-align: center; padding: 2rem; color: var(--error);">
                    Failed to load applications. Please try again later.
                </td>
            </tr>
        `;
    }
}

// Load Active Jobs
async function loadActiveJobs() {
    const container = document.getElementById('activeJobs');
    if (!container) return;
    
    try {
        const response = await fetch(API_URL + 'employer/jobs.php?limit=3', {
            headers: {
                'Authorization': 'Bearer ' + localStorage.getItem('user_token')
            }
        });
        
        const data = await response.json();
        
        if (data.success && data.jobs && data.jobs.length > 0) {
            container.innerHTML = data.jobs.map(job => createEmployerJobCard(job)).join('');
        } else {
            container.innerHTML = `
                <div style="text-align: center; padding: 3rem; grid-column: 1 / -1;">
                    <p style="color: var(--text-secondary); margin-bottom: 1rem;">You haven't posted any jobs yet.</p>
                    <a href="post-job.html" class="btn-primary">Post Your First Job</a>
                </div>
            `;
        }
    } catch (error) {
        console.error('Error loading jobs:', error);
        container.innerHTML = `
            <div style="text-align: center; padding: 3rem; color: var(--error); grid-column: 1 / -1;">
                Failed to load jobs. Please try again later.
            </div>
        `;
    }
}

// Create Employer Job Card
function createEmployerJobCard(job) {
    const applicationCount = job.application_count || 0;
    const views = job.views || 0;
    
    return `
        <div class="job-card" style="cursor: pointer;" onclick="window.location.href='job-details.php?id=${job.id}'">
            <div class="job-header">
                <div>
                    <h3 class="job-title">${escapeHtml(job.title)}</h3>
                    <p class="job-company">${escapeHtml(job.company_name)}</p>
                </div>
                <span class="job-badge ${job.status === 'Active' ? 'status-hired' : 'status-pending'}">
                    ${job.status}
                </span>
            </div>
            <div class="job-details">
                <div class="job-detail-item">
                    <span>📍</span>
                    <span>${escapeHtml(job.location)}</span>
                </div>
                <div class="job-detail-item">
                    <span>💼</span>
                    <span>${escapeHtml(job.job_type)}</span>
                </div>
                <div class="job-detail-item">
                    <span>📅</span>
                    <span>${getTimeAgo(job.posted_date)}</span>
                </div>
            </div>
            <div class="job-footer" style="margin-top: 1rem; display: flex; justify-content: space-between; align-items: center;">
                <div style="display: flex; gap: 1rem; font-size: 0.9rem; color: var(--text-secondary);">
                    <span>📨 ${applicationCount} applications</span>
                    <span>👁️ ${views} views</span>
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <button class="btn-action" onclick="event.stopPropagation(); editJob(${job.id})" style="font-size: 0.875rem;">
                        Edit
                    </button>
                    <button class="btn-action" onclick="event.stopPropagation(); viewJobApplications(${job.id})" style="font-size: 0.875rem; background: var(--secondary-color);">
                        Applications
                    </button>
                </div>
            </div>
        </div>
    `;
}

// View Application Details
function viewApplication(applicationId) {
    window.location.href = `employer-application-details.html?id=${applicationId}`;
}

// View Job Applications
function viewJobApplications(jobId) {
    window.location.href = `applications-received.html?job_id=${jobId}`;
}

// Edit Job
function editJob(jobId) {
    window.location.href = `edit-job.html?id=${jobId}`;
}

// Delete Job (with confirmation)
async function deleteJob(jobId, jobTitle) {
    if (!confirm(`Are you sure you want to delete "${jobTitle}"? This action cannot be undone.`)) {
        return;
    }
    
    try {
        const response = await fetch(API_URL + 'jobs.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + localStorage.getItem('user_token')
            },
            body: JSON.stringify({ id: jobId })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast('Job deleted successfully', 'success');
            loadActiveJobs(); // Reload jobs list
            loadEmployerStats(); // Refresh stats
        } else {
            showToast(data.message || 'Failed to delete job', 'error');
        }
    } catch (error) {
        console.error('Error deleting job:', error);
        showToast('An error occurred while deleting the job', 'error');
    }
}

// Update Application Status (Quick action from dashboard)
async function updateApplicationStatus(applicationId, newStatus) {
    try {
        const response = await fetch(API_URL + 'applications.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + localStorage.getItem('user_token')
            },
            body: JSON.stringify({
                application_id: applicationId,
                status: newStatus
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast('Application status updated successfully', 'success');
            loadRecentApplications(); // Reload applications
            loadEmployerStats(); // Refresh stats
        } else {
            showToast(data.message || 'Failed to update status', 'error');
        }
    } catch (error) {
        console.error('Error updating status:', error);
        showToast('An error occurred while updating the status', 'error');
    }
}

// Show Quick Stats Modal
function showQuickStats() {
    // This can be expanded to show a detailed stats modal
    alert('Detailed statistics view coming soon!');
}

// Export Applications to CSV
function exportApplications() {
    // This can be implemented to export applications data
    showToast('Export feature coming soon!', 'info');
}

// Dark Mode Toggle
function setupDarkModeToggle() {
    const darkModeToggle = document.getElementById('darkModeToggle');
    const body = document.body;

    if (!darkModeToggle) return;

    // Set initial state
    if (localStorage.getItem('darkMode') === 'enabled') {
        body.setAttribute('data-theme', 'dark');
        darkModeToggle.textContent = '☀️';
    } else {
        darkModeToggle.textContent = '🌙';
    }

    // Add click listener
    darkModeToggle.addEventListener('click', () => {
        if (body.getAttribute('data-theme') === 'dark') {
            body.removeAttribute('data-theme');
            localStorage.setItem('darkMode', 'disabled');
            darkModeToggle.textContent = '🌙';
        } else {
            body.setAttribute('data-theme', 'dark');
            localStorage.setItem('darkMode', 'enabled');
            darkModeToggle.textContent = '☀️';
        }
    });
}

// Initialize Dashboard
document.addEventListener('DOMContentLoaded', () => {
    // Check if user is authenticated and is an employer
    if (!checkAuth()) {
        window.location.href = 'login.html';
        return;
    }
    
    const userType = localStorage.getItem('user_type');
    if (userType !== 'employer') {
        showToast('Access denied. Employer account required.', 'error');
        setTimeout(() => {
            window.location.href = 'dashboard.html';
        }, 2000);
        return;
    }
    
    // Setup dark mode
    setupDarkModeToggle();
    
    // Load dashboard data
    if (window.location.pathname.includes('employer-dashboard.html')) {
        loadEmployerDashboard();
    }
});

// Refresh Dashboard
function refreshDashboard() {
    showToast('Refreshing dashboard...', 'info');
    loadEmployerDashboard();
}

// Check Auth function (if not already defined in auth.js)
function checkAuth() {
    const token = localStorage.getItem('user_token');
    return token !== null;
}