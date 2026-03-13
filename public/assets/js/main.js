// Main JavaScript File - php.samdu.uz
// No frameworks, pure vanilla JavaScript

// ==================== Global Variables ====================
let sampleModal;

// ==================== Initialize on DOM Load ====================
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap modal
    const modalElement = document.getElementById('sampleModal');
    if (modalElement) {
        sampleModal = new bootstrap.Modal(modalElement);
    }

    // Initialize all event listeners
    initDocumentsForm();
    initProfileForm();
    initSidebar();
    initFileInputs();
    initSampleButtons();
});

// ==================== Sidebar Toggle ====================
function initSidebar() {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');

    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('show');
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            if (window.innerWidth < 768) {
                if (!sidebar.contains(event.target) && !sidebarToggle.contains(event.target)) {
                    sidebar.classList.remove('show');
                }
            }
        });
    }
}


// ==================== Documents Form ====================
function initDocumentsForm() {
    const documentsForm = document.getElementById('documentsForm');
    
    if (documentsForm) {
        documentsForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Validate all required fields
            let valid = true;
            const requiredInputs = documentsForm.querySelectorAll('[required]');
            
            requiredInputs.forEach(input => {
                if (input.type === 'file') {
                    if (!input.files || input.files.length === 0) {
                        input.classList.add('is-invalid');
                        valid = false;
                    } else {
                        input.classList.remove('is-invalid');
                        input.classList.add('is-valid');
                    }
                } else if (input.type === 'email') {
                    if (!validateEmail(input.value)) {
                        input.classList.add('is-invalid');
                        valid = false;
                    } else {
                        input.classList.remove('is-invalid');
                        input.classList.add('is-valid');
                    }
                } else {
                    if (input.value.trim() === '') {
                        input.classList.add('is-invalid');
                        valid = false;
                    } else {
                        input.classList.remove('is-invalid');
                        input.classList.add('is-valid');
                    }
                }
            });
            
            if (valid) {
                // Show success message
                const successMsg = document.getElementById('formSuccessMessage');
                successMsg.classList.remove('d-none');
                
                // Scroll to top
                window.scrollTo({ top: 0, behavior: 'smooth' });
                
                // Reset form after 3 seconds
                setTimeout(() => {
                    documentsForm.reset();
                    successMsg.classList.add('d-none');
                    
                    // Clear all validation classes
                    requiredInputs.forEach(input => {
                        input.classList.remove('is-valid', 'is-invalid');
                    });
                    
                    // Clear file previews
                    const filePreviews = documentsForm.querySelectorAll('.file-name-preview');
                    filePreviews.forEach(preview => {
                        preview.textContent = '';
                    });
                }, 3000);
            } else {
                // Scroll to first invalid field
                const firstInvalid = documentsForm.querySelector('.is-invalid');
                if (firstInvalid) {
                    firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstInvalid.focus();
                }
            }
        });

        // Real-time email validation
        const emailInput = document.getElementById('email');
        if (emailInput) {
            emailInput.addEventListener('input', function() {
                if (validateEmail(this.value)) {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                } else {
                    this.classList.remove('is-valid');
                    if (this.value !== '') {
                        this.classList.add('is-invalid');
                    }
                }
            });
        }
    }
}

// ==================== File Input Handlers ====================
function initFileInputs() {
    const fileInputs = document.querySelectorAll('.file-input');
    
    fileInputs.forEach(input => {
        input.addEventListener('change', function() {
            const preview = this.parentElement.nextElementSibling;
            
            if (this.files && this.files[0]) {
                const fileName = this.files[0].name;
                const fileSize = (this.files[0].size / 1024).toFixed(2); // KB
                
                if (preview && preview.classList.contains('file-name-preview')) {
                    preview.textContent = `📎 ${fileName} (${fileSize} KB)`;
                }
                
                // Remove invalid state
                this.classList.remove('is-invalid');
            } else {
                if (preview && preview.classList.contains('file-name-preview')) {
                    preview.textContent = '';
                }
            }
        });
    });
}

// ==================== Sample Button Handlers ====================
function initSampleButtons() {
    const sampleButtons = document.querySelectorAll('.sample-btn');
    
    sampleButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const sampleType = this.getAttribute('data-sample');
            showSampleModal(sampleType);
        });
    });
}

