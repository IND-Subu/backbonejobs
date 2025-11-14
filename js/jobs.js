// Jobs Page JavaScript

let allJobs = [];
let filteredJobs = [];
let currentPage = 1;
const jobsPerPage = 10;

// Load jobs on page load
document.addEventListener('DOMContentLoaded', () => {
    loadJobs();
    
    // Get URL parameters for pre-filtering
    const urlParams = new URLSearchParams(window.location.search);
    const category = urlParams.get('category');
    const location = urlParams.get('location');
    const title = urlParams.get('title');
    
    if (category) {
        document.getElementById('filterCategory').value = category;
    }
    if (location) {
        document.getElementById('filterLocation').value = location;
    }
    if (title) {
        document.getElementById('filterKeywords').value = title;
    }
});

// Load all jobs
async function loadJobs() {
    showLoading();
    
    try {
        const response = await fetch(API_URL + 'jobs.php?limit=100');
        const data = await response.json();
        
        if (data.success) {
            allJobs = data.jobs;
            filteredJobs = [...allJobs];
            
            // Update total count
            if (document.getElementById('totalJobsCount')) {
                document.getElementById('totalJobsCount').textContent = allJobs.length;
            }
            
            applyFilters();
        } else {
            showNoJobsState();
        }
    } catch (error) {
        console.error('Error loading jobs:', error);
        showError();
    }
}

// Apply all filters
function applyFilters() {
    const keywords = document.getElementById('filterKeywords')?.value.toLowerCase() || '';
    const location = document.getElementById('filterLocation')?.value.toLowerCase() || '';
    const category = document.getElementById('filterCategory')?.value || '';
    const minSalary = document.getElementById('filterSalary')?.value || '';
    const experience = document.getElementById('filterExperience')?.value || '';
    const postedDays = document.getElementById('filterPosted')?.value || '';
    const sortBy = document.getElementById('sortBy')?.value || 'date_desc';
    
    // Get checked job types
    const jobTypes = Array.from(document.querySelectorAll('.checkbox-group input[type="checkbox"]:checked'))
        .map(cb => cb.value);
    
    // Filter jobs
    filteredJobs = allJobs.filter(job => {
        // Keywords filter
        if (keywords) {
            const matchKeywords = job.title.toLowerCase().includes(keywords) ||
                                job.company_name.toLowerCase().includes(keywords) ||
                                job.description.toLowerCase().includes(keywords);
            if (!matchKeywords) return false;
        }
        
        // Location filter
        if (location) {
            const matchLocation = job.location.toLowerCase().includes(location) ||
                                job.city?.toLowerCase().includes(location);
            if (!matchLocation) return false;
        }
        
        // Category filter
        if (category && job.category_name !== category) {
            return false;
        }
        
        // Job type filter
        if (jobTypes.length > 0 && !jobTypes.includes(job.job_type)) {
            return false;
        }
        
        // Salary filter
        if (minSalary && job.salary_min < parseInt(minSalary)) {
            return false;
        }
        
        // Experience filter
        if (experience && job.experience_required !== experience) {
            return false;
        }
        
        // Posted date filter
        if (postedDays) {
            const jobDate = new Date(job.posted_date);
            const cutoffDate = new Date();
            cutoffDate.setDate(cutoffDate.getDate() - parseInt(postedDays));
            if (jobDate < cutoffDate) return false;
        }
        
        return true;
    });
    
    // Sort jobs
    sortJobs(sortBy);
    
    // Reset to first page
    currentPage = 1;
    
    // Display results
    displayJobs();
}

// Sort jobs
function sortJobs(sortBy) {
    switch (sortBy) {
        case 'date_desc':
            filteredJobs.sort((a, b) => new Date(b.posted_date) - new Date(a.posted_date));
            break;
        case 'date_asc':
            filteredJobs.sort((a, b) => new Date(a.posted_date) - new Date(b.posted_date));
            break;
        case 'salary_desc':
            filteredJobs.sort((a, b) => b.salary_max - a.salary_max);
            break;
        case 'salary_asc':
            filteredJobs.sort((a, b) => a.salary_min - b.salary_min);
            break;
        case 'company_asc':
            filteredJobs.sort((a, b) => a.company_name.localeCompare(b.company_name));
            break;
    }
}

