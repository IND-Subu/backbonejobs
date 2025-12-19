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