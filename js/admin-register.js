// Admin Registration JavaScript

let verifiedRegistrationCode = null;

// Password strength checker
const passwordInput = document.getElementById('password');
if (passwordInput) {
    passwordInput.addEventListener('input', checkPasswordStrength);
}

function checkPasswordStrength() {
    const password = passwordInput.value;
    const strengthDiv = document.getElementById('passwordStrength');
    
    if (!password) {
        strengthDiv.innerHTML = '';
        return;
    }
    
    let strength = 0;
    let feedback = [];
    
    // Length check
    if (password.length >= 8) strength++;
    if (password.length >= 12) strength++;
    
    // Character variety checks
    if (/[a-z]/.test(password)) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[^a-zA-Z0-9]/.test(password)) strength++;
    
    // Provide feedback
    if (!/[a-z]/.test(password)) feedback.push('lowercase letter');
    if (!/[A-Z]/.test(password)) feedback.push('uppercase letter');
    if (!/[0-9]/.test(password)) feedback.push('number');
    if (!/[^a-zA-Z0-9]/.test(password)) feedback.push('special character');
    if (password.length < 8) feedback.push('at least 8 characters');
    
    let strengthClass = '';
    let strengthText = '';
    let strengthColor = '';
    
    if (strength <= 2) {
        strengthClass = 'strength-weak';
        strengthText = 'Weak';
        strengthColor = 'var(--error)';
    } else if (strength <= 4) {
        strengthClass = 'strength-medium';
        strengthText = 'Medium';
        strengthColor = '#f59e0b';
    } else {
        strengthClass = 'strength-strong';
        strengthText = 'Strong';
        strengthColor = 'var(--success)';
    }
    
    strengthDiv.innerHTML = `
        <div class="password-strength ${strengthClass}"></div>
        <small style="color: ${strengthColor}; font-weight: 600;">
            Password Strength: ${strengthText}
        </small>
        ${feedback.length > 0 ? `
            <small style="display: block; color: var(--text-secondary); margin-top: 0.25rem;">
                Add: ${feedback.join(', ')}
            </small>
        ` : ''}
    `;
}

// Code Verification Form
const codeVerifyForm = document.getElementById('codeVerifyForm');
if (codeVerifyForm) {
    codeVerifyForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const code = document.getElementById('registrationCode').value.trim();
        
        if (!code) {
            showToast('Please enter a registration code', 'error');
            return;
        }
        
        try {
            const response = await fetch(API_URL + 'admin/verify-registration-code.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ code: code })
            });
            
            const data = await response.json();
            
            if (data.success) {
                verifiedRegistrationCode = code;
                document.getElementById('verifiedCode').value = code;
                
                // Set role based on code
                const roleSelect = document.getElementById('role');
                roleSelect.value = data.role;
                
                // Show registration form
                document.getElementById('codeVerification').style.display = 'none';
                document.getElementById('registrationForm').style.display = 'block';
                
                showToast('Code verified! Please complete your registration.', 'success');
            } else {
                showToast(data.message || 'Invalid registration code', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showToast('An error occurred. Please try again.', 'error');
        }
    });
}

// Show code verification section
function showCodeVerification() {
    document.getElementById('registrationForm').style.display = 'none';
    document.getElementById('codeVerification').style.display = 'block';
    verifiedRegistrationCode = null;
    document.getElementById('codeVerifyForm').reset();
}

// Admin Registration Form
const adminRegisterForm = document.getElementById('adminRegisterForm');
if (adminRegisterForm) {
    adminRegisterForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const name = document.getElementById('name').value.trim();
        const email = document.getElementById('email').value.trim();
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirmPassword').value;
        const phone = document.getElementById('phone').value.trim();
        const role = document.getElementById('role').value;
        const code = document.getElementById('verifiedCode').value;
        const agreeTerms = document.getElementById('agreeTerms').checked;
        
        // Validation
        if (!name || !email || !password || !confirmPassword) {
            showToast('Please fill in all required fields', 'error');
            return;
        }
        
        if (!validateEmail(email)) {
            showToast('Please enter a valid email address', 'error');
            return;
        }
        
        if (password.length < 8) {
            showToast('Password must be at least 8 characters long', 'error');
            return;
        }
        
        // Password strength check
        const hasUpperCase = /[A-Z]/.test(password);
        const hasLowerCase = /[a-z]/.test(password);
        const hasNumber = /[0-9]/.test(password);
        const hasSpecial = /[^a-zA-Z0-9]/.test(password);
        
        if (!hasUpperCase || !hasLowerCase || !hasNumber || !hasSpecial) {
            showToast('Password must include uppercase, lowercase, number, and special character', 'error');
            return;
        }
        
        if (password !== confirmPassword) {
            showToast('Passwords do not match', 'error');
            return;
        }
        
        if (phone && !validatePhone(phone)) {
            showToast('Please enter a valid 10-digit phone number', 'error');
            return;
        }
        
        if (!agreeTerms) {
            showToast('You must agree to the terms and conditions', 'error');
            return;
        }
        
        if (!code) {
            showToast('Registration code is missing. Please verify your code again.', 'error');
            showCodeVerification();
            return;
        }
        
        try {
            const response = await fetch(API_URL + 'admin/register.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    name: name,
                    email: email,
                    password: password,
                    phone: phone || null,
                    role: role,
                    registration_code: code
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                showToast('Admin account created successfully!', 'success');
                
                // Clear form
                adminRegisterForm.reset();
                
                // Redirect to login after 2 seconds
                setTimeout(() => {
                    window.location.href = 'admin-login.html?registered=true';
                }, 2000);
            } else {
                showToast(data.message || 'Registration failed', 'error');
                
                // If code is invalid, show code verification again
                if (data.message && data.message.includes('code')) {
                    showCodeVerification();
                }
            }
        } catch (error) {
            console.error('Error:', error);
            showToast('An error occurred. Please try again.', 'error');
        }
    });
}

// Check if already logged in as admin
document.addEventListener('DOMContentLoaded', () => {
    const userType = localStorage.getItem('user_type');
    const token = localStorage.getItem('user_token');
    
    if (token && userType === 'admin') {
        showToast('You are already logged in as admin', 'info');
        setTimeout(() => {
            window.location.href = 'admin-dashboard.html';
        }, 1500);
    }
    
    // Setup dark mode
    setupDarkModeToggle();
});

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