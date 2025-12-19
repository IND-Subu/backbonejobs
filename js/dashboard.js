// Dashboard JavaScript

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
async function loadDashboardData() {
    try {
        const userData = JSON.parse(localStorage.getItem('user_data'));
        
        if (userData) {
            // Update user name
            if (document.getElementById('userName')) {
                document.getElementById('userName').textContent = userData.name.split(' ')[0];
            }
            if (document.getElementById('userNameDisplay')) {
                document.getElementById('userNameDisplay').textContent = userData.name.split(' ')[0];
            }
        }
        
        // Load application statistics
        await loadApplicationStats();
        
        // Load recent applications
        await loadRecentApplications();
        
        // Load recommended jobs
        await loadRecommendedJobs();
        
        // Calculate profile completion
        calculateProfileCompletion(userData);
        
    } catch (error) {
        console.error('Error loading dashboard:', error);
    }
}

// Load Application Statistics
async function loadApplicationStats() {
    try {
        const response = await fetch(API_URL + 'stats.php', {
            headers: {
                'Authorization': 'Bearer ' + localStorage.getItem('user_token')
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            const stats = data.stats;
            
            if (document.getElementById('totalApplications')) {
                animateCounter('totalApplications', stats.total || 0);
            }
            if (document.getElementById('shortlisted')) {
                animateCounter('shortlisted', stats.shortlisted || 0);
            }
            if (document.getElementById('pending')) {
                animateCounter('pending', stats.pending || 0);
            }
            if (document.getElementById('rejected')) {
                animateCounter('rejected', stats.rejected || 0);
            }
        }
    } catch (error) {
        console.error('Error loading stats:', error);
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
        
        if (data.success && data.applications.length > 0) {
            table.innerHTML = data.applications.map(app => `
                <tr>
                    <td><strong>${escapeHtml(app.job_title)}</strong></td>
                    <td>${escapeHtml(app.company_name)}</td>
                    <td>${new Date(app.applied_date).toLocaleDateString()}</td>
                    <td><span class="status-badge status-${app.status.toLowerCase().replace(' ', '-')}">${app.status}</span></td>
                    <td>
                        <button class="btn-action" onclick="viewApplication(${app.id})">View</button>
                    </td>
                </tr>
            `).join('');
        } else {
            table.innerHTML = `
                <tr>
                    <td colspan="5" style="text-align: center; padding: 2rem;">
                        <p style="color: var(--text-secondary);">No applications yet.</p>
                        <a href="jobs.html" class="btn-primary" style="margin-top: 1rem; display: inline-block;">Browse Jobs</a>
                    </td>
                </tr>
            `;
        }
    } catch (error) {
        console.error('Error loading applications:', error);
        table.innerHTML = `
            <tr>
                <td colspan="5" style="text-align: center; padding: 2rem; color: var(--error);">
                    Failed to load applications
                </td>
            </tr>
        `;
    }
}

// Load Recommended Jobs
async function loadRecommendedJobs() {
    const container = document.getElementById('recommendedJobs');
    if (!container) return;
    
    try {
        const response = await fetch(API_URL + 'jobs.php?limit=3');
        const data = await response.json();
        
        if (data.success && data.jobs.length > 0) {
            container.innerHTML = data.jobs.map(job => createJobCard(job)).join('');
        } else {
            container.innerHTML = '<p style="text-align: center; color: var(--text-secondary);">No recommended jobs available</p>';
        }
    } catch (error) {
        console.error('Error loading recommended jobs:', error);
        container.innerHTML = '<p style="text-align: center; color: var(--error);">Failed to load jobs</p>';
    }
}

// Calculate Profile Completion
function calculateProfileCompletion(userData) {
    if (!userData) return;
    
    let completedFields = 0;
    const totalFields = 10;
    
    const fields = [
        'name', 'email', 'phone', 'date_of_birth', 'address', 
        'city', 'experience_years', 'education', 'skills', 'resume_path'
    ];
    
    fields.forEach(field => {
        if (userData[field] && userData[field] !== '' && userData[field] !== null) {
            completedFields++;
        }
    });
    
    const percentage = Math.round((completedFields / totalFields) * 100);
    
    const progressFill = document.getElementById('profileProgress');
    const progressText = document.getElementById('profilePercentage');
    
    if (progressFill) {
        progressFill.style.width = percentage + '%';
    }
    if (progressText) {
        progressText.textContent = percentage;
    }
}

// View Application Details
function viewApplication(applicationId) {
    window.location.href = `application-details.html?id=${applicationId}`;
}

// Track Application
async function trackApplication(jobId) {
    try {
        const response = await fetch(API_URL + `applications.php?job_id=${jobId}`, {
            headers: {
                'Authorization': 'Bearer ' + localStorage.getItem('user_token')
            }
        });
        
        const data = await response.json();
        
        if (data.success && data.application) {
            showApplicationStatus(data.application);
        } else {
            showToast('Application not found', 'error');
        }
    } catch (error) {
        console.error('Error tracking application:', error);
        showToast('Failed to track application', 'error');
    }
}

// Show Application Status Modal
function showApplicationStatus(application) {
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h2>Application Status</h2>
                <button class="modal-close" onclick="this.closest('.modal').remove()">×</button>
            </div>
            <div class="modal-body">
                <div class="status-timeline">
                    <div class="timeline-item ${application.status === 'Pending' ? 'active' : 'completed'}">
                        <div class="timeline-icon">📝</div>
                        <div class="timeline-content">
                            <h4>Application Submitted</h4>
                            <p>${new Date(application.applied_date).toLocaleString()}</p>
                        </div>
                    </div>
                    <div class="timeline-item ${['Reviewed', 'Shortlisted', 'Hired'].includes(application.status) ? 'completed' : ''}">
                        <div class="timeline-icon">👀</div>
                        <div class="timeline-content">
                            <h4>Under Review</h4>
                            <p>${application.reviewed_date ? new Date(application.reviewed_date).toLocaleString() : 'Pending'}</p>
                        </div>
                    </div>
                    <div class="timeline-item ${application.status === 'Shortlisted' || application.status === 'Hired' ? 'completed' : ''}">
                        <div class="timeline-icon">✅</div>
                        <div class="timeline-content">
                            <h4>Shortlisted</h4>
                            <p>${application.status === 'Shortlisted' || application.status === 'Hired' ? 'Completed' : 'Pending'}</p>
                        </div>
                    </div>
                    <div class="timeline-item ${application.status === 'Hired' ? 'completed' : ''}">
                        <div class="timeline-icon">🎉</div>
                        <div class="timeline-content">
                            <h4>Hired</h4>
                            <p>${application.status === 'Hired' ? 'Congratulations!' : 'Pending'}</p>
                        </div>
                    </div>
                </div>
                ${application.notes ? `<div class="notes-box"><strong>Notes:</strong><p>${escapeHtml(application.notes)}</p></div>` : ''}
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Add modal styles
    if (!document.getElementById('modal-styles')) {
        const style = document.createElement('style');
        style.id = 'modal-styles';
        style.textContent = `
            .modal {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 10000;
                padding: 1rem;
            }
            .modal-content {
                background: var(--background);
                border-radius: 12px;
                max-width: 600px;
                width: 100%;
                max-height: 90vh;
                overflow-y: auto;
            }
            .modal-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 1.5rem;
                border-bottom: 1px solid var(--border);
            }
            .modal-close {
                background: none;
                border: none;
                font-size: 2rem;
                cursor: pointer;
                color: var(--text-secondary);
            }
            .modal-body {
                padding: 1.5rem;
            }
            .status-timeline {
                display: flex;
                flex-direction: column;
                gap: 1rem;
            }
            .timeline-item {
                display: flex;
                gap: 1rem;
                opacity: 0.5;
            }
            .timeline-item.active,
            .timeline-item.completed {
                opacity: 1;
            }
            .timeline-icon {
                font-size: 2rem;
            }
            .timeline-content h4 {
                margin-bottom: 0.25rem;
            }
            .timeline-content p {
                color: var(--text-secondary);
                font-size: 0.9rem;
            }
            .notes-box {
                margin-top: 1.5rem;
                padding: 1rem;
                background: var(--surface);
                border-radius: 8px;
            }
        `;
        document.head.appendChild(style);
    }
}

// Initialize Dashboard
document.addEventListener('DOMContentLoaded', () => {
    if (window.location.pathname.includes('dashboard.html')) {
        loadDashboardData();
    }
});