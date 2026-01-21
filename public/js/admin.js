
/**
 * Admin Panel JavaScript
 */

// Confirm actions
document.addEventListener('click', function(e) {
    if (e.target.matches('[data-confirm]')) {
        if (!confirm(e.target.dataset.confirm)) {
            e.preventDefault();
        }
    }
});

// Auto-refresh for pending bookings
if (window.location.pathname === '/admin') {
    setTimeout(function() {
        location.reload();
    }, 60000); // Refresh every minute
}

console.log('Admin panel loaded');
