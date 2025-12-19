// Admin Categories Management JavaScript

let allCategories = [];
let editingCategoryId = null;

// Load Categories
async function loadCategories() {
    try {
        const response = await fetch(API_URL + 'admin/categories.php', {
            headers: {
                'Authorization': 'Bearer ' + localStorage.getItem('user_token')
            }
        });
        
        const data = await response.json();
        
        if (data.success && data.categories) {
            allCategories = data.categories;
            displayCategories(allCategories);
            document.getElementById('categoryCount').textContent = allCategories.length;
        } else {
            showNoCategories();
        }
    } catch (error) {
        console.error('Error loading categories:', error);
        showError();
    }
}

// Display Categories
function displayCategories(categories) {
    const grid = document.getElementById('categoriesGrid');
    
    if (!categories || categories.length === 0) {
        grid.innerHTML = `
            <div style="text-align: center; padding: 3rem; grid-column: 1 / -1;">
                <p style="color: var(--text-secondary); margin-bottom: 1rem;">No categories found</p>
                <button onclick="showAddCategoryModal()" class="btn-primary">Add First Category</button>
            </div>
        `;
        return;
    }
    
    grid.innerHTML = categories.map(category => `
        <div class="category-card ${category.is_active ? '' : 'inactive'}">
            <div class="category-header">
                <div class="category-icon">${category.icon || '🏷️'}</div>
                <div class="category-info">
                    <h3>${escapeHtml(category.category_name)}</h3>
                    <p>${escapeHtml(category.description || 'No description')}</p>
                </div>
            </div>
            <div class="category-stats">
                <span>💼 ${category.job_count || 0} jobs</span>
                <span class="status-badge ${category.is_active ? 'status-hired' : 'status-pending'}">
                    ${category.is_active ? 'Active' : 'Inactive'}
                </span>
            </div>
            <div class="category-actions">
                <button class="btn-action" onclick="editCategory(${category.id})">
                    ✏️ Edit
                </button>
                <button class="btn-action" onclick="toggleCategoryStatus(${category.id}, ${category.is_active})" 
                        style="background: ${category.is_active ? '#f59e0b' : '#10b981'};">
                    ${category.is_active ? '⏸️ Deactivate' : '▶️ Activate'}
                </button>
                <button class="btn-action" onclick="deleteCategory(${category.id}, '${escapeHtml(category.category_name)}')" 
                        style="background: var(--error);">
                    🗑️ Delete
                </button>
            </div>
        </div>
    `).join('');
}

// Show Add Category Modal
function showAddCategoryModal() {
    editingCategoryId = null;
    document.getElementById('modalTitle').textContent = 'Add New Category';
    document.getElementById('categoryForm').reset();
    document.getElementById('categoryId').value = '';
    document.getElementById('categoryActive').checked = true;
    document.getElementById('categoryModal').style.display = 'flex';
}

// Edit Category
function editCategory(categoryId) {
    const category = allCategories.find(c => c.id === categoryId);
    if (!category) return;
    
    editingCategoryId = categoryId;
    document.getElementById('modalTitle').textContent = 'Edit Category';
    document.getElementById('categoryId').value = category.id;
    document.getElementById('categoryName').value = category.category_name;
    document.getElementById('categoryDescription').value = category.description || '';
    document.getElementById('categoryIcon').value = category.icon || '';
    document.getElementById('categoryActive').checked = category.is_active == 1;
    document.getElementById('categoryModal').style.display = 'flex';
}

// Close Category Modal
function closeCategoryModal() {
    document.getElementById('categoryModal').style.display = 'none';
    document.getElementById('categoryForm').reset();
    editingCategoryId = null;
}

