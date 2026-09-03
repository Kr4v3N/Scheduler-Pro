/* scheduler-pro/assets/script.js */
document.addEventListener('DOMContentLoaded', function() {
    
    // 1. Animation d'entrée des cartes
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

    // 2. Gestion des boutons (Effet de chargement)
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function() {
            const btn = this.querySelector('.sp-btn');
            if (btn) {
                const originalText = btn.innerHTML;
                btn.style.width = btn.offsetWidth + 'px'; // Garde la largeur
                btn.innerHTML = '<span class="dashicons dashicons-update sp-spin"></span> Traitement...';
                btn.style.opacity = '0.7';
                btn.style.pointerEvents = 'none';
            }
        });
    });

    // 3. Auto-fermeture des alertes avec fondu
    const alerts = document.querySelectorAll('.sp-alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.8s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 800);
        }, 4000);
    });
});