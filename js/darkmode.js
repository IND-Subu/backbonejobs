function setupDarkModeToggle() {
    console.log('Darkmode found');
    const darkModeToggle = document.getElementById('darkModeToggle');
    const body = document.body;

    if (!darkModeToggle) return;

    // Set initial state
    if (localStorage.getItem('darkMode') === 'enabled') {
        body.setAttribute('data-theme', 'dark');
        darkModeToggle.textContent = '☀️';
    } else {
        darkModeToggle.textContent = '🌙';
    }

    // Add click listener
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