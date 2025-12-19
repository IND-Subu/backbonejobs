// Admin Dashboard JavaScript

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

// Load Admin Dashboard
async function loadAdminDashboard() {
    try {
        const userData = JSON.parse(localStorage.getItem('user_data'));
        
        if (userData) {
            // Update admin name
            if (document.getElementById('adminName')) {
                document.getElementById('adminName').textContent = userData.name || 'Admin';
            }
            if (document.getElementById('adminNameDisplay')) {
                document.getElementById('adminNameDisplay').textContent = userData.name || 'Admin';
            }
        }
        
        // Load all dashboard components
        await Promise.all([
            loadPlatformStats(),
            loadRecentActivities(),
            loadRecentFeedback(),
            loadAnalytics()
        ]);
        
    } catch (error) {
        console.error('Error loading dashboard:', error);
        showToast('Failed to load dashboard data', 'error');
    }
}

// Load Platform Statistics
async function loadPlatformStats() {
    try {
        const response = await fetch(API_URL + 'admin/stats.php', {
            headers: {
                'Authorization': 'Bearer ' + localStorage.getItem('user_token')
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            const stats = data.stats;
            
            // Animate counters
            animateCounter('totalUsers', stats.total_users || 0);
            animateCounter('totalEmployers', stats.total_employers || 0);
            animateCounter('totalJobs', stats.active_jobs || 0);
            animateCounter('totalApplications', stats.total_applications || 0);
            
            // Update additional stats
            document.getElementById('newUsers').textContent = stats.new_users_week || 0;
            document.getElementById('newEmployers').textContent = stats.new_employers_week || 0;
            document.getElementById('inactiveJobs').textContent = stats.inactive_jobs || 0;
            document.getElementById('newApplications').textContent = stats.new_applications_today || 0;
            
            // Update quick action counts
            document.getElementById('pendingCount').textContent = `${stats.pending_approvals || 0} pending`;
            document.getElementById('feedbackCount').textContent = `${stats.unread_feedback || 0} unread`;
        }
    } catch (error) {
        console.error('Error loading stats:', error);
        // Set default values
        setDefaultStats();
    }
}

// Set Default Stats on Error
function setDefaultStats() {
    document.getElementById('totalUsers').textContent = '0';
    document.getElementById('totalEmployers').textContent = '0';
    document.getElementById('totalJobs').textContent = '0';
    document.getElementById('totalApplications').textContent = '0';
    document.getElementById('newUsers').textContent = '0';
    document.getElementById('newEmployers').textContent = '0';
    document.getElementById('inactiveJobs').textContent = '0';
    document.getElementById('newApplications').textContent = '0';
    document.getElementById('pendingCount').textContent = '0 pending';
    document.getElementById('feedbackCount').textContent = '0 unread';
}

// Load Recent Activities
async function loadRecentActivities() {
    const table = document.getElementById('activitiesTable');
    if (!table) return;
    
    try {
        const response = await fetch(API_URL + 'admin/activities.php?limit=10', {
            headers: {
                'Authorization': 'Bearer ' + localStorage.getItem('user_token')
            }
        });
        
        const data = await response.json();
        
        if (data.success && data.activities && data.activities.length > 0) {
            table.innerHTML = data.activities.map(activity => {
                const activityType = getActivityType(activity.activity_type);
                return `
                    <tr>
                        <td>
                            <strong>${escapeHtml(activity.description)}</strong>
                        </td>
                        <td>${escapeHtml(activity.user_name || activity.employer_name || 'System')}</td>
                        <td>
                            <span class="badge" style="background: ${activityType.color};">
                                ${activityType.icon} ${activityType.label}
                            </span>
                        </td>
                        <td>${formatDateTime(activity.created_at)}</td>
                        <td>
                            <button class="btn-icon" onclick="viewActivityDetails('${activity.id}')" title="View Details">
                                👁️
                            </button>
                        </td>
                    </tr>
                `;
            }).join('');
        } else {
            table.innerHTML = `
                <tr>
                    <td colspan="5" style="text-align: center; padding: 2rem; color: var(--text-secondary);">
                        No recent activities
                    </td>
                </tr>
            `;
        }
    } catch (error) {
        console.error('Error loading activities:', error);
        table.innerHTML = `
            <tr>
                <td colspan="5" style="text-align: center; padding: 2rem; color: var(--error);">
                    Failed to load activities
                </td>
            </tr>
        `;
    }
}

// Get Activity Type Details
function getActivityType(type) {
    const types = {
        'user_registered': { label: 'User', icon: '👤', color: '#3b82f6' },
        'employer_registered': { label: 'Employer', icon: '🏢', color: '#10b981' },
        'job_created': { label: 'Job', icon: '💼', color: '#f59e0b' },
        'job_updated': { label: 'Job', icon: '✏️', color: '#f59e0b' },
        'job_deleted': { label: 'Job', icon: '🗑️', color: '#ef4444' },
        'application_submitted': { label: 'Application', icon: '📨', color: '#8b5cf6' },
        'application_status_updated': { label: 'Application', icon: '✅', color: '#10b981' },
        'feedback_submitted': { label: 'Feedback', icon: '💬', color: '#06b6d4' }
    };
    
    return types[type] || { label: 'System', icon: '⚙️', color: '#6b7280' };
}

// Load Recent Feedback
async function loadRecentFeedback() {
    const container = document.getElementById('feedbackList');
    if (!container) return;
    
    try {
        const response = await fetch(API_URL + 'admin/feedback.php?limit=5', {
            headers: {
                'Authorization': 'Bearer ' + localStorage.getItem('user_token')
            }
        });
        
        const data = await response.json();
        
        if (data.success && data.feedback && data.feedback.length > 0) {
            container.innerHTML = data.feedback.map(fb => `
                <div class="feedback-item ${fb.status === 'unread' ? 'unread' : ''}">
                    <div class="feedback-header">
                        <div>
                            <strong>${escapeHtml(fb.name || 'Anonymous')}</strong>
                            <span style="color: var(--text-secondary); font-size: 0.9rem;">
                                ${escapeHtml(fb.email || '')}
                            </span>
                        </div>
                        <span class="feedback-type">${getFeedbackTypeLabel(fb.feedback_type)}</span>
                    </div>
                    <p class="feedback-message">${escapeHtml(fb.message).substring(0, 150)}${fb.message.length > 150 ? '...' : ''}</p>
                    <div class="feedback-footer">
                        <span style="color: var(--text-secondary); font-size: 0.85rem;">
                            ${getTimeAgo(fb.created_at)}
                        </span>
                        <button class="btn-action" onclick="viewFeedback(${fb.id})">View Details</button>
                    </div>
                </div>
            `).join('');
        } else {
            container.innerHTML = `
                <div style="text-align: center; padding: 2rem; color: var(--text-secondary);">
                    No feedback received yet
                </div>
            `;
        }
    } catch (error) {
        console.error('Error loading feedback:', error);
        container.innerHTML = `
            <div style="text-align: center; padding: 2rem; color: var(--error);">
                Failed to load feedback
            </div>
        `;
    }
}

// Get Feedback Type Label
function getFeedbackTypeLabel(type) {
    const types = {
        'bug': '🐛 Bug Report',
        'feature': '✨ Feature Request',
        'general': '💬 General Feedback',
        'complaint': '⚠️ Complaint',
        'suggestion': '💡 Suggestion'
    };
    return types[type] || '💬 Feedback';
}

// Load Analytics
async function loadAnalytics() {
    const days = document.getElementById('analyticsRange')?.value || 7;
    
    try {
        const response = await fetch(API_URL + `admin/analytics.php?days=${days}`, {
            headers: {
                'Authorization': 'Bearer ' + localStorage.getItem('user_token')
            }
        });
        
        const data = await response.json();
        
        if (data.success && data.analytics) {
            renderChart(data.analytics);
        }
    } catch (error) {
        console.error('Error loading analytics:', error);
    }
}

// Render Chart (Simple implementation - you can use Chart.js for better charts)
function renderChart(analytics) {
    const canvas = document.getElementById('mainChart');
    if (!canvas) return;
    
    // For now, show a simple message
    // You can integrate Chart.js or any other charting library here
    const chartContainer = document.getElementById('analyticsChart');
    if (chartContainer && !analytics.length) {
        chartContainer.innerHTML = `
            <div style="text-align: center; padding: 3rem; color: var(--text-secondary);">
                <p>No analytics data available for the selected period</p>
            </div>
        `;
    }
    // TODO: Implement actual chart rendering with Chart.js
}

// View Activity Details
function viewActivityDetails(activityId) {
    window.location.href = `admin-activity-details.html?id=${activityId}`;
}

// View Feedback
function viewFeedback(feedbackId) {
    window.location.href = `admin-feedback-details.html?id=${feedbackId}`;
}

// Show Pending Approvals
function showPendingApprovals() {
    window.location.href = 'admin-approvals.html';
}

// Refresh Dashboard
function refreshDashboard() {
    showToast('Refreshing dashboard...', 'info');
    loadAdminDashboard();
}

// Export Data
async function exportData() {
    showToast('Preparing export...', 'info');

    try {
        const response = await fetch(API_URL + 'admin/export.php?type=all&format=json', {
            headers: {
                'Authorization': 'Bearer ' + localStorage.getItem('user_token')
            }
        });

        if (!response.ok) {
            showToast('Export failed', 'error');
            return;
        }

        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);

        const link = document.createElement('a');
        link.href = url;
        link.download = "complete_export.json"; 
        link.click();

        window.URL.revokeObjectURL(url);

        showToast('Export successful!', 'success');

    } catch (error) {
        console.error(error);
        showToast('Export failed', 'error');
    }
}

// Format DateTime
function formatDateTime(dateString) {
    const date = new Date(dateString);
    return date.toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Dark Mode Toggle
function setupDarkModeToggle() {
    const darkModeToggle = document.getElementById('darkModeToggle');
    const body = document.body;

    if (!darkModeToggle) return;

    if (localStorage.getItem('darkMode') === 'enabled') {
        body.setAttribute('data-theme', 'dark');
        darkModeToggle.textContent = '☀️';
    } else {
        darkModeToggle.textContent = '🌙';
    }

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

// Check Admin Auth
function checkAdminAuth() {
    const token = localStorage.getItem('user_token');
    const userType = localStorage.getItem('user_type');
    
    if (!token || userType !== 'admin') {
        showToast('Access denied. Admin privileges required.', 'error');
        setTimeout(() => {
            window.location.href = 'login.html';
        });
        return false;
    }
    
    return true;
}

// Initialize Dashboard
document.addEventListener('DOMContentLoaded', () => {
    if (!checkAdminAuth()) {
        return;
    }
    
    setupDarkModeToggle();
    
    if (window.location.pathname.includes('admin-dashboard.html')) {
        loadAdminDashboard();
    }
});