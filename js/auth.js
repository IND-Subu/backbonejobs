// Authentication JavaScript

let currentUserType = 'jobseeker'; // or 'employer'

// Switch between job seeker and employer tabs
function switchTab(type) {
    currentUserType = type;
    const tabs = document.querySelectorAll('.tab');
    tabs.forEach(tab => tab.classList.remove('active'));
    event.target.classList.add('active');
}

// Login Form Handler
const loginForm = document.getElementById('loginForm');
if (loginForm) {
    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const email = document.getElementById('email').value;
        const password = document.getElementById('password').value;
        
        try {
            const response = await fetch(API_URL + 'auth/login.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    email: email,
                    password: password,
                    user_type: currentUserType
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Store token
                localStorage.setItem('user_token', data.token);
                localStorage.setItem('user_type', currentUserType);
                localStorage.setItem('user_data', JSON.stringify(data.user));
                
                showToast('Login successful!', 'success');
                
                // Redirect based on user type
                setTimeout(() => {
                    if (currentUserType === 'employer') {
                        window.location.href = 'employer-dashboard.html';
                    } else {
                        const redirect = new URLSearchParams(window.location.search).get('redirect');
                        window.location.href = redirect || 'dashboard.html';
                    }
                }, 1000);
            } else {
                showToast(data.message || 'Login failed', 'error');
            }
        } catch (error) {
            console.error('Login error:', error);
            showToast('An error occurred. Please try again.', 'error');
        }
    });
}

// Registration Form Handler
const registerForm = document.getElementById('registerForm');
if (registerForm) {
    registerForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const formData = new FormData(registerForm);
        const data = {
            user_type: currentUserType
        };
        
        // Convert FormData to JSON
        formData.forEach((value, key) => {
            data[key] = value;
        });
        
        // Validation
        if (data.password !== data.confirm_password) {
            showToast('Passwords do not match', 'error');
            return;
        }
        
        if (!validateEmail(data.email)) {
            showToast('Please enter a valid email', 'error');
            return;
        }
        
        if (!validatePhone(data.phone)) {
            showToast('Please enter a valid 10-digit phone number', 'error');
            return;
        }
        
        try {
            const response = await fetch(API_URL + 'auth/register.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.success) {
                showToast('Registration successful! Please login.', 'success');
                setTimeout(() => {
                    window.location.href = 'login.html';
                }, 2000);
            } else {
                showToast(result.message || 'Registration failed', 'error');
            }
        } catch (error) {
            console.error('Registration error:', error);
            showToast('An error occurred. Please try again.', 'error');
        }
    });
}

// Apply Job Form Handler
const applyForm = document.getElementById('applyForm');
if (applyForm) {
    applyForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const formData = new FormData(applyForm);
        
        // Get job_id from URL
        const urlParams = new URLSearchParams(window.location.search);
        const jobId = urlParams.get('job_id');
        
        if (!jobId) {
            showToast('Invalid job ID', 'error');
            return;
        }
        
        formData.append('job_id', jobId);
        
        try {
            const response = await fetch(API_URL + 'applications.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                showToast('Application submitted successfully!', 'success');
                setTimeout(() => {
                    window.location.href = 'dashboard.html';
                }, 2000);
            } else {
                showToast(data.message || 'Application failed', 'error');
            }
        } catch (error) {
            console.error('Application error:', error);
            showToast('An error occurred. Please try again.', 'error');
        }
    });
}

// File Upload Handler
const fileInputs = document.querySelectorAll('input[type="file"]');
fileInputs.forEach(input => {
    input.addEventListener('change', (e) => {
        const fileName = e.target.files[0]?.name;
        const fileNameDisplay = e.target.parentElement.querySelector('.file-name');
        if (fileNameDisplay && fileName) {
            fileNameDisplay.textContent = fileName;
        }
    });
});

// Logout Function
function logout() {
    localStorage.removeItem('user_token');
    localStorage.removeItem('user_type');
    localStorage.removeItem('user_data');
    showToast('Logged out successfully', 'success');
    setTimeout(() => {
        window.location.href = 'index.html';
    }, 1000);
}

// Check if user is logged in
function checkAuth() {
    const token = localStorage.getItem('user_token');
    const userType = localStorage.getItem('user_type');
    
    if (!token) {
        // Redirect to login if trying to access protected page
        const protectedPages = ['dashboard.html', 'employer-dashboard.html', 'apply.html', 'profile.html'];
        const currentPage = window.location.pathname.split('/').pop();
        
        if (protectedPages.includes(currentPage)) {
            window.location.href = 'login.html';
        }
        return false;
    }
    
    return true;
}

// Load user profile
async function loadUserProfile() {
    if (!checkAuth()) return;
    
    try {
        const userType = localStorage.getItem('user_type');
        const endpoint = userType === 'employer' ? 'employers/profile.php' : 'users/profile.php';
        
        const response = await fetch(API_URL + endpoint, {
            headers: {
                'Authorization': 'Bearer ' + localStorage.getItem('user_token')
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            return data.user;
        } else {
            if (response.status === 401) {
                logout();
            }
        }
    } catch (error) {
        console.error('Error loading profile:', error);
    }
    
    return null;
}

// Initialize auth on page load
document.addEventListener('DOMContentLoaded', () => {
    // Check authentication for protected pages
    checkAuth();
});