// Job Details JavaScript

let currentJob = null;
let whatsappNumber = null;

// Load job details on page load
document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const jobId = urlParams.get('id');
    
    if (!jobId) {
        showError();
        return;
    }
    
    loadJobDetails(jobId);
});

// Load job details
async function loadJobDetails(jobId) {
    try {
        const response = await fetch(API_URL + `jobs.php?id=${jobId}`);
        const data = await response.json();
        
        if (data.success && data.job) {
            currentJob = data.job;
            displayJobDetails(data.job);
            loadSimilarJobs(data.job.category_id);
        } else {
            showError();
        }
    } catch (error) {
        console.error('Error loading job details:', error);
        showError();
    }
}

// Display job details
function displayJobDetails(job) {
    // Update page title
    document.title = `${job.title} - ${job.company_name} | BackboneJobs`;
    
    // Company logo
    const logoElement = document.getElementById('companyLogo');
    if (job.company_logo) {
        logoElement.innerHTML = `<img src="${job.company_logo}" alt="${job.company_name}" style="width: 100%; height: 100%; object-fit: cover; border-radius: 12px;">`;
    } else {
        logoElement.textContent = '🏢';
    }
    
    // Job header
    document.getElementById('jobTitle').textContent = job.title;
    document.getElementById('companyName').textContent = job.company_name;
    document.getElementById('jobLocation').innerHTML = `📍 ${escapeHtml(job.location)}`;
    document.getElementById('jobType').innerHTML = `💼 ${escapeHtml(job.job_type)}`;
    document.getElementById('jobPosted').innerHTML = `📅 ${getTimeAgo(job.posted_date)}`;
    
    // Job description
    document.getElementById('jobDescription').textContent = job.description;
    
    // Requirements
    if (job.requirements && job.requirements.trim()) {
        document.getElementById('jobRequirements').textContent = job.requirements;
        document.getElementById('requirementsSection').style.display = 'block';
    }
    
    // Responsibilities
    if (job.responsibilities && job.responsibilities.trim()) {
        document.getElementById('jobResponsibilities').textContent = job.responsibilities;
        document.getElementById('responsibilitiesSection').style.display = 'block';
    }
    
    // Benefits
    if (job.benefits && job.benefits.trim()) {
        document.getElementById('jobBenefits').textContent = job.benefits;
        document.getElementById('benefitsSection').style.display = 'block';
    }
    
    // Company description
    document.getElementById('companyDescription').textContent = job.company_description || `${job.company_name} is looking for talented individuals to join their team.`;
    
    // Salary
    document.getElementById('salaryDisplay').innerHTML = `₹${formatSalary(job.salary_min)} - ₹${formatSalary(job.salary_max)}`;
    
    // Sidebar details
    document.getElementById('sidebarJobType').textContent = job.job_type;
    document.getElementById('sidebarExperience').textContent = job.experience_required || 'Any';
    document.getElementById('sidebarEducation').textContent = job.education_required || 'Any';
    document.getElementById('sidebarVacancies').textContent = job.vacancies || '1';
    document.getElementById('sidebarTimings').textContent = job.work_timings || 'As per company policy';
    document.getElementById('sidebarDeadline').textContent = job.application_deadline ? new Date(job.application_deadline).toLocaleDateString() : 'Open';
    
    // Contact information
    if (job.contact_email) {
        document.getElementById('contactEmail').innerHTML = `📧 <span>${escapeHtml(job.contact_email)}</span>`;
    } else {
        document.getElementById('contactEmail').style.display = 'none';
    }
    
    if (job.contact_phone) {
        document.getElementById('contactPhone').innerHTML = `📞 <span>${escapeHtml(job.contact_phone)}</span>`;
    } else {
        document.getElementById('contactPhone').style.display = 'none';
    }
    
    // WhatsApp button
    if (job.whatsapp_number) {
        whatsappNumber = job.whatsapp_number;
        document.getElementById('btnWhatsApp').style.display = 'flex';
    }
    
    // Check if already applied
    if (job.has_applied) {
        document.getElementById('btnApply').style.display = 'none';
        document.getElementById('appliedStatus').style.display = 'block';
    }
    
    // Check if saved
    if (job.is_saved) {
        const saveBtn = document.getElementById('btnSave');
        saveBtn.classList.add('saved');
        document.getElementById('saveIcon').textContent = '❤️';
    }
    
    // Show content
    document.getElementById('loadingState').style.display = 'none';
    document.getElementById('jobDetailsContainer').style.display = 'block';
}

