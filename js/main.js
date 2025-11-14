// API Configuration
const API_URL = 'api/';

// Dark Mode Toggle
const darkModeToggle = document.getElementById('darkModeToggle');
const body = document.body;

// Load dark mode preference
if (localStorage.getItem('darkMode') === 'enabled') {
    body.setAttribute('data-theme', 'dark');
    if (darkModeToggle) darkModeToggle.textContent = '☀️';
}

if (darkModeToggle) {
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

// Mobile Menu Toggle
const mobileMenuBtn = document.getElementById('mobileMenuBtn');
const mobileMenu = document.getElementById('mobileMenu');

if (mobileMenuBtn) {
    mobileMenuBtn.addEventListener('click', () => {
        mobileMenu.classList.toggle('active');
    });
}

// Load Statistics
async function loadStats() {
    try {
        const response = await fetch(API_URL + 'stats.php');
        const data = await response.json();
        
        if (data.success) {
            if (document.getElementById('totalJobs')) {
                animateCounter('totalJobs', data.stats.total_jobs);
            }
            if (document.getElementById('totalCompanies')) {
                animateCounter('totalCompanies', data.stats.total_companies);
            }
            if (document.getElementById('totalApplications')) {
                animateCounter('totalApplications', data.stats.total_applications);
            }
        }
    } catch (error) {
        console.error('Error loading stats:', error);
    }
}

// Animate Counter
function animateCounter(elementId, target) {
    const element = document.getElementById(elementId);
    const duration = 2000;
    const steps = 60;
    const increment = target / steps;
    let current = 0;
    
    const timer = setInterval(() => {
        current += increment;
        if (current >= target) {
            element.textContent = target;
            clearInterval(timer);
        } else {
            element.textContent = Math.floor(current);
        }
    }, duration / steps);
}

// Load Recent Jobs
async function loadRecentJobs() {
    const jobsList = document.getElementById('jobsList');
    if (!jobsList) return;
    
    try {
        const response = await fetch(API_URL + 'jobs.php?limit=6');
        const data = await response.json();
        
        if (data.success && data.jobs.length > 0) {
            jobsList.innerHTML = data.jobs.map(job => createJobCard(job)).join('');
        } else {
            jobsList.innerHTML = '<p style="text-align: center; color: var(--text-secondary);">No jobs available at the moment. Check back soon!</p>';
        }
    } catch (error) {
        console.error('Error loading jobs:', error);
        jobsList.innerHTML = '<p style="text-align: center; color: var(--error);">Failed to load jobs. Please try again later.</p>';
    }
}

// Create Job Card HTML
function createJobCard(job) {
    return `
        <div class="job-card" onclick="viewJob(${job.id})">
            <div class="job-header">
                <div>
                    <h3 class="job-title">${escapeHtml(job.title)}</h3>
                    <p class="job-company">${escapeHtml(job.company_name)}</p>
                </div>
                <span class="job-badge">${escapeHtml(job.job_type)}</span>
            </div>
            <div class="job-details">
                <div class="job-detail-item">
                    <span>📍</span>
                    <span>${escapeHtml(job.location)}</span>
                </div>
                <div class="job-detail-item">
                    <span>💼</span>
                    <span>${escapeHtml(job.experience_required)}</span>
                </div>
                <div class="job-detail-item">
                    <span>📅</span>
                    <span>${getTimeAgo(job.posted_date)}</span>
                </div>
            </div>
            <p style="color: var(--text-secondary); margin: 1rem 0;">${escapeHtml(job.description.substring(0, 100))}...</p>
            <div class="job-footer">
                <span class="job-salary">₹${formatSalary(job.salary_min)} - ₹${formatSalary(job.salary_max)}</span>
                <button class="btn-apply" onclick="event.stopPropagation(); applyJob(${job.id})">Apply Now</button>
            </div>
        </div>
    `;
}

// Search Jobs
function searchJobs() {
    const jobTitle = document.getElementById('searchJob').value;
    const location = document.getElementById('searchLocation').value;
    
    const params = new URLSearchParams();
    if (jobTitle) params.append('title', jobTitle);
    if (location) params.append('location', location);
    
    window.location.href = `jobs.html?${params.toString()}`;
}

// Filter by Category
function filterByCategory(category) {
    window.location.href = `jobs.html?category=${encodeURIComponent(category)}`;
}

// View Job Details
function viewJob(jobId) {
    window.location.href = `job-details.html?id=${jobId}`;
}

// Apply for Job
function applyJob(jobId) {
    const isLoggedIn = localStorage.getItem('user_token');
    
    if (!isLoggedIn) {
        alert('Please login to apply for jobs');
        window.location.href = `login.html?redirect=job-details.html?id=${jobId}`;
    } else {
        window.location.href = `apply.html?job_id=${jobId}`;
    }
}

// Utility Functions
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

function formatSalary(amount) {
    if (amount >= 100000) {
        return (amount / 100000).toFixed(1) + 'L';
    } else if (amount >= 1000) {
        return (amount / 1000).toFixed(1) + 'K';
    }
    return amount.toString();
}

function getTimeAgo(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const seconds = Math.floor((now - date) / 1000);
    
    const intervals = {
        year: 31536000,
        month: 2592000,
        week: 604800,
        day: 86400,
        hour: 3600,
        minute: 60
    };
    
    for (const [unit, secondsInUnit] of Object.entries(intervals)) {
        const interval = Math.floor(seconds / secondsInUnit);
        if (interval >= 1) {
            return interval === 1 ? `1 ${unit} ago` : `${interval} ${unit}s ago`;
        }
    }
    
    return 'Just now';
}

// Form Validation
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function validatePhone(phone) {
    const re = /^[6-9]\d{9}$/;
    return re.test(phone);
}

// Show Toast Notification
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 1rem 1.5rem;
        background: ${type === 'success' ? 'var(--success)' : type === 'error' ? 'var(--error)' : 'var(--primary-color)'};
        color: white;
        border-radius: 8px;
        z-index: 10000;
        animation: slideInRight 0.3s ease;
    `;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    loadStats();
    loadRecentJobs();
    
    // Add search on enter key
    const searchInputs = [document.getElementById('searchJob'), document.getElementById('searchLocation')];
    searchInputs.forEach(input => {
        if (input) {
            input.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    searchJobs();
                }
            });
        }
    });
});

// Add CSS for toast animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);