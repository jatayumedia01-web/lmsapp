/* Devithor admin — small enhancements. No framework. */

// Confirm-on-click for any element with data-confirm.
document.addEventListener('click', (e) => {
    const el = e.target.closest('[data-confirm]');
    if (!el) return;
    if (!confirm(el.dataset.confirm)) {
        e.preventDefault();
        e.stopPropagation();
    }
});

// Submit a parent form when an element with data-submit-form is clicked.
document.addEventListener('click', (e) => {
    const el = e.target.closest('[data-submit-form]');
    if (!el) return;
    const form = el.closest('form');
    if (form) form.submit();
});

// Auto-hide flash alerts after 4s for a less cluttered admin.
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.alert.auto-hide').forEach((alert) => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.4s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 4000);
    });
});
