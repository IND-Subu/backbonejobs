// Navbar Component - Dynamic Navigation
// This file should be loaded on ALL pages

document.addEventListener('DOMContentLoaded', () => {
    updateNavbar();
    setupMobileMenu();
});

function updateNavbar() {
    const isLoggedIn = localStorage.getItem('user_token') !== null;
    const userType = localStorage.getItem('user_type');
    const userData = JSON.parse(localStorage.getItem('user_data') || '{}');
    
    // Get current page
    const currentPage = window.location.pathname.split('/').pop() || 'index.html';
    
    // Find nav-links container
    const navLinks = document.querySelector('.nav-links');
    if (!navLinks) return;
    
    // Generate appropriate navigation
    if (isLoggedIn) {
        if (userType === 'employer') {
            navLinks.innerHTML = generateEmployerNav(userData, currentPage);
        } else {
            navLinks.innerHTML = generateUserNav(userData, currentPage);
        }
    } else {
        navLinks.innerHTML = generateGuestNav(currentPage);
    }
    
    // Setup user menu dropdown if exists
    setupUserDropdown();
    setupDarkModeToggle();
}

function generateGuestNav(currentPage) {
    return `
        <a href="index.html" ${currentPage === 'index.html' ? 'class="active"' : ''}>Home</a>
        <a href="jobs.html" ${currentPage === 'jobs.html' ? 'class="active"' : ''}>Jobs</a>
        <a href="about.html" ${currentPage === 'about.html' ? 'class="active"' : ''}>About</a>
        <a href="contact.html" ${currentPage === 'contact.html' ? 'class="active"' : ''}>Contact</a>
        <a href="login.html" class="btn-primary" style="padding: 0.5rem 1rem;">Login</a>
        <button id="darkModeToggle" class="dark-toggle">☀️</button>
    `;
}

function generateUserNav(userData, currentPage) {
    const userName = userData.name ? userData.name.split(' ')[0] : 'User';
    return `
        <div class="user-menu">
            <button class="user-btn" id="userMenuBtn">
                <span>${userName}</span>
                <span>&#9662;</span>
            </button>
            <div class="user-dropdown" id="userDropdown">
                <a href="dashboard.html">Dashboard</a>
                <a href="my-applications.html">My Applications</a>
                <a href="saved-jobs.html">Saved Jobs</a>
                <a href="profile.html">My Profile</a>
                <a href="settings.html">Settings</a>
                <div style="border-top: 1px solid var(--border); margin: 0.5rem 0;"></div>
                <a href="#" onclick="logout(); return false;">Logout</a>
            </div>
        </div>
           <button id="darkModeToggle" class="dark-toggle">☀️</button>
    `;
}

function generateEmployerNav(userData, currentPage) {
    const companyName = userData.company_name || userData.name || 'Company';
    return `
        <div class="user-menu">
            <button class="user-btn" id="userMenuBtn">
                <span>${companyName}</span>
                <span>&#9662;</span>
            </button>
            <div class="user-dropdown" id="userDropdown">
                <a href="employer-dashboard.html">Dashboard</a>
                <a href="my-jobs.html">My Jobs</a>
                <a href="applications-received.html">Applications</a>
                <a href="employer-profile.html">Company Profile</a>
                <a href="settings.html">Settings</a>
                <div style="border-top: 1px solid var(--border); margin: 0.5rem 0;"></div>
                <a href="#" onclick="logout(); return false;">Logout</a>
            </div>
        </div>
        <button id="darkModeToggle" class="dark-toggle">☀️</button>
    `;
}

function setupUserDropdown() {
    const userMenuBtn = document.getElementById('userMenuBtn');
    const userDropdown = document.getElementById('userDropdown');
    
    if (userMenuBtn && userDropdown) {
        userMenuBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            userDropdown.classList.add('show');
        });
        
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.user-menu')) {
                userDropdown.classList.remove('show');
            }
        });
    }
}

function setupMobileMenu() {
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const navLinks = document.querySelector('.nav-links');
    
    if (mobileMenuBtn && navLinks) {
        mobileMenuBtn.addEventListener('click', () => {
            navLinks.classList.toggle('mobile-active');
        });
    }
}

// Logout function
function logout() {
    localStorage.removeItem('user_token');
    localStorage.removeItem('user_type');
    localStorage.removeItem('user_data');
    showToast('Logged out successfully', 'success');
    setTimeout(() => {
        window.location.href = 'index.html';
    }, 1000);
}