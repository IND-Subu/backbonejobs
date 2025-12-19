// Admin Feedback Management JavaScript

let allFeedback = [];
let currentFeedbackId = null;

// Load Feedback
async function loadFeedback() {
    try {
        const response = await fetch(API_URL + 'admin/feedback.php', {
            headers: {
                'Authorization': 'Bearer ' + localStorage.getItem('user_token')
            }
        });
        
        const data = await response.json();
        
        if (data.success && data.feedback) {
            allFeedback = data.feedback;
            displayFeedback(allFeedback);
            updateFeedbackStats();
        } else {
            showNoFeedback();
        }
    } catch (error) {
        console.error('Error loading feedback:', error);
        showError();
    }
}

// Display Feedback
function displayFeedback(feedbackList) {
    const container = document.getElementById('feedbackList');
    
    if (!feedbackList || feedbackList.length === 0) {
        container.innerHTML = `
            <div style="text-align: center; padding: 3rem; color: var(--text-secondary);">
                No feedback found
            </div>
        `;
        document.getElementById('feedbackCount').textContent = '0';
        return;
    }
    
    container.innerHTML = feedbackList.map(fb => `
        <div class="feedback-item ${fb.status === 'unread' ? 'unread' : ''}" 
             onclick="viewFeedbackDetails(${fb.id})">
            <div class="feedback-header">
                <div>
                    <strong>${escapeHtml(fb.name || 'Anonymous')}</strong>
                    ${fb.email ? `<span style="color: var(--text-secondary); font-size: 0.9rem;">${escapeHtml(fb.email)}</span>` : ''}
                    ${fb.phone ? `<span style="color: var(--text-secondary); font-size: 0.9rem;">📞 ${escapeHtml(fb.phone)}</span>` : ''}
                </div>
                <div style="display: flex; gap: 0.5rem; align-items: center;">
                    <span class="feedback-type">${getFeedbackTypeLabel(fb.feedback_type)}</span>
                    <span class="status-badge status-${fb.status}">
                        ${fb.status.charAt(0).toUpperCase() + fb.status.slice(1)}
                    </span>
                </div>
            </div>
            <p class="feedback-message">${escapeHtml(fb.message).substring(0, 200)}${fb.message.length > 200 ? '...' : ''}</p>
            <div class="feedback-footer">
                <span style="color: var(--text-secondary); font-size: 0.85rem;">
                    📅 ${formatDateTime(fb.created_at)}
                </span>
                <div style="display: flex; gap: 0.5rem;">
                    <button class="btn-action" onclick="event.stopPropagation(); markAsRead(${fb.id})" 
                            ${fb.status !== 'unread' ? 'disabled' : ''}>
                        👁️ Read
                    </button>
                    <button class="btn-action" onclick="event.stopPropagation(); markAsResolved(${fb.id})"
                            ${fb.status === 'resolved' ? 'disabled' : ''}>
                        ✅ Resolve
                    </button>
                </div>
            </div>
        </div>
    `).join('');
    
    document.getElementById('feedbackCount').textContent = feedbackList.length;
}

