/* scheduler-pro/assets/script.js */
document.addEventListener('DOMContentLoaded', function() {

    // 1. Card entrance animation
    const cards = document.querySelectorAll('.sp-card');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = 'all 0.4s ease-out';

        setTimeout(() => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, 100 * (index + 1));
    });

    // 2. Button handling (loading state)
    // Some forms (e.g. Preview / Run Now) contain more than one
    // .button-hero: target the actual submitter, not just the first match,
    // so the right button shows the loading state.
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(event) {
            const submitter = event.submitter;
            const btn = (submitter && submitter.classList.contains('button-hero'))
                ? submitter
                : this.querySelector('.button-hero');
            if (btn) {
                const originalText = btn.innerHTML;
                const processingLabel = (typeof spData !== 'undefined' && spData.i18n && spData.i18n.processing)
                    ? spData.i18n.processing
                    : 'Processing...';
                btn.style.width = btn.offsetWidth + 'px'; // Keep the width
                btn.innerHTML = '<span class="dashicons dashicons-update sp-spin"></span> ' + processingLabel;
                btn.style.opacity = '0.7';
                btn.style.pointerEvents = 'none';
            }
        });
    });

    // 3. Auto-dismiss alerts with a fade
    const alerts = document.querySelectorAll('.sp-alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.8s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 800);
        }, 4000);
    });
});