// Load similar jobs
async function loadSimilarJobs(categoryId) {
    try {
        const response = await fetch(API_URL + `jobs.php?category_id=${categoryId}&limit=3`);
        const data = await response.json();
        
        if (data.success && data.jobs.length > 0) {
            // Filter out current job
            const similarJobs = data.jobs.filter(job => job.id !== currentJob.id).slice(0, 3);
            
            if (similarJobs.length > 0) {
                const container = document.getElementById('similarJobs');
                container.innerHTML = similarJobs.map(job => createJobCard(job)).join('');
            }
        }
    } catch (error) {
        console.error('Error loading similar jobs:', error);
    }
}

// Apply for job
function applyForJob() {
    if (!checkAuth()) {
        showToast('Please login to apply for jobs', 'error');
        setTimeout(() => {
            window.location.href = `login.html?redirect=${encodeURIComponent(window.location.href)}`;
        }, 1500);
        return;
    }
    
    window.location.href = `apply.html?job_id=${currentJob.id}`;
}

// Toggle save job
async function toggleSaveJob() {
    if (!checkAuth()) {
        showToast('Please login to save jobs', 'error');
        setTimeout(() => {
            window.location.href = 'login.html';
        }, 1500);
        return;
    }
    
    const saveBtn = document.getElementById('btnSave');
    const saveIcon = document.getElementById('saveIcon');
    const isSaved = saveBtn.classList.contains('saved');
    
    try {
        const response = await fetch(API_URL + 'saved-jobs.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + localStorage.getItem('user_token')
            },
            body: JSON.stringify({ job_id: currentJob.id })
        });
        
        const data = await response.json();
        
        if (data.success) {
            if (data.saved) {
                saveBtn.classList.add('saved');
                saveIcon.textContent = '❤️';
                showToast('Job saved!', 'success');
            } else {
                saveBtn.classList.remove('saved');
                saveIcon.textContent = '🤍';
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

// Share job details
function shareJobDetails() {
    const shareUrl = window.location.href;
    const shareText = `Check out this job: ${currentJob.title} at ${currentJob.company_name}`;
    
    if (navigator.share) {
        navigator.share({
            title: currentJob.title,
            text: shareText,
            url: shareUrl
        }).catch(err => console.log('Error sharing:', err));
    } else {
        // Fallback: Copy to clipboard
        navigator.clipboard.writeText(shareUrl).then(() => {
            showToast('Job link copied to clipboard!', 'success');
        }).catch(err => {
            console.error('Failed to copy:', err);
            showToast('Failed to copy link', 'error');
        });
    }
}

// Contact via WhatsApp
function contactViaWhatsApp() {
    if (!whatsappNumber) return;
    
    // Remove any non-digit characters
    const cleanNumber = whatsappNumber.replace(/\D/g, '');
    
    // Add country code if not present
    const fullNumber = cleanNumber.startsWith('91') ? cleanNumber : '91' + cleanNumber;
    
    const message = `Hi, I'm interested in the ${currentJob.title} position at ${currentJob.company_name}`;
    const whatsappUrl = `https://wa.me/${fullNumber}?text=${encodeURIComponent(message)}`;
    
    window.open(whatsappUrl, '_blank');
}

// Report job
function reportJob() {
    if (!checkAuth()) {
        showToast('Please login to report a job', 'error');
        setTimeout(() => {
            window.location.href = 'login.html';
        }, 1500);
        return;
    }
    
    const reason = prompt('Please specify the reason for reporting this job:');
    
    if (reason && reason.trim()) {
        // In a real app, send this to the server
        showToast('Thank you for your report. We will review it shortly.', 'success');
        
        // Log to console for demo
        console.log('Job reported:', {
            job_id: currentJob.id,
            reason: reason
        });
    }
}

// Show error state
function showError() {
    document.getElementById('loadingState').style.display = 'none';
    document.getElementById('jobDetailsContainer').style.display = 'none';
    document.getElementById('errorState').style.display = 'flex';
}