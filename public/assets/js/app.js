// Umumiy JavaScript funksiyalar

// SweetAlert CDN yuklanmasa ham sahifalardagi edit/delete oqimi to'xtab qolmasligi uchun fallback.
if (typeof window.Swal === 'undefined') {
    window.Swal = {
        mixin() {
            return {
                fire(options = {}) {
                    const message = options.title || options.text || 'Xabar';
                    window.alert(message);
                    return Promise.resolve({
                        isConfirmed: true,
                        isDenied: false,
                        isDismissed: false
                    });
                }
            };
        },
        fire(...args) {
            const options = (typeof args[0] === 'object' && args[0] !== null)
                ? args[0]
                : {
                    title: args[0] || '',
                    text: args[1] || '',
                    icon: args[2] || ''
                };

            const text = options.text || options.title || 'Davom etilsinmi?';

            if (options.showDenyButton) {
                const answer = window.prompt(`${text}\nY - ha, N - yo'q, C - bekor`, 'Y');
                if (answer === null) {
                    return Promise.resolve({ isConfirmed: false, isDenied: false, isDismissed: true });
                }
                const norm = String(answer).trim().toLowerCase();
                if (norm === 'n') {
                    return Promise.resolve({ isConfirmed: false, isDenied: true, isDismissed: false });
                }
                if (norm === 'c') {
                    return Promise.resolve({ isConfirmed: false, isDenied: false, isDismissed: true });
                }
                return Promise.resolve({ isConfirmed: true, isDenied: false, isDismissed: false });
            }

            if (options.showCancelButton) {
                const ok = window.confirm(text);
                return Promise.resolve({ isConfirmed: ok, isDenied: false, isDismissed: !ok });
            }

            window.alert(text);
            return Promise.resolve({ isConfirmed: true, isDenied: false, isDismissed: false });
        }
    };
}

document.addEventListener('DOMContentLoaded', function() {
    // Joriy sanani ko'rsatish
    updateCurrentDate();
    
    // Sidebar toggle
    setupSidebarToggle();
    
    // Statistikani yangilash
    // updateStats();
    
    // Modal funksiyalari
    setupModal();

    // Jadvalda 0 qiymatlarni yashirish
    setupZeroCleaner();
});

function updateCurrentDate() {
    const dateElement = document.getElementById('currentDate');
    if (dateElement) {
        const now = new Date();
        const options = { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        };
        dateElement.textContent = now.toLocaleDateString('uz-UZ', options);
    }
}

function setupSidebarToggle() {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    const appContainer = document.querySelector('.app-container');
    const desktopBreakpoint = 1024;
    const storageKey = 'sidebarCollapsedDesktop';

    if (!sidebarToggle || !sidebar || !appContainer) {
        return;
    }

    function isMobileView() {
        return window.innerWidth <= desktopBreakpoint;
    }

    function setDesktopCollapsed(collapsed) {
        appContainer.classList.toggle('sidebar-collapsed', collapsed);
        sidebarToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        try {
            localStorage.setItem(storageKey, collapsed ? '1' : '0');
        } catch (e) {
        }
    }

    function getDesktopCollapsedSaved() {
        try {
            return localStorage.getItem(storageKey) === '1';
        } catch (e) {
            return false;
        }
    }

    function syncSidebarByViewport() {
        // Avval mobil classlarni tozalaymiz.
        sidebar.classList.remove('show');
        appContainer.classList.remove('sidebar-open');

        if (isMobileView()) {
            // Mobil holatda desktop collapse ishlatilmaydi.
            appContainer.classList.remove('sidebar-collapsed');
            sidebarToggle.setAttribute('aria-expanded', 'false');
            return;
        }

        setDesktopCollapsed(getDesktopCollapsedSaved());
    }

    sidebarToggle.addEventListener('click', function() {
        if (isMobileView()) {
            const isOpen = sidebar.classList.toggle('show');
            appContainer.classList.toggle('sidebar-open', isOpen);
            sidebarToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            return;
        }

        const willCollapse = !appContainer.classList.contains('sidebar-collapsed');
        setDesktopCollapsed(willCollapse);
    });

    window.addEventListener('resize', syncSidebarByViewport);
    syncSidebarByViewport();
}