// View Feedback Details
async function viewFeedbackDetails(feedbackId) {
    currentFeedbackId = feedbackId;
    
    try {
        const response = await fetch(API_URL + `admin/feedback.php?id=${feedbackId}`, {
            headers: {
                'Authorization': 'Bearer ' + localStorage.getItem('user_token')
            }
        });
        
        const data = await response.json();
        
        if (data.success && data.feedback) {
            const fb = data.feedback;
            
            const detailsHTML = `
                <div style="background: var(--surface); padding: 1.5rem; border-radius: 8px; margin-bottom: 1rem;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 1rem;">
                        <div>
                            <h3 style="margin-bottom: 0.5rem;">${escapeHtml(fb.name || 'Anonymous')}</h3>
                            ${fb.email ? `<p style="color: var(--text-secondary);">📧 ${escapeHtml(fb.email)}</p>` : ''}
                            ${fb.phone ? `<p style="color: var(--text-secondary);">📞 ${escapeHtml(fb.phone)}</p>` : ''}
                        </div>
                        <div style="text-align: right;">
                            <span class="feedback-type">${getFeedbackTypeLabel(fb.feedback_type)}</span>
                            <p style="color: var(--text-secondary); font-size: 0.85rem; margin-top: 0.5rem;">
                                ${formatDateTime(fb.created_at)}
                            </p>
                        </div>
                    </div>
                    
                    <div style="background: var(--background); padding: 1rem; border-radius: 8px;">
                        <strong style="color: var(--text-secondary); font-size: 0.9rem;">Message:</strong>
                        <p style="color: var(--text-primary); margin-top: 0.5rem; white-space: pre-wrap;">${escapeHtml(fb.message)}</p>
                    </div>
                    
                    ${fb.admin_response ? `
                        <div style="background: var(--background); padding: 1rem; border-radius: 8px; margin-top: 1rem; border-left: 3px solid var(--primary-color);">
                            <strong style="color: var(--text-secondary); font-size: 0.9rem;">Previous Response:</strong>
                            <p style="color: var(--text-primary); margin-top: 0.5rem; white-space: pre-wrap;">${escapeHtml(fb.admin_response)}</p>
                            <p style="color: var(--text-secondary); font-size: 0.85rem; margin-top: 0.5rem;">
                                Responded at: ${formatDateTime(fb.responded_at)}
                            </p>
                        </div>
                    ` : ''}
                    
                    <div style="margin-top: 1rem;">
                        <span class="status-badge status-${fb.status}" style="font-size: 1rem;">
                            Status: ${fb.status.charAt(0).toUpperCase() + fb.status.slice(1)}
                        </span>
                    </div>
                </div>
            `;
            
            document.getElementById('feedbackDetails').innerHTML = detailsHTML;
            document.getElementById('adminResponse').value = fb.admin_response || '';
            document.getElementById('feedbackModal').style.display = 'flex';
            
            // Auto-mark as read when viewed
            if (fb.status === 'unread') {
                markAsRead(feedbackId, false);
            }
        }
    } catch (error) {
        console.error('Error loading feedback details:', error);
        showToast('Failed to load feedback details', 'error');
    }
}

// Close Feedback Modal
function closeFeedbackModal() {
    document.getElementById('feedbackModal').style.display = 'none';
    currentFeedbackId = null;
    loadFeedback(); // Reload to reflect changes
}

// Mark as Read
async function markAsRead(feedbackId, reload = true) {
    try {
        const response = await fetch(API_URL + 'admin/feedback.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + localStorage.getItem('user_token')
            },
            body: JSON.stringify({
                id: feedbackId,
                status: 'read'
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            if (reload) {
                showToast('Marked as read', 'success');
                loadFeedback();
            }
        }
    } catch (error) {
        console.error('Error marking as read:', error);
    }
}

// Mark as Resolved
async function markAsResolved(feedbackId) {
    try {
        const response = await fetch(API_URL + 'admin/feedback.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + localStorage.getItem('user_token')
            },
            body: JSON.stringify({
                id: feedbackId,
                status: 'resolved'
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast('Marked as resolved', 'success');
            loadFeedback();
        }
    } catch (error) {
        console.error('Error marking as resolved:', error);
    }
}

// Update Feedback Status from Modal
async function updateFeedbackStatus(status) {
    if (!currentFeedbackId) return;
    
    try {
        const response = await fetch(API_URL + 'admin/feedback.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + localStorage.getItem('user_token')
            },
            body: JSON.stringify({
                id: currentFeedbackId,
                status: status
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast(`Marked as ${status}`, 'success');
            closeFeedbackModal();
        } else {
            showToast(data.message || 'Failed to update status', 'error');
        }
    } catch (error) {
        console.error('Error updating status:', error);
        showToast('An error occurred', 'error');
    }
}

// Send Response
async function sendResponse() {
    if (!currentFeedbackId) return;
    
    const response = document.getElementById('adminResponse').value.trim();
    
    if (!response) {
        showToast('Please enter a response', 'error');
        return;
    }
    
    try {
        const res = await fetch(API_URL + 'admin/feedback.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + localStorage.getItem('user_token')
            },
            body: JSON.stringify({
                id: currentFeedbackId,
                admin_response: response,
                send_email: true
            })
        });
        
        const data = await res.json();
        
        if (data.success) {
            showToast('Response sent successfully', 'success');
            closeFeedbackModal();
        } else {
            showToast(data.message || 'Failed to send response', 'error');
        }
    } catch (error) {
        console.error('Error sending response:', error);
        showToast('An error occurred', 'error');
    }
}

// Delete Feedback
async function deleteFeedback() {
    if (!currentFeedbackId) return;
    
    if (!confirm('Are you sure you want to delete this feedback? This action cannot be undone.')) {
        return;
    }
    
    try {
        const response = await fetch(API_URL + 'admin/feedback.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + localStorage.getItem('user_token')
            },
            body: JSON.stringify({ id: currentFeedbackId })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast('Feedback deleted successfully', 'success');
            closeFeedbackModal();
        } else {
            showToast(data.message || 'Failed to delete feedback', 'error');
        }
    } catch (error) {
        console.error('Error deleting feedback:', error);
        showToast('An error occurred', 'error');
    }
}

