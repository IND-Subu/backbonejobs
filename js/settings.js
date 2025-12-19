
        // redirect dashboard to proper user
        const userType = localStorage.getItem('user_type');

        if(userType === 'employer'){
            document.getElementById('dashboardLink1').href= 'employer-dashboard.html';
            document.getElementById('dashboardLink2').href= 'employer-dashboard.html';
        } else{
            document.getElementById('dashboardLink1').href= 'dashboard.html';
            document.getElementById('dashboardLink2').href= 'dashboard.html';
        }
        
        // Change Password
        document.getElementById('passwordForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const newPass = formData.get('new_password');
            const confirmPass = formData.get('confirm_password');
            
            if (newPass !== confirmPass) {
                showToast('Passwords do not match', 'error');
                return;
            }
            
            const data = {
                current_password: formData.get('current_password'),
                new_password: newPass
            };
            
            try {
                const response = await fetch(API_URL + 'users/change-password.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + localStorage.getItem('user_token')
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast('Password updated successfully', 'success');
                    e.target.reset();
                } else {
                    showToast(result.message || 'Failed to update password', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('An error occurred', 'error');
            }
        });

        // Notification Preferences
        document.getElementById('notificationForm').addEventListener('submit', (e) => {
            e.preventDefault();
            
            const preferences = {
                job_alerts: document.getElementById('jobAlerts').checked,
                application_updates: document.getElementById('applicationUpdates').checked,
                marketing_emails: document.getElementById('marketingEmails').checked
            };
            
            // Save to localStorage for demo
            localStorage.setItem('notification_preferences', JSON.stringify(preferences));
            showToast('Notification preferences saved', 'success');
        });

        // Privacy Settings
        document.getElementById('privacyForm').addEventListener('submit', (e) => {
            e.preventDefault();
            
            const privacy = {
                profile_visible: document.getElementById('profileVisible').checked,
                show_phone: document.getElementById('showPhone').checked
            };
            
            // Save to localStorage for demo
            localStorage.setItem('privacy_settings', JSON.stringify(privacy));
            showToast('Privacy settings updated', 'success');
        });

        // Delete Account
        function deleteAccount() {
            const confirmed = confirm('Are you sure you want to delete your account? This action cannot be undone.');
            
            if (confirmed) {
                const doubleConfirm = prompt('Type "DELETE" to confirm account deletion:');
                
                if (doubleConfirm === 'DELETE') {
                    // In real app, call API to delete account
                    showToast('Account deletion initiated. You will receive a confirmation email.', 'success');
                    setTimeout(() => {
                        logout();
                    }, 3000);
                } else {
                    showToast('Account deletion cancelled', 'error');
                }
            }
        }

        // Load saved preferences
        document.addEventListener('DOMContentLoaded', () => {
            if (!checkAuth()) {
                window.location.href = 'login.html';
                return;
            }

            // Load notification preferences
            const notifPrefs = JSON.parse(localStorage.getItem('notification_preferences') || '{}');
            if (notifPrefs.job_alerts !== undefined) document.getElementById('jobAlerts').checked = notifPrefs.job_alerts;
            if (notifPrefs.application_updates !== undefined) document.getElementById('applicationUpdates').checked = notifPrefs.application_updates;
            if (notifPrefs.marketing_emails !== undefined) document.getElementById('marketingEmails').checked = notifPrefs.marketing_emails;

            // Load privacy settings
            const privacySettings = JSON.parse(localStorage.getItem('privacy_settings') || '{}');
            if (privacySettings.profile_visible !== undefined) document.getElementById('profileVisible').checked = privacySettings.profile_visible;
            if (privacySettings.show_phone !== undefined) document.getElementById('showPhone').checked = privacySettings.show_phone;
        });