// Display jobs
function displayJobs() {
    const jobsGrid = document.getElementById('jobsGrid');
    const loadingState = document.getElementById('loadingState');
    const noResults = document.getElementById('noResults');
    const pagination = document.getElementById('pagination');
    const resultsCount = document.getElementById('resultsCount');
    
    // Hide all states
    loadingState.style.display = 'none';
    noResults.style.display = 'none';
    jobsGrid.style.display = 'none';
    pagination.style.display = 'none';
    
    // Check if we have results
    if (filteredJobs.length === 0) {
        noResults.style.display = 'block';
        resultsCount.textContent = 'No jobs found';
        return;
    }
    
    // Calculate pagination
    const totalPages = Math.ceil(filteredJobs.length / jobsPerPage);
    const startIndex = (currentPage - 1) * jobsPerPage;
    const endIndex = Math.min(startIndex + jobsPerPage, filteredJobs.length);
    const jobsToDisplay = filteredJobs.slice(startIndex, endIndex);
    
    // Update results count
    resultsCount.textContent = `Showing ${startIndex + 1}-${endIndex} of ${filteredJobs.length} jobs`;
    
    // Display jobs
    jobsGrid.innerHTML = jobsToDisplay.map(job => createDetailedJobCard(job)).join('');
    jobsGrid.style.display = 'grid';
    
    // Display pagination if needed
    if (totalPages > 1) {
        displayPagination(totalPages);
        pagination.style.display = 'flex';
    }
}

// Create detailed job card
function createDetailedJobCard(job) {
    const isSaved = false; // Check if job is saved
    
    return `
        <div class="job-card-detailed" onclick="viewJob(${job.id})">
            <div class="job-card-top">
                <div class="job-card-info">
                    <div class="company-info">
                        <div class="company-logo">
                            ${job.company_logo ? `<img src="${job.company_logo}" alt="${job.company_name}">` : '🏢'}
                        </div>
                        <div class="company-details">
                            <h3>${escapeHtml(job.title)}</h3>
                            <p>${escapeHtml(job.company_name)}</p>
                        </div>
                    </div>
                </div>
                <div class="job-card-actions">
                    <button class="btn-icon ${isSaved ? 'saved' : ''}" 
                            onclick="event.stopPropagation(); toggleSaveJob(${job.id})" 
                            title="Save Job">
                        ${isSaved ? '❤️' : '🤍'}
                    </button>
                    <button class="btn-icon" 
                            onclick="event.stopPropagation(); shareJob(${job.id})" 
                            title="Share Job">
                        📤
                    </button>
                </div>
            </div>
            
            <div class="job-tags">
                <span class="job-tag">📍 ${escapeHtml(job.location)}</span>
                <span class="job-tag">💼 ${escapeHtml(job.job_type)}</span>
                <span class="job-tag">👤 ${escapeHtml(job.experience_required || 'Any')}</span>
                ${job.category_name ? `<span class="job-tag">🏷️ ${escapeHtml(job.category_name)}</span>` : ''}
            </div>
            
            <p class="job-description">
                ${escapeHtml(job.description.substring(0, 200))}${job.description.length > 200 ? '...' : ''}
            </p>
            
            <div class="job-meta">
                <div class="job-salary-range">
                    ₹${formatSalary(job.salary_min)} - ₹${formatSalary(job.salary_max)}
                </div>
                <div class="job-posted">
                    Posted ${getTimeAgo(job.posted_date)}
                </div>
            </div>
        </div>
    `;
}

