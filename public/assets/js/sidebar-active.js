document.addEventListener('DOMContentLoaded', () => {
    const currentPage = window.location.pathname.split('/').pop();

    // 1. Barcha activelarni olib tashlash
    document.querySelectorAll('.sidebar-nav a.active').forEach(el => {
        el.classList.remove('active');
    });

    // 2. Faqat mos linkni active qilish
    document.querySelectorAll('.sidebar-nav a').forEach(link => {
        const href = link.getAttribute('href');

        if (href === currentPage) {
            link.classList.add('active');

            // Agar submenu ichida bo‘lsa → settings ochilsin
            const details = link.closest('details');
            if (details) {
                details.open = true;
            }
        }
    });
});