// Submit Category
async function submitCategory(event) {
    event.preventDefault();
    
    const categoryId = document.getElementById('categoryId').value;
    const categoryName = document.getElementById('categoryName').value.trim();
    const description = document.getElementById('categoryDescription').value.trim();
    const icon = document.getElementById('categoryIcon').value.trim();
    const isActive = document.getElementById('categoryActive').checked ? 1 : 0;
    
    if (!categoryName) {
        showToast('Category name is required', 'error');
        return;
    }
    
    const data = {
        category_name: categoryName,
        description: description,
        icon: icon,
        is_active: isActive
    };
    
    const method = categoryId ? 'PUT' : 'POST';
    if (categoryId) {
        data.id = categoryId;
    }
    
    try {
        const response = await fetch(API_URL + 'admin/categories.php', {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + localStorage.getItem('user_token')
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast(categoryId ? 'Category updated successfully' : 'Category added successfully', 'success');
            closeCategoryModal();
            loadCategories();
        } else {
            showToast(result.message || 'Failed to save category', 'error');
        }
    } catch (error) {
        console.error('Error saving category:', error);
        showToast('An error occurred', 'error');
    }
}

// Toggle Category Status
async function toggleCategoryStatus(categoryId, currentStatus) {
    const newStatus = currentStatus ? 0 : 1;
    const action = newStatus ? 'activate' : 'deactivate';
    
    if (!confirm(`Are you sure you want to ${action} this category?`)) {
        return;
    }
    
    try {
        const response = await fetch(API_URL + 'admin/categories.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + localStorage.getItem('user_token')
            },
            body: JSON.stringify({
                id: categoryId,
                is_active: newStatus
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast(`Category ${action}d successfully`, 'success');
            loadCategories();
        } else {
            showToast(data.message || 'Failed to update category', 'error');
        }
    } catch (error) {
        console.error('Error updating category:', error);
        showToast('An error occurred', 'error');
    }
}

// Delete Category
async function deleteCategory(categoryId, categoryName) {
    const category = allCategories.find(c => c.id === categoryId);
    const jobCount = category?.job_count || 0;
    
    let confirmMessage = `Are you sure you want to delete "${categoryName}"?`;
    if (jobCount > 0) {
        confirmMessage += `\n\nWarning: This category has ${jobCount} job(s) associated with it. These jobs will need to be reassigned to another category.`;
    }
    
    if (!confirm(confirmMessage)) {
        return;
    }
    
    try {
        const response = await fetch(API_URL + 'admin/categories.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + localStorage.getItem('user_token')
            },
            body: JSON.stringify({ id: categoryId })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast('Category deleted successfully', 'success');
            loadCategories();
        } else {
            showToast(data.message || 'Failed to delete category', 'error');
        }
    } catch (error) {
        console.error('Error deleting category:', error);
        showToast('An error occurred', 'error');
    }
}

// Filter Categories
function filterCategories() {
    const searchTerm = document.getElementById('searchCategory').value.toLowerCase();
    
    const filtered = allCategories.filter(category => 
        category.category_name.toLowerCase().includes(searchTerm) ||
        (category.description && category.description.toLowerCase().includes(searchTerm))
    );
    
    displayCategories(filtered);
    document.getElementById('categoryCount').textContent = filtered.length;
}

// Sort Categories
function sortCategories() {
    const sortBy = document.getElementById('sortCategories').value;
    let sorted = [...allCategories];
    
    switch(sortBy) {
        case 'name_asc':
            sorted.sort((a, b) => a.category_name.localeCompare(b.category_name));
            break;
        case 'name_desc':
            sorted.sort((a, b) => b.category_name.localeCompare(a.category_name));
            break;
        case 'jobs_desc':
            sorted.sort((a, b) => (b.job_count || 0) - (a.job_count || 0));
            break;
        case 'jobs_asc':
            sorted.sort((a, b) => (a.job_count || 0) - (b.job_count || 0));
            break;
    }
    
    displayCategories(sorted);
}

// Show No Categories
function showNoCategories() {
    const grid = document.getElementById('categoriesGrid');
    grid.innerHTML = `
        <div style="text-align: center; padding: 3rem; grid-column: 1 / -1;">
            <p style="color: var(--text-secondary); margin-bottom: 1rem;">No categories found</p>
            <button onclick="showAddCategoryModal()" class="btn-primary">Add First Category</button>
        </div>
    `;
}

// Show Error
function showError() {
    const grid = document.getElementById('categoriesGrid');
    grid.innerHTML = `
        <div style="text-align: center; padding: 3rem; color: var(--error); grid-column: 1 / -1;">
            <p>Failed to load categories. Please try again.</p>
            <button onclick="loadCategories()" class="btn-primary" style="margin-top: 1rem;">Retry</button>
        </div>
    `;
}

// Close modal when clicking outside
document.addEventListener('click', (e) => {
    const modal = document.getElementById('categoryModal');
    if (e.target === modal) {
        closeCategoryModal();
    }
});

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    if (window.location.pathname.includes('admin-categories.html')) {
        loadCategories();
    }
});