// Mark All as Read
async function markAllAsRead() {
    if (!confirm('Mark all feedback as read?')) return;
    
    try {
        const response = await fetch(API_URL + 'admin/feedback.php?action=mark_all_read', {
            method: 'PUT',
            headers: {
                'Authorization': 'Bearer ' + localStorage.getItem('user_token')
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast('All feedback marked as read', 'success');
            loadFeedback();
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('An error occurred', 'error');
    }
}

// Filter Feedback
function filterFeedback() {
    const searchTerm = document.getElementById('searchFeedback').value.toLowerCase();
    const typeFilter = document.getElementById('filterType').value;
    const statusFilter = document.getElementById('filterStatus').value;
    
    let filtered = allFeedback.filter(fb => {
        const matchSearch = !searchTerm || 
            (fb.name && fb.name.toLowerCase().includes(searchTerm)) ||
            (fb.email && fb.email.toLowerCase().includes(searchTerm)) ||
            (fb.message && fb.message.toLowerCase().includes(searchTerm));
        
        const matchType = !typeFilter || fb.feedback_type === typeFilter;
        const matchStatus = !statusFilter || fb.status === statusFilter;
        
        return matchSearch && matchType && matchStatus;
    });
    
    displayFeedback(filtered);
}

// Update Feedback Stats
function updateFeedbackStats() {
    const total = allFeedback.length;
    const unread = allFeedback.filter(fb => fb.status === 'unread').length;
    const resolved = allFeedback.filter(fb => fb.status === 'resolved').length;
    const bugs = allFeedback.filter(fb => fb.feedback_type === 'bug').length;
    
    document.getElementById('totalFeedback').textContent = total;
    document.getElementById('unreadFeedback').textContent = unread;
    document.getElementById('resolvedFeedback').textContent = resolved;
    document.getElementById('bugReports').textContent = bugs;
}

// Get Feedback Type Label
function getFeedbackTypeLabel(type) {
    const types = {
        'bug': '🐛 Bug',
        'feature': '✨ Feature',
        'general': '💬 General',
        'complaint': '⚠️ Complaint',
        'suggestion': '💡 Suggestion'
    };
    return types[type] || '💬 Feedback';
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

// Export Feedback
function exportFeedback() {
    showToast('Export feature coming soon', 'info');
}

// Show No Feedback
function showNoFeedback() {
    const container = document.getElementById('feedbackList');
    container.innerHTML = `
        <div style="text-align: center; padding: 3rem; color: var(--text-secondary);">
            No feedback received yet
        </div>
    `;
}

// Show Error
function showError() {
    const container = document.getElementById('feedbackList');
    container.innerHTML = `
        <div style="text-align: center; padding: 3rem; color: var(--error);">
            Failed to load feedback. Please try again.
            <br><br>
            <button onclick="loadFeedback()" class="btn-primary">Retry</button>
        </div>
    `;
}

// Close modal on outside click
document.addEventListener('click', (e) => {
    const modal = document.getElementById('feedbackModal');
    if (e.target === modal) {
        closeFeedbackModal();
    }
});

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    if (window.location.pathname.includes('admin-feedback.html')) {
        loadFeedback();
    }
});