function showSampleModal(type) {
    if (!sampleModal) return;
    
    const modalBody = document.getElementById('sampleModalBody');
    const modalTitle = document.getElementById('sampleModalLabel');
    
    // Sample content for different document types
    const sampleContent = {
        passport: {
            title: 'Passport namunasi',
            content: `
                <div class="text-center">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Pasportingizning birinchi sahifasi (ma'lumotlar sahifasi) rangli skanerlangan nusxasi
                    </div>
                    <div class="bg-light p-4 rounded">
                        <i class="fas fa-id-card" style="font-size: 5rem; color: #0d6efd;"></i>
                        <p class="mt-3 mb-0">PDF, JPG yoki PNG formatda yuklang</p>
                        <p class="text-muted small">Maksimal hajmi: 5MB</p>
                    </div>
                </div>
            `
        },
        letter: {
            title: "Yo'llanma xat namunasi",
            content: `
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Universitet yoki muassasa tomonidan berilgan rasmiy yo'llanma xat
                </div>
                <div class="bg-light p-4 rounded">
                    <h6>Yo'llanma xat tarkibi:</h6>
                    <ul class="text-start">
                        <li>Muassasa nomi va manzili</li>
                        <li>Talabaning F.I.Sh.</li>
                        <li>Yuboruvchi tashkilot ma'lumotlari</li>
                        <li>Rasmiy muhur va imzo</li>
                    </ul>
                </div>
            `
        },
        application: {
            title: 'Ariza namunasi',
            content: `
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    PhD dasturiga qabul qilish uchun shaxsiy ariza
                </div>
                <div class="bg-light p-4 rounded">
                    <h6>Ariza tarkibi:</h6>
                    <ul class="text-start">
                        <li>Kim tomonidan berilayotgani</li>
                        <li>Kimga yuborilayotgani (rektor, dekan)</li>
                        <li>Qabul qilish so'rovi</li>
                        <li>Mutaxassislik yo'nalishi</li>
                        <li>Sana va imzo</li>
                    </ul>
                </div>
            `
        },
        oak: {
            title: 'OAK Bulletin namunasi',
            content: `
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Oliy attestatsiya komissiyasi byulleteni
                </div>
                <div class="bg-light p-4 rounded text-center">
                    <i class="fas fa-newspaper" style="font-size: 5rem; color: #198754;"></i>
                    <p class="mt-3 mb-0">OAK rasmiy nashridagi e'lon nusxasi</p>
                    <p class="text-muted small">PDF formatda yuklang</p>
                </div>
            `
        },
        phd: {
            title: 'PhD Status xati namunasi',
            content: `
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    PhD talabaligingizni tasdiqlovchi rasmiy xat
                </div>
                <div class="bg-light p-4 rounded">
                    <h6>Xat tarkibi:</h6>
                    <ul class="text-start">
                        <li>Universitet yoki muassasa nomi</li>
                        <li>PhD talabasi statusini tasdiqlash</li>
                        <li>Yo'nalish va mutaxassislik</li>
                        <li>Rahbar ma'lumotlari</li>
                        <li>Rasmiy muhur va imzo</li>
                    </ul>
                </div>
            `
        },
        exam: {
            title: 'Imtihon ma\'lumoti namunasi',
            content: `
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Qabul imtihonlari yoki test natijalari haqida ma'lumot
                </div>
                <div class="bg-light p-4 rounded">
                    <h6>Kerakli ma'lumotlar:</h6>
                    <ul class="text-start">
                        <li>Imtihon sanasi va joyi</li>
                        <li>Imtihon natijalari (ball)</li>
                        <li>Imtihon turi (yozma, og'zaki, test)</li>
                        <li>Sertifikat yoki natija varag'i</li>
                    </ul>
                </div>
            `
        }
    };
    
    const sample = sampleContent[type] || sampleContent.passport;
    
    modalTitle.textContent = sample.title;
    modalBody.innerHTML = sample.content;
    
    sampleModal.show();
}

// ==================== Profile Form ====================
function initProfileForm() {
    const profileForm = document.getElementById('profileForm');
    const editBtn = document.getElementById('editProfileBtn');
    const cancelBtn = document.getElementById('cancelEditBtn');
    const saveContainer = document.getElementById('saveButtonContainer');
    
    if (editBtn) {
        editBtn.addEventListener('click', function() {
            // Enable all inputs
            const inputs = profileForm.querySelectorAll('input');
            inputs.forEach(input => {
                input.disabled = false;
            });
            
            // Show save buttons
            if (saveContainer) {
                saveContainer.classList.remove('d-none');
            }
            
            // Hide edit button
            editBtn.classList.add('d-none');
        });
    }
    
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function() {
            // Disable all inputs
            const inputs = profileForm.querySelectorAll('input');
            inputs.forEach(input => {
                input.disabled = true;
            });
            
            // Hide save buttons
            if (saveContainer) {
                saveContainer.classList.add('d-none');
            }
            
            // Show edit button
            if (editBtn) {
                editBtn.classList.remove('d-none');
            }
            
            // Reset form to original values (simulated)
            profileForm.reset();
        });
    }
    
    if (profileForm) {
        profileForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Simulate saving
            const successMsg = document.getElementById('profileSuccessMessage');
            successMsg.classList.remove('d-none');
            
            // Scroll to top
            window.scrollTo({ top: 0, behavior: 'smooth' });
            
            // Disable inputs
            const inputs = profileForm.querySelectorAll('input');
            inputs.forEach(input => {
                input.disabled = true;
            });
            
            // Hide save buttons
            if (saveContainer) {
                saveContainer.classList.add('d-none');
            }
            
            // Show edit button
            if (editBtn) {
                editBtn.classList.remove('d-none');
            }
            
            // Hide success message after 3 seconds
            setTimeout(() => {
                successMsg.classList.add('d-none');
            }, 3000);
        });
    }
}

// ==================== Utility Functions ====================
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(String(email).toLowerCase());
}

function showLoadingButton(button) {
    if (button) {
        button.classList.add('loading');
        button.disabled = true;
    }
}

// dashboard.js
document.addEventListener('DOMContentLoaded', function() {
    // Sidebar Toggle for Mobile
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    
    if (sidebarToggle && sidebar) {
        // Create overlay element
        let overlay = document.querySelector('.sidebar-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'sidebar-overlay';
            document.body.appendChild(overlay);
        }
        
        // Toggle sidebar on button click
        sidebarToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
            document.body.style.overflow = sidebar.classList.contains('show') ? 'hidden' : '';
        });
        
        // Close sidebar when clicking overlay
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
            document.body.style.overflow = '';
        });
        
        // Close sidebar on window resize if open
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 768 && sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
                document.body.style.overflow = '';
            }
        });
    }
    
    // Active link highlighting
    const currentPage = window.location.pathname.split('/').pop();
    const navLinks = document.querySelectorAll('.sidebar .nav-link');
    
    navLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (href === currentPage || (currentPage === '' && href === 'index.html')) {
            link.classList.add('active');
        } else {
            link.classList.remove('active');
        }
    });
    
    // Card hover animations
    const cards = document.querySelectorAll('.card');
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
});