// Display pagination
function displayPagination(totalPages) {
    const pagination = document.getElementById('pagination');
    let html = '';
    
    // Previous button
    html += `<button class="pagination-btn" onclick="changePage(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}>
        ← Previous
    </button>`;
    
    // Page numbers
    const startPage = Math.max(1, currentPage - 2);
    const endPage = Math.min(totalPages, currentPage + 2);
    
    if (startPage > 1) {
        html += `<button class="pagination-btn" onclick="changePage(1)">1</button>`;
        if (startPage > 2) {
            html += `<span style="padding: 0 0.5rem;">...</span>`;
        }
    }
    
    for (let i = startPage; i <= endPage; i++) {
        html += `<button class="pagination-btn ${i === currentPage ? 'active' : ''}" onclick="changePage(${i})">
            ${i}
        </button>`;
    }
    
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            html += `<span style="padding: 0 0.5rem;">...</span>`;
        }
        html += `<button class="pagination-btn" onclick="changePage(${totalPages})">${totalPages}</button>`;
    }
    
    // Next button
    html += `<button class="pagination-btn" onclick="changePage(${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''}>
        Next →
    </button>`;
    
    pagination.innerHTML = html;
}

// Change page
function changePage(page) {
    currentPage = page;
    displayJobs();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Clear all filters
function clearFilters() {
    document.getElementById('filterKeywords').value = '';
    document.getElementById('filterLocation').value = '';
    document.getElementById('filterCategory').value = '';
    document.getElementById('filterSalary').value = '';
    document.getElementById('filterExperience').value = '';
    document.getElementById('filterPosted').value = '';
    document.getElementById('sortBy').value = 'date_desc';
    
    // Uncheck all job type checkboxes
    document.querySelectorAll('.checkbox-group input[type="checkbox"]').forEach(cb => {
        cb.checked = false;
    });
    
    // Clear URL parameters
    window.history.replaceState({}, '', window.location.pathname);
    
    applyFilters();
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
            showToast(data.saved ? 'Job saved!' : 'Job removed from saved', 'success');
            // Reload jobs to update UI
            loadJobs();
        } else {
            showToast(data.message || 'Failed to save job', 'error');
        }
    } catch (error) {
        console.error('Error saving job:', error);
        showToast('An error occurred', 'error');
    }
}

// Share job
function shareJob(jobId) {
    const job = allJobs.find(j => j.id === jobId);
    if (!job) return;
    
    const shareUrl = `${window.location.origin}/job-details.html?id=${jobId}`;
    const shareText = `Check out this job: ${job.title} at ${job.company_name}`;
    
    if (navigator.share) {
        navigator.share({
            title: job.title,
            text: shareText,
            url: shareUrl
        }).catch(err => console.log('Error sharing:', err));
    } else {
        // Fallback: Copy to clipboard
        navigator.clipboard.writeText(shareUrl).then(() => {
            showToast('Job link copied to clipboard!', 'success');
        }).catch(err => {
            console.error('Failed to copy:', err);
        });
    }
}

// Show loading state
function showLoading() {
    document.getElementById('loadingState').style.display = 'block';
    document.getElementById('jobsGrid').style.display = 'none';
    document.getElementById('noResults').style.display = 'none';
}

// Show error state
function showError() {
    const jobsGrid = document.getElementById('jobsGrid');
    const loadingState = document.getElementById('loadingState');
    const noResults = document.getElementById('noResults');
    
    loadingState.style.display = 'none';
    noResults.style.display = 'none';
    
    jobsGrid.style.display = 'block';
    jobsGrid.innerHTML = `
        <div style="text-align: center; padding: 3rem; color: var(--error);">
            <h3>Failed to load jobs</h3>
            <p>Please try again later</p>
            <button class="btn-primary" onclick="loadJobs()">Retry</button>
        </div>
    `;
}

// Show no jobs state
function showNoJobsState() {
    const jobsGrid = document.getElementById('jobsGrid');
    const loadingState = document.getElementById('loadingState');
    const noResults = document.getElementById('noResults');
    
    loadingState.style.display = 'none';
    jobsGrid.style.display = 'none';
    noResults.style.display = 'block';
}

// Check if user is authenticated
function checkAuth() {
    return localStorage.getItem('user_token') !== null;
}
