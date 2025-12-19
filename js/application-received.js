let allApplications = [];
        let currentFilter = 'all';

        async function loadApplications() {
            try {
                const response = await fetch(API_URL + 'applications.php?employer=true', {
                    headers: {
                        'Authorization': 'Bearer ' + localStorage.getItem('user_token')
                    }
                });
                
                const data = await response.json();
                
                if (data.success) {
                    allApplications = data.applications;
                    displayApplications();
                }
            } catch (error) {
                console.error('Error:', error);
                document.getElementById('applicationsTable').innerHTML = '<tr><td colspan="5" style="text-align: center; color: var(--error);">Failed to load applications</td></tr>';
            }
        }

        function filterApplications(status) {
            currentFilter = status;
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            displayApplications();
        }

        function displayApplications() {
            const table = document.getElementById('applicationsTable');
            let filtered = currentFilter === 'all' ? allApplications : allApplications.filter(a => a.status === currentFilter);
            
            if (filtered.length === 0) {
                table.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 2rem;">No applications found</td></tr>';
                return;
            }

            table.innerHTML = filtered.map(app => `
                <tr>
                    <td>
                        <strong>${escapeHtml(app.applicant_name)}</strong><br>
                        <small style="color: var(--text-secondary);">${escapeHtml(app.applicant_email)}</small><br>
                        <small style="color: var(--text-secondary);">📞 ${escapeHtml(app.applicant_phone)}</small>
                    </td>
                    <td>${escapeHtml(app.job_title)}</td>
                    <td>${new Date(app.applied_date).toLocaleDateString()}</td>
                    <td><span class="status-badge status-${app.status.toLowerCase()}">${app.status}</span></td>
                    <td>
                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                            <button class="btn-action" onclick="viewApplication(${app.id})">View</button>
                            ${app.status === 'Pending' ? `
                                <button class="btn-action" style="background: var(--success);" onclick="updateStatus(${app.id}, 'Shortlisted')">Shortlist</button>
                                <button class="btn-action" style="background: var(--error);" onclick="updateStatus(${app.id}, 'Rejected')">Reject</button>
                            ` : ''}
                        </div>
                    </td>
                </tr>
            `).join('');
        }

        function viewApplication(appId) {
            window.location.href = `application-details.html?id=${appId}`;
        }

        async function updateStatus(appId, status) {
            try {
                const response = await fetch(API_URL + 'applications.php', {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + localStorage.getItem('user_token')
                    },
                    body: JSON.stringify({ application_id: appId, status: status })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast('Application status updated', 'success');
                    loadApplications();
                } else {
                    showToast('Failed to update status', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('An error occurred', 'error');
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            if (localStorage.getItem('user_type') !== 'employer') {
                window.location.href = 'login.html';
                return;
            }
            loadApplications();
        });