// function updateStats() {
//     // Yo'nalishlar soni
//     const yonalishlar = JSON.parse(localStorage.getItem('yonalishlar')) || [];
//     document.getElementById('yonalishlarSoni')?.textContent = yonalishlar.length;
    
//     // Dasturlar soni
//     const dasturlar = JSON.parse(localStorage.getItem('dasturlar')) || [];
//     document.getElementById('dasturlarSoni')?.textContent = dasturlar.length;
    
//     // Rejalar soni
//     const rejalar = JSON.parse(localStorage.getItem('haftalik-rejalar')) || [];
//     document.getElementById('rejalarSoni')?.textContent = rejalar.length;
// }

function setupModal() {
    const modal = document.getElementById('yonalishModal');
    const closeModalBtn = document.getElementById('closeModal');
    const cancelBtn = document.getElementById('cancelBtn');
    
    if (modal && closeModalBtn) {
        closeModalBtn.addEventListener('click', function() {
            modal.classList.remove('show');
        });
        
        cancelBtn?.addEventListener('click', function() {
            modal.classList.remove('show');
        });
        
        // Modal tashqarisini bosganda yopish
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.classList.remove('show');
            }
        });
    }
}

// Modalni ochish funksiyasi
function openModal(modalId, title = '') {
    const modal = document.getElementById(modalId);
    const modalTitle = document.getElementById('modalTitle');
    
    if (modal) {
        if (modalTitle && title) {
            modalTitle.textContent = title;
        }
        modal.classList.add('show');
    }
}

// Formani tozalash funksiyasi
function resetForm(formId) {
    const form = document.getElementById(formId);
    if (form) {
        form.reset();
    }
}

// Xabarlarni ko'rsatish funksiyasi
function showMessage(message, type = 'success') {
    const messageDiv = document.createElement('div');
    messageDiv.className = `message message-${type}`;
    messageDiv.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
        <span>${message}</span>
        <button class="message-close">&times;</button>
    `;
    
    document.body.appendChild(messageDiv);
    
    // Animatsiya
    setTimeout(() => {
        messageDiv.classList.add('show');
    }, 10);
    
    // 5 sekunddan keyin o'chirish
    setTimeout(() => {
        messageDiv.classList.remove('show');
        setTimeout(() => {
            document.body.removeChild(messageDiv);
        }, 300);
    }, 5000);
    
    // Yopish tugmasi
    messageDiv.querySelector('.message-close').addEventListener('click', function() {
        messageDiv.classList.remove('show');
        setTimeout(() => {
            document.body.removeChild(messageDiv);
        }, 300);
    });
}

// Rasmlarni yuklash funksiyasi
function loadImagePreview(input, previewId) {
    const preview = document.getElementById(previewId);
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function setupZeroCleaner() {
    const zeroRegex = /^0([.,]0+)?$/;

    function clearZeroCells(root = document) {
        const cells = root.querySelectorAll ? root.querySelectorAll('td') : [];
        cells.forEach(cell => {
            if (cell.dataset && cell.dataset.keepZero === '1') return;
            if (cell.querySelector && cell.querySelector('input,select,textarea,button')) return;
            const text = (cell.textContent || '').trim();
            if (!text) return;
            if (zeroRegex.test(text)) {
                cell.textContent = '';
            }
        });
    }

    clearZeroCells();

    const observer = new MutationObserver(mutations => {
        let needsScan = false;
        mutations.forEach(mutation => {
            mutation.addedNodes.forEach(node => {
                if (node.nodeType !== 1) return;
                if (node.matches && (node.matches('td') || node.querySelector('td'))) {
                    needsScan = true;
                }
            });
        });
        if (needsScan) {
            clearZeroCells();
        }
    });

    if (document.body) {
        observer.observe(document.body, { childList: true, subtree: true });
    }
}
